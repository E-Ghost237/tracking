<?php

namespace App\Filament\Resources\PaymentProofs;

use App\Enums\ProofStatus;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Payment proof review queue (FR-70): oldest first, filters for method, amount, status,
 * customer and flags. Proofs older than the alert threshold are shown in red (FR-74).
 */
class PaymentProofResource extends Resource
{
    protected static ?string $model = PaymentProof::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Proofs queue';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $count = PaymentProof::query()->whereIn('status', [ProofStatus::UnderReview->value, ProofStatus::FirstApproved->value])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $oldest = PaymentProof::query()->whereIn('status', [ProofStatus::UnderReview->value, ProofStatus::FirstApproved->value])->min('submitted_at');

        return $oldest && now()->diffInMinutes($oldest, true) > (int) config('platform.settings.review_alert_minutes', 60) ? 'danger' : 'warning';
    }

    public static function table(Table $table): Table
    {
        $alert = (int) app(\App\Services\Settings::class)->get('review_alert_minutes', 60);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.user', 'orderPayment.method', 'user']))
            ->columns([
                TextColumn::make('submitted_at')->label('Age')->since()->sortable()
                    ->color(fn (PaymentProof $record) => $record->status->isPending() && $record->ageInMinutes() > $alert ? 'danger' : null)
                    ->weight(fn (PaymentProof $record) => $record->status->isPending() && $record->ageInMinutes() > $alert ? 'bold' : null),
                TextColumn::make('order.number')->label('Order')->searchable(),
                TextColumn::make('order.payment_reference')->label('Reference')->fontFamily('mono')->searchable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('orderPayment.method.name')->label('Method')->badge(),
                TextColumn::make('amount_paid')->label('Declared')->formatStateUsing(fn (PaymentProof $record) => Money::format($record->amount_paid, $record->currency, 'en')),
                TextColumn::make('orderPayment.amount_expected')->label('Due')->formatStateUsing(fn (PaymentProof $record) => Money::format($record->orderPayment->amount_expected, $record->orderPayment->currency, 'en')),
                IconColumn::make('is_duplicate')->label('Duplicate')->boolean()->trueIcon(Heroicon::OutlinedExclamationTriangle)->trueColor('danger')->falseIcon(null),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ProofStatus $state) => $state->label())->color(fn (ProofStatus $state) => $state->color()),
            ])
            ->defaultSort('submitted_at', 'asc')
            ->filters([
                SelectFilter::make('status')->options(collect(ProofStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all())
                    ->default(ProofStatus::UnderReview->value),
                SelectFilter::make('method')->label('Method')
                    ->options(fn () => PaymentMethod::withTrashed()->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('orderPayment', fn ($q) => $q->where('payment_method_id', $data['value'])) : $query),
                TernaryFilter::make('is_duplicate')->label('Duplicate flag'),
                Filter::make('large')->label('Above two-person threshold')
                    ->query(fn (Builder $query) => $query->whereHas('order', fn ($q) => $q->where('total', '>=', (int) app(\App\Services\Settings::class)->get('two_person_threshold')))),
                Filter::make('overdue')->label('Older than alert threshold')
                    ->query(fn (Builder $query) => $query->where('submitted_at', '<', now()->subMinutes($alert))),
            ])
            ->recordUrl(fn (PaymentProof $record) => static::getUrl('review', ['record' => $record]))
            ->recordActions([
                Action::make('review')->label('Review')->icon(Heroicon::OutlinedEye)
                    ->url(fn (PaymentProof $record) => static::getUrl('review', ['record' => $record])),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentProofs::route('/'),
            'review' => Pages\ReviewPaymentProof::route('/{record}/review'),
        ];
    }
}
