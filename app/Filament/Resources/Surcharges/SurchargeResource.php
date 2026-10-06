<?php

namespace App\Filament\Resources\Surcharges;

use App\Models\Surcharge;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * Fuel, insurance and handling surcharges (FR-22, FR-125).
 */
class SurchargeResource extends Resource
{
    protected static ?string $model = Surcharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->required()->maxLength(30)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(80),
            Select::make('type')->options(['percent' => 'Percent', 'fixed' => 'Fixed amount (USD)'])->required(),
            TextInput::make('value')->numeric()->required()->minValue(0)->maxValue(100000),
            Select::make('basis')->options(['freight' => 'Freight price', 'declared_value' => 'Declared value (insurance)'])->required(),
            Select::make('applies_to')->options(['all' => 'All modes', 'air' => 'Air', 'sea' => 'Sea', 'road' => 'Road', 'express' => 'Express'])->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->badge(),
                TextColumn::make('name'),
                TextColumn::make('type'),
                TextColumn::make('value')->numeric(2),
                TextColumn::make('basis'),
                TextColumn::make('applies_to'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurcharges::route('/'),
            'create' => Pages\CreateSurcharge::route('/create'),
            'edit' => Pages\EditSurcharge::route('/{record}/edit'),
        ];
    }
}
