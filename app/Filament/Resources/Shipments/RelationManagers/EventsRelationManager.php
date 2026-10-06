<?php

namespace App\Filament\Resources\Shipments\RelationManagers;

use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\City;
use App\Services\Shipping\ShipmentEventService;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tracking events. Events are added through ShipmentEventService (source and author recorded,
 * Delivered protected) and are never edited afterwards (risk R6).
 */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ShipmentStatus $state) => $state->label())->color(fn (ShipmentStatus $state) => $state->color()),
                TextColumn::make('label')->wrap(),
                TextColumn::make('place'),
                TextColumn::make('source')->badge(),
                IconColumn::make('is_public')->boolean()->label('Public'),
                TextColumn::make('author.name')->label('By')->placeholder('system'),
                TextColumn::make('note')->toggleable(isToggledHiddenByDefault: true)->wrap(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->headerActions([
                Action::make('addEvent')->label('Add event')->icon(Heroicon::OutlinedPlus)
                    ->visible(fn () => auth()->user()->hasPermission(Permissions::EVENTS_CREATE) && $this->getOwnerRecord()->isReleased())
                    ->schema([
                        Select::make('status')->options(ShipmentStatus::options(ShipmentStatus::eventStatuses()))->required()->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $state ? $set('label', ShipmentStatus::from($state)->label()) : null),
                        TextInput::make('label')->required()->maxLength(250),
                        Select::make('city')->label('Place')->searchable()
                            ->getSearchResultsUsing(fn (string $search) => City::query()
                                ->whereRaw('LOWER(ascii_name) LIKE ?', [addcslashes(mb_strtolower(\Illuminate\Support\Str::ascii($search)), '%_\\').'%'])
                                ->orderByDesc('population')->limit(20)->get()
                                ->mapWithKeys(fn (City $c) => [$c->id => $c->name.', '.$c->country])->all())
                            ->getOptionLabelUsing(fn ($value) => ($c = City::query()->find($value)) ? $c->name.', '.$c->country : null),
                        TextInput::make('place_override')->label('Or free text place')->maxLength(250),
                        DateTimePicker::make('occurred_at')->required()->default(now())->maxDate(now()->addHour()),
                        Toggle::make('is_public')->label('Visible to customer')->default(true),
                        Textarea::make('note')->label('Internal note')->maxLength(1000),
                    ])
                    ->action(function (array $data, ShipmentEventService $events): void {
                        $city = isset($data['city']) ? City::query()->find($data['city']) : null;
                        try {
                            $events->add($this->getOwnerRecord(), [
                                'status' => $data['status'],
                                'label' => $data['label'],
                                'place' => $data['place_override'] ?: ($city ? $city->name.', '.$city->country : null),
                                'lat' => $city?->lat,
                                'lon' => $city?->lon,
                                'occurred_at' => $data['occurred_at'],
                                'is_public' => (bool) $data['is_public'],
                                'note' => $data['note'] ?? null,
                            ], auth()->user());
                            Notification::make()->success()->title('Event added.')->send();
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }
}
