<?php

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Exceptions\DomainRuleException;
use App\Jobs\ProcessPaymentProof;
use App\Models\GiftCardSubmission;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\PaymentProofFile;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\FileStorageService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Accepts a proof of payment (section 5.5): private storage, hashing and duplicate flags,
 * then queues the malware scan before the proof reaches the review queue.
 */
class ProofSubmissionService
{
    public function __construct(
        private readonly FileStorageService $files,
        private readonly OrderStateMachine $states,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount_paid: string|float, payer_name: string, paid_on: string, transaction_id?: ?string, note?: ?string, gift_card?: ?array<string, mixed>}  $data
     * @param  array<int, UploadedFile>  $uploads
     */
    public function submit(Order $order, User $customer, array $data, array $uploads): PaymentProof
    {
        $max = (int) config('platform.upload.proof_max_files', 3);
        if ($uploads === [] || count($uploads) > $max) {
            throw new DomainRuleException('invalid_files', __('Upload between 1 and :max files.', ['max' => $max]));
        }

        /** @var array<int, StoredFile> $stored */
        $stored = [];

        try {
            $proof = $this->persist($order, $customer, $data, $uploads, $stored);
        } catch (Throwable $e) {
            // The transaction rolled back: remove any file already written to storage.
            foreach ($stored as $file) {
                Storage::disk($file->disk)->delete($file->path);
            }

            throw $e;
        }

        ProcessPaymentProof::dispatch($proof->id)->afterCommit();

        return $proof;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, StoredFile>  $stored
     */
    private function persist(Order $order, User $customer, array $data, array $uploads, array &$stored): PaymentProof
    {
        return DB::transaction(function () use ($order, $customer, $data, $uploads, &$stored): PaymentProof {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->user_id !== $customer->id) {
                throw new DomainRuleException('forbidden', __('This order does not belong to you.'), 403);
            }
            if (! $order->status->acceptsProof()) {
                throw new DomainRuleException('order_not_accepting_proof', __('A proof cannot be uploaded for this order right now.'), 409);
            }
            if ($order->expires_at !== null && $order->expires_at->isPast() && $order->status->canExpire()) {
                throw new DomainRuleException('order_expired', __('This order has expired. Please book again.'), 409);
            }

            $payment = $order->payments()->whereNull('superseded_at')->latest('selected_at')->lockForUpdate()->first();
            if ($payment === null) {
                throw new DomainRuleException('no_method_selected', __('Choose a payment method first.'), 409);
            }

            $method = $payment->method;
            $transactionId = isset($data['transaction_id']) ? trim((string) $data['transaction_id']) : null;
            if ($method->requires_transaction_id && blank($transactionId)) {
                throw new DomainRuleException('transaction_id_required', __('The transaction ID is required for this payment method.'));
            }

            $paidOn = CarbonImmutable::parse($data['paid_on']);
            if ($paidOn->isAfter(now()->endOfDay()) || $paidOn->isBefore($order->created_at->copy()->subDay()->startOfDay())) {
                throw new DomainRuleException('invalid_payment_date', __('The payment date must be between the order date and today.'));
            }

            $amountMinor = Money::fromMajor($data['amount_paid'], $payment->currency);
            if ($amountMinor <= 0) {
                throw new DomainRuleException('invalid_amount', __('Enter the amount you paid.'));
            }

            $giftCard = $method->isGiftCard()
                ? $this->validateGiftCard($customer, $method->gift_card_rules ?? [], (array) ($data['gift_card'] ?? []), $payment->currency)
                : null;

            $proof = new PaymentProof;
            $proof->forceFill([
                'order_payment_id' => $payment->id,
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'amount_paid' => $amountMinor,
                'currency' => $payment->currency,
                'payer_name' => trim($data['payer_name']),
                'paid_on' => $paidOn->toDateString(),
                'transaction_id' => $transactionId ?: null,
                'customer_note' => isset($data['note']) ? trim((string) $data['note']) : null,
                'status' => ProofStatus::PendingScan,
                'submitted_at' => now(),
            ])->save();

            $hashes = [];
            foreach ($uploads as $upload) {
                $file = $this->files->storeUpload(
                    $upload,
                    'payment_proofs',
                    $customer,
                    config('platform.upload.proof_mimes'),
                    (int) config('platform.upload.proof_max_kb'),
                );
                $stored[] = $file;
                PaymentProofFile::query()->create(['payment_proof_id' => $proof->id, 'file_id' => $file->id, 'sha256' => $file->sha256]);
                $hashes[] = $file->sha256;
            }

            if ($giftCard !== null) {
                $submission = new GiftCardSubmission;
                $submission->forceFill($giftCard + ['payment_proof_id' => $proof->id])->save();
            }

            $this->flagDuplicates($proof, $hashes, $transactionId);

            $payment->forceFill(['status' => OrderPaymentStatus::ProofSubmitted])->save();
            $this->states->transition($order, OrderStatus::ProofSubmitted, 'proof_submitted', $customer);
            $this->audit->log('payment.proof_submitted', $proof, null, [
                'order' => $order->number,
                'amount_paid' => $amountMinor,
                'currency' => $payment->currency,
                'files' => count($uploads),
                'duplicate' => $proof->is_duplicate,
            ], $customer);

            return $proof;
        });
    }

