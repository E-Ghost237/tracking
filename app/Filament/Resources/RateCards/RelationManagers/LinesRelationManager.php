<?php

namespace App\Filament\Resources\RateCards\RelationManagers;

use App\Models\Zone;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public function form(Schema $schema): Schema
    {
        $zones = fn () => Zone::query()->pluck('code', 'code')->put('ROW', 'ROW')->all();

        return $schema->columns(3)->components([
            Select::make('zone_from')->options($zones)->required(),
            Select::make('zone_to')->options($zones)->required(),
            Select::make('mode')->options(['air' => 'Air', 'sea' => 'Sea', 'road' => 'Road'])->required(),
            TextInput::make('base_fee')->label('Base fee (cents)')->numeric()->required()->minValue(0)->maxValue(10000000),
            TextInput::make('price_per_kg')->label('Price per kg (cents)')->numeric()->required()->minValue(0)->maxValue(1000000),
            TextInput::make('weight_from_kg')->numeric()->required()->minValue(0),
            TextInput::make('weight_to_kg')->numeric()->required()->gt('weight_from_kg'),
            TextInput::make('transit_min_days')->numeric()->required()->minValue(0),
            TextInput::make('transit_max_days')->numeric()->required()->gte('transit_min_days'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone_from')->badge()->sortable(),
                TextColumn::make('zone_to')->badge()->sortable(),
                TextColumn::make('mode')->sortable(),
                TextColumn::make('weight_from_kg')->label('From kg'),
                TextColumn::make('weight_to_kg')->label('To kg'),
                TextColumn::make('base_fee')->money('USD', divideBy: 100),
                TextColumn::make('price_per_kg')->money('USD', divideBy: 100),
                TextColumn::make('transit_min_days')->label('Days')->formatStateUsing(fn ($state, $record) => $record->transit_min_days.'–'.$record->transit_max_days),
            ])
            ->filters([
                SelectFilter::make('mode')->options(['air' => 'Air', 'sea' => 'Sea', 'road' => 'Road']),
                SelectFilter::make('zone_from')->options(fn () => Zone::query()->pluck('code', 'code')->put('ROW', 'ROW')->all()),
                SelectFilter::make('zone_to')->options(fn () => Zone::query()->pluck('code', 'code')->put('ROW', 'ROW')->all()),
            ])
            ->defaultSort('zone_from')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
