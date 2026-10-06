<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Models\PaymentMethod;
use App\Models\PaymentMethodField;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Manual payment methods configured entirely in the back-office (section 5.2).
 * Account details are encrypted fields; changes are audited and announced to all admins.
 */
class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Method')->columns(3)->schema([
                    TextInput::make('name')->required()->maxLength(60),
                    TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('kind')->options(PaymentMethod::KINDS)->required()->live(),
                    Toggle::make('is_enabled')->label('Enabled for new orders')
                        ->helperText('Check the provider terms for business use before enabling (risk R2).'),
                    Select::make('currency')->options(['USD' => 'USD', 'EUR' => 'EUR', 'XAF' => 'XAF', 'GBP' => 'GBP', 'CAD' => 'CAD'])->required(),
                    Select::make('risk_level')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->required(),
                    TextInput::make('min_amount')->label('Minimum order (cents, USD)')->numeric()->minValue(0)->default(0),
                    TextInput::make('max_amount')->label('Maximum order (cents, USD)')->numeric()->minValue(0),
                    TextInput::make('expiry_hours')->label('Payment window (hours, empty = default)')->numeric()->minValue(1)->maxValue(168),
                    TextInput::make('fee_percent')->label('Fee (%)')->numeric()->minValue(0)->maxValue(20)->default(0),
                    TextInput::make('fee_fixed')->label('Fixed fee (cents, USD)')->numeric()->minValue(0)->default(0),
                    TextInput::make('sort_order')->numeric()->default(0),
                    TagsInput::make('countries')->label('Allowed origin countries (empty = all)')->columnSpanFull()->nestedRecursiveRules(['size:2', 'alpha']),
                    Toggle::make('requires_transaction_id')->label('Transaction ID required'),
                    Toggle::make('proof_required')->default(true)->disabled()->dehydrated(),
                ]),
                Tab::make('Account details')->schema([
                    Repeater::make('fields')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->reorderable()
                        ->columns(4)
                        ->itemLabel(fn (array $state): ?string => $state['label_en'] ?? null)
                        ->helperText('Shown to a customer only after they select this method on their own order. Values are encrypted.')
                        ->schema([
                            TextInput::make('label_en')->required()->maxLength(60),
                            TextInput::make('label_fr')->required()->maxLength(60),
                            Select::make('type')->options(PaymentMethodField::TYPES)->required()->default('copy'),
                            TextInput::make('value')->required()->maxLength(1000)->columnSpanFull()
                                ->helperText(fn (?PaymentMethodField $record) => $record?->pending_effective_at ? 'Scheduled change goes live '.$record->pending_effective_at->diffForHumans() : null),
                        ]),
                ]),
                Tab::make('Instructions')->columns(2)->schema([
                    Textarea::make('instructions_en')->rows(6)->maxLength(3000),
                    Textarea::make('instructions_fr')->rows(6)->maxLength(3000),
                ]),
                Tab::make('Gift card rules')->visible(fn (Get $get) => $get('kind') === 'gift_card')->columns(3)->schema([
                    Section::make()->columnSpanFull()->description('Gift cards are the payment method most associated with fraud (risk R3). Keep them disabled unless the client decides otherwise.')->schema([]),
                    TagsInput::make('gift_card_rules.brands')->label('Accepted brands')->columnSpanFull(),
                    TextInput::make('gift_card_rules.per_card_min')->label('Min per card (cents)')->numeric()->minValue(0),
                    TextInput::make('gift_card_rules.per_card_max')->label('Max per card (cents)')->numeric()->minValue(0),
                    TextInput::make('gift_card_rules.daily_limit')->label('Daily limit per customer (cents)')->numeric()->minValue(0),
                    TextInput::make('gift_card_rules.min_account_age_days')->label('Minimum account age (days)')->numeric()->minValue(0)->default(30),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('currency'),
                TextColumn::make('fee_percent')->suffix('%'),
                TextColumn::make('risk_level')->badge()->color(fn (string $state) => match ($state) {
                    'high' => 'danger',
                    'medium' => 'warning',
                    default => 'success',
                }),
                TextColumn::make('fields_count')->counts('fields')->label('Details'),
                ToggleColumn::make('is_enabled')->label('Enabled'),
                IconColumn::make('requires_transaction_id')->boolean()->label('Tx ID'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
