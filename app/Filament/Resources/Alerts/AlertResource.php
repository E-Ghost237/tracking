<?php

namespace App\Filament\Resources\Alerts;

use App\Models\Alert;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * Service alerts: delays, holidays, weather (section 3.1).
 */
class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Service alerts';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('locale')->options(['en' => 'English', 'fr' => 'Français'])->required(),
            Select::make('severity')->options(['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'])->required(),
            TextInput::make('region')->maxLength(60),
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->required()->rows(4)->maxLength(3000)->columnSpanFull(),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at')->after('starts_at'),
            Toggle::make('is_published'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('severity')->badge()->color(fn (string $state) => ['critical' => 'danger', 'warning' => 'warning'][$state] ?? 'info'),
                TextColumn::make('locale')->badge(),
                TextColumn::make('starts_at')->dateTime(),
                TextColumn::make('ends_at')->dateTime(),
                IconColumn::make('is_published')->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAlerts::route('/'), 'create' => Pages\CreateAlert::route('/create'), 'edit' => Pages\EditAlert::route('/{record}/edit')];
    }
}
