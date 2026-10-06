<?php

namespace App\Filament\Resources\TransportModes;

use App\Models\TransportMode;
use BackedEnum;
use Filament\Actions\EditAction;
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
 * Mode multipliers and volumetric divisors (FR-21, FR-22).
 */
class TransportModeResource extends Resource
{
    protected static ?string $model = TransportMode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?string $navigationLabel = 'Mode multipliers';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->disabled(),
            TextInput::make('name_en')->required()->maxLength(60),
            TextInput::make('name_fr')->required()->maxLength(60),
            TextInput::make('multiplier')->numeric()->required()->minValue(0.1)->maxValue(10),
            TextInput::make('volumetric_divisor')->numeric()->required()->minValue(1000)->maxValue(10000),
            Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->badge(),
                TextColumn::make('name_en'),
                TextColumn::make('multiplier')->numeric(3),
                TextColumn::make('volumetric_divisor'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportModes::route('/'),
            'edit' => Pages\EditTransportMode::route('/{record}/edit'),
        ];
    }
}
