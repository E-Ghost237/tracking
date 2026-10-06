<?php

namespace App\Filament\Resources\RateCards;

use App\Models\RateCard;
use App\Services\Pricing\RateCardCsv;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
 * Versioned rate cards: zone pair base fees, weight bands and per-kg prices (FR-125).
 * A new version applies to new quotes only (BR-02).
 */
class RateCardResource extends Resource
{
    protected static ?string $model = RateCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?string $navigationLabel = 'Rate cards';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('version')->numeric()->required()->unique(ignoreRecord: true)
                ->default(fn () => (int) RateCard::query()->max('version') + 1),
            TextInput::make('name')->required()->maxLength(120),
            DateTimePicker::make('valid_from'),
            DateTimePicker::make('valid_to'),
            Toggle::make('is_active')->helperText('The active card with the highest version is used for new quotes.'),
            Textarea::make('notes')->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')->badge()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                TextColumn::make('valid_from')->dateTime(),
                TextColumn::make('valid_to')->dateTime(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('version', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (RateCard $record, RateCardCsv $csv) => response()->streamDownload(
                        fn () => print ($csv->export($record)),
                        'rate-card-v'.$record->version.'.csv',
                        ['Content-Type' => 'text/csv'],
                    )),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRateCards::route('/'),
            'create' => Pages\CreateRateCard::route('/create'),
            'edit' => Pages\EditRateCard::route('/{record}/edit'),
        ];
    }
}