    /**
     * A file hash or transaction id already used on another order raises a flag for the verifier (P7).
     *
     * @param  array<int, string>  $hashes
     */
    private function flagDuplicates(PaymentProof $proof, array $hashes, ?string $transactionId): void
    {
        $matches = PaymentProof::query()
            ->where('order_id', '!=', $proof->order_id)
            ->where(function ($query) use ($hashes, $transactionId): void {
                $query->whereHas('files', fn ($q) => $q->whereIn('payment_proof_files.sha256', $hashes));
                if (filled($transactionId)) {
                    $query->orWhere('transaction_id', $transactionId);
                }
            })
            ->with('order:id,number')
            ->limit(10)
            ->get();

        if ($matches->isNotEmpty()) {
            $proof->forceFill([
                'is_duplicate' => true,
                'duplicate_of' => $matches->map(fn (PaymentProof $p) => $p->order?->number)->filter()->unique()->values()->all(),
            ])->save();
        }
    }

    /**
     * Validates gift card input against the method rules (FR-93) before anything is stored.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function validateGiftCard(User $customer, array $rules, array $card, string $currency): array
    {
        $brand = (string) ($card['brand'] ?? '');
        $code = preg_replace('/\s+/', '', (string) ($card['code'] ?? '')) ?? '';
        $amount = Money::fromMajor($card['amount'] ?? 0, $currency);

        $brands = array_map('strtolower', (array) ($rules['brands'] ?? []));
        if ($brand === '' || ! in_array(strtolower($brand), $brands, true)) {
            throw new DomainRuleException('gift_card_brand', __('This gift card brand is not accepted.'));
        }
        if (strlen($code) < 6 || strlen($code) > 64) {
            throw new DomainRuleException('gift_card_code', __('Enter the full gift card code.'));
        }

        $min = (int) ($rules['per_card_min'] ?? 0);
        $max = (int) ($rules['per_card_max'] ?? 0);
        if ($amount <= 0 || $amount < $min || ($max > 0 && $amount > $max)) {
            throw new DomainRuleException('gift_card_amount', __('The card value is outside the accepted range.'));
        }

        $dailyLimit = (int) ($rules['daily_limit'] ?? 0);
        if ($dailyLimit > 0) {
            $today = GiftCardSubmission::query()
                ->whereHas('proof', fn ($q) => $q->where('user_id', $customer->id))
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('amount');
            if ($today + $amount > $dailyLimit) {
                throw new DomainRuleException('gift_card_daily_limit', __('The daily gift card limit has been reached.'));
            }
        }

        return [
            'brand' => $brand,
            'code' => $code,
            'code_last4' => substr($code, -4),
            'pin' => filled($card['pin'] ?? null) ? (string) $card['pin'] : null,
            'amount' => $amount,
            'currency' => $currency,
        ];
    }
}
