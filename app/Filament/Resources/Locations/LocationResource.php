<?php

namespace App\Filament\Resources\Locations;

use App\Models\Location;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hubs and drop-off points shown on the locations page and the globe.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('type')->options(['hub' => 'Hub', 'dropoff' => 'Drop-off point', 'pickup' => 'Pickup point'])->required(),
            Toggle::make('is_active')->default(true),
            TextInput::make('line1')->label('Address')->maxLength(200),
            TextInput::make('city')->required()->maxLength(120),
            TextInput::make('country')->required()->length(2)->alpha()->dehydrateStateUsing(fn (string $state) => strtoupper($state)),
            TextInput::make('lat')->numeric()->required()->minValue(-90)->maxValue(90),
            TextInput::make('lon')->numeric()->required()->minValue(-180)->maxValue(180),
            TextInput::make('phone')->maxLength(40),
            TagsInput::make('opening_hours')->columnSpan(2),
            TagsInput::make('modes')->suggestions(['air', 'sea', 'road']),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('city')->searchable(),
                TextColumn::make('country'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLocations::route('/'), 'create' => Pages\CreateLocation::route('/create'), 'edit' => Pages\EditLocation::route('/{record}/edit')];
    }
}
