<?php

namespace App\Services\Support;

use App\Exceptions\DomainRuleException;
use App\Jobs\ScanStoredFile;
use App\Models\Claim;
use App\Models\ClaimLog;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\FileStorageService;
use App\Services\Notifications\NotificationService;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Claims for lost, damaged or delayed shipments with evidence and a decision log (FR-108).
 */
class ClaimService
{
    public function __construct(
        private readonly FileStorageService $files,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function open(User $customer, array $data, array $photos): Claim
    {
        $shipment = Shipment::query()->whereBelongsTo($customer)->where('public_id', $data['shipment_id'])->whereNotNull('released_at')->first();
        if ($shipment === null) {
            throw new DomainRuleException('shipment_not_found', __('Choose one of your released shipments.'), 404);
        }
        if (Claim::query()->where('shipment_id', $shipment->id)->whereIn('status', ['open', 'under_review'])->exists()) {
            throw new DomainRuleException('claim_exists', __('A claim is already open for this shipment.'), 409);
        }

        $attachments = [];
        foreach ($photos as $photo) {
            $stored = $this->files->storeUpload($photo, 'claims', $customer, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 8192);
            ScanStoredFile::dispatch($stored->id)->afterCommit();
            $attachments[] = $stored->public_id;
        }

        $claim = DB::transaction(function () use ($customer, $shipment, $data, $attachments): Claim {
            $claim = new Claim([
                'type' => $data['type'],
                'description' => $data['description'],
                'amount_claimed' => Money::fromMajor($data['amount_claimed'] ?? 0),
                'currency' => 'USD',
            ]);
            $claim->forceFill(['shipment_id' => $shipment->id, 'user_id' => $customer->id, 'status' => 'open', 'attachments' => $attachments])->save();
            $claim->logs()->save(new ClaimLog(['user_id' => $customer->id, 'action' => 'opened', 'note' => null]));

            return $claim;
        });

        $locale = $customer->preferredLocale();
        $this->notifications->send('claim.update', $customer, [
            'tracking_number' => (string) $shipment->tracking_number,
            'status' => __('Open', [], $locale),
            'message' => __('We received your claim and will review it.', [], $locale),
        ]);
        $this->notifications->notifyStaff('admin.claim_opened', Permissions::CLAIMS_MANAGE, ['tracking_number' => (string) $shipment->tracking_number, 'type' => $claim->type]);

        return $claim;
    }

    public function decide(Claim $claim, User $staff, string $status, string $decision, ?int $amountApproved): void
    {
        if (! in_array($status, ['under_review', 'approved', 'rejected', 'paid'], true)) {
            throw new DomainRuleException('invalid_status', __('Invalid claim status.'));
        }

        DB::transaction(function () use ($claim, $staff, $status, $decision, $amountApproved): void {
            $before = $claim->only(['status', 'decision', 'amount_approved']);
            $claim->forceFill([
                'status' => $status,
                'decision' => $decision,
                'amount_approved' => $amountApproved,
                'decided_by' => $staff->id,
                'decided_at' => in_array($status, ['approved', 'rejected'], true) ? now() : $claim->decided_at,
            ])->save();
            $claim->logs()->save(new ClaimLog(['user_id' => $staff->id, 'action' => $status, 'note' => $decision]));
            $this->audit->log('claim.decided', $claim, $before, $claim->only(['status', 'decision', 'amount_approved']), $staff);
        });

        $claim->loadMissing(['user', 'shipment']);
        $locale = $claim->user->preferredLocale();
        $this->notifications->send('claim.update', $claim->user, [
            'tracking_number' => (string) $claim->shipment->tracking_number,
            'status' => __(Claim::STATUSES[$status], [], $locale),
            'message' => $decision,
        ]);
    }
}
