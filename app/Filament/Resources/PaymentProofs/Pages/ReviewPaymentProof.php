<?php

namespace App\Filament\Resources\PaymentProofs\Pages;

use App\Enums\ProofStatus;
use App\Enums\ReviewDecision;
use App\Exceptions\DomainRuleException;
use App\Filament\Resources\PaymentProofs\PaymentProofResource;
use App\Models\PaymentProof;
use App\Services\AuditLogger;
use App\Services\Files\FileStorageService;
use App\Services\Payments\ProofReviewService;
use App\Services\ProofOverview;
use App\Support\Money;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Side-by-side review (FR-71): the proof, the order and a checklist. Decisions go through
 * ProofReviewService, which enforces the two-person rule and conflict-of-interest checks.
 */
class ReviewPaymentProof extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PaymentProofResource::class;

    protected string $view = 'filament.review-payment-proof';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(auth()->user()?->hasPermission(Permissions::PROOFS_VIEW), 403);
    }

    public function getTitle(): string
    {
        return 'Review proof · '.$this->proof()->order->number;
    }

    public function proof(): PaymentProof
    {
        /** @var PaymentProof $proof */
        $proof = $this->record;

        return $proof->loadMissing(['order.user', 'order.shipment', 'order.proofs', 'orderPayment.method', 'files', 'reviews.reviewer', 'giftCard']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $proof = $this->proof();
        $files = app(FileStorageService::class);

        return [
            'proof' => $proof,
            'overview' => app(ProofOverview::class)->for($proof),
            'fileLinks' => $proof->files->map(fn ($file) => [
                'name' => $file->original_name,
                'mime' => $file->mime,
                'scan' => $file->scan_status,
                'url' => $file->scan_status === 'infected' ? null : $files->temporaryUrl($file),
                'thumb' => $file->thumbnail_path ? $files->temporaryUrl($file, thumbnail: true) : null,
                'sha' => substr($file->sha256, 0, 16),
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $canReview = fn () => auth()->user()->hasPermission(Permissions::PROOFS_REVIEW)
            && in_array($this->proof()->status, [ProofStatus::UnderReview, ProofStatus::FirstApproved], true);

        return [
            Action::make('approve')
                ->label(fn () => $this->proof()->status === ProofStatus::FirstApproved ? 'Give second approval' : 'Approve')
                ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible($canReview)
                ->modalDescription('Approving releases the label, tracking number and invoice to the customer.')
                ->schema([
                    CheckboxList::make('checklist')->label('Checklist')->required()->options(self::checklist())
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            $missing = array_diff(['amount_matches', 'reference_present', 'date_after_order', 'payer_plausible', 'not_duplicate'], (array) $value);
                            if ($missing !== []) {
                                $fail('All mandatory checks must be ticked before approval.');
                            }
                        }),
                    TextInput::make('amount_received')->label(fn () => 'Amount actually received ('.$this->proof()->orderPayment->currency.')')
                        ->numeric()->required()->minValue(0.01)
                        ->default(fn () => Money::toMajor($this->proof()->amount_paid, $this->proof()->currency))
                        ->helperText('Less than the amount due records a partial payment and asks the customer for the balance.'),
                    Textarea::make('note')->maxLength(1000),
                ])
                ->action(function (array $data, ProofReviewService $reviews): void {
                    $this->decide(fn () => $reviews->approve(
                        $this->proof(),
                        auth()->user(),
                        array_fill_keys((array) $data['checklist'], true),
                        $data['note'] ?? null,
                        Money::fromMajor($data['amount_received'], $this->proof()->orderPayment->currency),
                    ), fn ($result) => match ($result) {
                        ProofReviewService::RESULT_FIRST_APPROVAL => 'First approval recorded. A different staff member must give the second approval.',
                        ProofReviewService::RESULT_PARTIAL => 'Partial payment recorded. The customer was asked for the balance.',
                        default => 'Payment approved. Label and tracking number released.',
                    });
                }),

            Action::make('reject')
                ->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible($canReview)
                ->schema([
                    Select::make('reason')->options(ReviewDecision::rejectReasons())->required(),
                    Textarea::make('note')->label('Message to the customer')->maxLength(1000),
                ])
                ->action(function (array $data, ProofReviewService $reviews): void {
                    $this->decide(fn () => $reviews->reject($this->proof(), auth()->user(), $data['reason'], $data['note'] ?? null), fn () => 'Proof rejected. The customer was emailed the reason.');
                }),

            Action::make('requestInfo')->label('Request more information')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('warning')
                ->visible($canReview)
                ->schema([Textarea::make('message')->required()->minLength(5)->maxLength(1000)])
                ->action(function (array $data, ProofReviewService $reviews): void {
                    $this->decide(fn () => $reviews->requestInfo($this->proof(), auth()->user(), $data['message']), fn () => 'Request sent to the customer.');
                }),

            Action::make('revealGiftCard')->label('Reveal card code')
                ->icon(Heroicon::OutlinedEye)->color('gray')
                ->visible(fn () => $this->proof()->giftCard !== null && auth()->user()->hasPermission(Permissions::GIFT_CARDS_REVEAL))
                ->requiresConfirmation()
                ->modalDescription('This reveal is written to the audit log.')
                ->action(function (AuditLogger $audit): void {
                    $card = $this->proof()->giftCard;
                    $audit->log('gift_card.revealed', $this->proof(), null, ['brand' => $card->brand, 'last4' => $card->code_last4]);
                    Notification::make()->title($card->brand.' card')
                        ->body(new HtmlString('Code: <code>'.e($card->code).'</code>'.($card->pin ? '<br>PIN: <code>'.e($card->pin).'</code>' : '')))
                        ->persistent()->warning()->send();
                }),

            Action::make('correct')->label('Record correction')
                ->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn () => auth()->user()->hasPermission(Permissions::PROOFS_CORRECT) && $this->proof()->status === ProofStatus::Rejected)
                ->modalDescription('Reverses this rejection and approves the payment. The original decision stays in the history.')
                ->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(1000)])
                ->action(function (array $data, ProofReviewService $reviews): void {
                    $this->decide(fn () => $reviews->correctRejection($this->proof(), auth()->user(), $data['reason']), fn () => 'Correction recorded and payment approved.');
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checklist(): array
    {
        return [
            'amount_matches' => 'Amount matches the amount due',
            'reference_present' => 'Payment reference is present',
            'date_after_order' => 'Payment date is after the order',
            'payer_plausible' => 'Payer name is plausible',
            'not_duplicate' => 'Proof is not a duplicate',
            'confirmed_in_account' => 'Confirmed in our bank or app statement (recommended)',
        ];
    }

    private function decide(callable $operation, callable $message): void
    {
        try {
            $result = $operation();
            Notification::make()->success()->title($message($result))->send();
            $this->record = $this->record->fresh();
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
