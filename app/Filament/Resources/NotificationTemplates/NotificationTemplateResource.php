<?php

namespace App\Filament\Resources\NotificationTemplates;

use App\Models\NotificationTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Email templates per event and language (FR-110). Variables use {{name}} syntax.
 */
class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Email templates';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('event')->disabled(),
            TextInput::make('locale')->disabled(),
            Toggle::make('is_active'),
            TextInput::make('subject')->required()->maxLength(200)->columnSpanFull(),
            MarkdownEditor::make('body')->required()->maxLength(20000)->columnSpanFull()->disableToolbarButtons(['attachFiles'])
                ->helperText('Variables: {{name}}, {{brand}}, {{order_number}}, {{reference}}, {{tracking_number}}, links ending in _url. Never add payment details or card codes (FR-113).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('locale')->badge(),
                TextColumn::make('subject')->limit(60),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('event')
            ->filters([SelectFilter::make('locale')->options(['en' => 'English', 'fr' => 'Français'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotificationTemplates::route('/'), 'edit' => Pages\EditNotificationTemplate::route('/{record}/edit')];
    }
}
