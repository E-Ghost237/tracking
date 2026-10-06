<?php

namespace App\Filament\Resources\Carriers;

use App\Models\Carrier;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Carriers, number formats, tracking URL templates and encrypted API credentials (FR-126).
 */
class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->required()->maxLength(30)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('name')->required()->maxLength(80),
                TagsInput::make('number_patterns')
                    ->label('Number formats (regular expressions, without delimiters)')
                    ->helperText('Matched against the number with spaces and hyphens removed. Example: 1Z[0-9A-Z]{16}')
                    ->required()
                    ->columnSpanFull()
                    ->nestedRecursiveRules(['max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! is_string($value) || @preg_match('/^(?:'.str_replace('/', '\/', $value).')$/', '') === false) {
                            $fail('Invalid regular expression.');
                        }
                    }]),
                TextInput::make('tracking_url_template')->label('Tracking URL template')->url()->maxLength(255)->helperText('Use {number} as placeholder. Must start with https://')
                    ->rule('starts_with:https://'),
                TextInput::make('region')->maxLength(60),
                Toggle::make('is_own')->label('Own freight (our tracking numbers)'),
                Toggle::make('is_active')->default(true),
                TextInput::make('sort_order')->numeric()->default(0),
            ]),
            Section::make('Aggregator or API credentials')->description('Stored encrypted. Leave empty to enter events by hand.')->schema([
                KeyValue::make('api_config')
                    ->formatStateUsing(fn (?Carrier $record) => $record?->api_config ?? [])
                    ->keyLabel('Key')->valueLabel('Value'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->badge()->searchable(),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('number_patterns')->badge()->fontFamily('mono'),
                TextColumn::make('region'),
                IconColumn::make('is_own')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarriers::route('/'),
            'create' => Pages\CreateCarrier::route('/create'),
            'edit' => Pages\EditCarrier::route('/{record}/edit'),
        ];
    }
}
