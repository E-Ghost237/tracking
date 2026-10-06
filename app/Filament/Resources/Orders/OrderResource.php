<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Order;
use App\Services\Payments\RefundService;
use App\Support\Money;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Orders: price breakdown, payments, invoices, refunds and credit notes (FR-123).
 * Statuses only change through the payment services, never by editing.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Order')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('number'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                TextEntry::make('payment_reference')->fontFamily('mono')->copyable(),
                TextEntry::make('subtotal')->formatStateUsing(fn (Order $record) => Money::format($record->subtotal, $record->currency, 'en')),
                TextEntry::make('fee')->formatStateUsing(fn (Order $record) => Money::format($record->fee, $record->currency, 'en')),
                TextEntry::make('total')->weight('bold')->formatStateUsing(fn (Order $record) => Money::format($record->total, $record->currency, 'en')),
                TextEntry::make('amount_paid')->formatStateUsing(fn (Order $record) => Money::format($record->amount_paid, $record->currency, 'en')),
                TextEntry::make('credit')->formatStateUsing(fn (Order $record) => Money::format($record->credit, $record->currency, 'en')),
                TextEntry::make('receipt_number')->placeholder('—'),
                TextEntry::make('expires_at')->dateTime(),
                TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                TextEntry::make('is_escalated')->label('Escalated')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No')->color(fn (bool $state) => $state ? 'danger' : 'gray'),
            ]),
            Section::make('Customer')->columnSpan(1)->schema([
                TextEntry::make('user.name'),
                TextEntry::make('user.email')->copyable(),
                TextEntry::make('shipment.tracking_number')->label('Tracking number')->placeholder('Not released'),
            ]),
            Section::make('Payment attempts')->columnSpanFull()->schema([
                RepeatableEntry::make('payments')->columns(5)->schema([
                    TextEntry::make('method.name')->label('Method'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('amount_expected')->formatStateUsing(fn ($record) => Money::format($record->amount_expected, $record->currency, 'en')),
                    TextEntry::make('amount_received')->formatStateUsing(fn ($record) => Money::format($record->amount_received, $record->currency, 'en')),
                    TextEntry::make('selected_at')->dateTime(),
                ]),
            ]),
            Section::make('Invoices and credit notes')->columnSpan(2)->schema([
                RepeatableEntry::make('invoices')->columns(3)->schema([
                    TextEntry::make('number'),
                    TextEntry::make('type')->badge(),
                    TextEntry::make('amount')->formatStateUsing(fn ($record) => Money::format($record->amount, $record->currency, 'en')),
                ]),
            ]),
            Section::make('Refunds')->columnSpan(1)->schema([
                RepeatableEntry::make('refunds')->schema([
                    TextEntry::make('amount')->formatStateUsing(fn ($record) => Money::format($record->amount, $record->currency, 'en').' via '.$record->method),
                    TextEntry::make('reason'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'shipment']))
            ->columns([
                TextColumn::make('number')->searchable()->weight('bold'),
                TextColumn::make('payment_reference')->fontFamily('mono')->searchable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('total')->formatStateUsing(fn (Order $record) => Money::format($record->total, $record->currency, 'en'))->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('shipment.tracking_number')->label('Tracking')->fontFamily('mono')->placeholder('—'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(OrderStatus::options())->multiple()])
            ->recordActions([ViewAction::make()]);
    }

    public static function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Record refund')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('warning')
            ->visible(fn (Order $record) => auth()->user()->hasPermission(Permissions::ORDERS_REFUND)
                && in_array($record->status, [OrderStatus::Paid, OrderStatus::Cancelled, OrderStatus::PartiallyPaid], true))
            ->schema([
                TextInput::make('amount')->label('Amount (USD)')->numeric()->required()->minValue(0.01),
                TextInput::make('method')->required()->maxLength(60)->placeholder('e.g. Zelle, IBAN transfer'),
                TextInput::make('reference')->maxLength(120),
                Textarea::make('reason')->required()->maxLength(1000),
            ])
            ->action(function (Order $record, array $data, RefundService $refunds): void {
                try {
                    $refunds->refund($record, auth()->user(), Money::fromMajor($data['amount']), $data['method'], $data['reason'], $data['reference'] ?? null);
                    Notification::make()->success()->title('Refund recorded and credit note issued.')->send();
                } catch (DomainRuleException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'payment_reference'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
