<?php

namespace App\Filament\Resources\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Services\Files\FileStorageService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Shipments (FR-121): search by number, name, email or reference; edit details;
 * add events through the event service; proof of delivery; documents.
 */
class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'tracking_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Last-mile partner')->description('Carrier number used by USPS, UPS or FedEx for the final leg, if any.')->columns(2)->schema([
                Select::make('partner_carrier_id')->label('Partner carrier')
                    ->options(fn () => Carrier::query()->where('is_own', false)->pluck('name', 'id')->all()),
                TextInput::make('partner_tracking_number')->maxLength(40)->regex('/^[A-Za-z0-9]+$/'),
            ]),
            Section::make('Delivery')->columns(2)->schema([
                DateTimePicker::make('eta_at')->label('Estimated delivery'),
                TextInput::make('service')->maxLength(40)->required(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Shipment')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('tracking_number')->fontFamily('mono')->copyable()->placeholder('Not released'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (ShipmentStatus $state) => $state->label())->color(fn (ShipmentStatus $state) => $state->color()),
                TextEntry::make('service'),
                TextEntry::make('origin')->formatStateUsing(fn (Shipment $record) => trim(($record->origin['line1'] ?? '').', '.$record->originLabel(), ', ')),
                TextEntry::make('destination')->formatStateUsing(fn (Shipment $record) => trim(($record->destination['line1'] ?? '').', '.$record->destinationLabel(), ', ')),
                TextEntry::make('eta_at')->dateTime()->label('ETA'),
                TextEntry::make('weight_g')->label('Weight')->formatStateUsing(fn ($state) => number_format($state / 1000, 2).' kg'),
                TextEntry::make('chargeable_weight_g')->label('Chargeable')->formatStateUsing(fn ($state) => number_format($state / 1000, 2).' kg'),
                TextEntry::make('partnerCarrier.name')->label('Partner')->placeholder('—')
                    ->formatStateUsing(fn (Shipment $record) => trim(($record->partnerCarrier?->name ?? '').' '.$record->partner_tracking_number)),
            ]),
            Section::make('Parties')->columnSpan(1)->schema([
                TextEntry::make('sender')->formatStateUsing(fn (Shipment $record) => ($record->sender['name'] ?? '').' · '.($record->sender['phone'] ?? '')),
                TextEntry::make('recipient')->formatStateUsing(fn (Shipment $record) => ($record->recipient['name'] ?? '').' · '.($record->recipient['phone'] ?? '')),
                TextEntry::make('user.email')->label('Customer account'),
                TextEntry::make('order.number')->label('Order'),
            ]),
            Section::make('Documents')->columnSpanFull()->schema([
                TextEntry::make('documents')->label('')->html()->state(function (Shipment $record): HtmlString {
                    $files = app(FileStorageService::class);
                    $links = $record->documents()->with('file')->get()->map(function ($document) use ($files) {
                        if ($document->file === null) {
                            return '<span class="text-gray-500">'.e($document->type).' (preparing)</span>';
                        }

                        return '<a class="text-primary-600 underline" target="_blank" rel="noopener" href="'.e($files->temporaryUrl($document->file)).'">'.e($document->type).'</a>';
                    });

                    return new HtmlString($links->isEmpty() ? 'No documents yet.' : $links->implode(' · '));
                }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'order', 'carrier']))
            ->columns([
                TextColumn::make('tracking_number')->fontFamily('mono')->weight('bold')->searchable()->placeholder('Unreleased'),
                TextColumn::make('order.payment_reference')->label('Reference')->fontFamily('mono')->searchable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('recipient_name')->label('Recipient')
                    ->state(fn (Shipment $record) => $record->recipient['name'] ?? '')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereRaw("recipient->>'name' ilike ?", ['%'.addcslashes($search, '%_\\').'%'])),
                TextColumn::make('route')->state(fn (Shipment $record) => $record->originLabel().' → '.$record->destinationLabel()),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ShipmentStatus $state) => $state->label())->color(fn (ShipmentStatus $state) => $state->color()),
                TextColumn::make('eta_at')->label('ETA')->date()->sortable()
                    ->color(fn (Shipment $record) => $record->eta_at?->isPast() && ! $record->status->isTerminal() && $record->released_at ? 'danger' : null),
                TextColumn::make('created_at')->since()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ShipmentStatus::options())->multiple(),
                SelectFilter::make('mode')->options(['air' => 'Air', 'sea' => 'Sea', 'road' => 'Road', 'express' => 'Express']),
                TernaryFilter::make('released')->nullable()->attribute('released_at'),
                TernaryFilter::make('late')->label('Late')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('released_at')->where('eta_at', '<', now())->whereNotIn('status', ['delivered', 'returned', 'cancelled']),
                        false: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['tracking_number', 'partner_tracking_number'];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\EventsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'view' => Pages\ViewShipment::route('/{record}'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }
}
