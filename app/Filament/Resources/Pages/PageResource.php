<?php

namespace App\Filament\Resources\Pages;

use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
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
 * CMS pages with preview before publishing (FR-128). Content is Markdown rendered safely.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    public const SLUGS = ['customs', 'packing', 'about', 'terms', 'privacy', 'cookies', 'shipping-policy', 'claims-policy', 'prohibited-items', 'refund-policy', 'payment-terms'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('slug')->options(array_combine(self::SLUGS, self::SLUGS))->required(),
            Select::make('locale')->options(['en' => 'English', 'fr' => 'Français'])->required(),
            Toggle::make('is_published'),
            TextInput::make('title')->required()->maxLength(160)->columnSpan(2),
            DateTimePicker::make('published_at'),
            TextInput::make('summary')->maxLength(300)->columnSpanFull(),
            MarkdownEditor::make('body')->required()->columnSpanFull()->maxLength(100000)
                ->disableToolbarButtons(['attachFiles']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->badge()->searchable(),
                TextColumn::make('locale')->badge(),
                TextColumn::make('title')->searchable(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('updated_at')->since(),
            ])
            ->filters([SelectFilter::make('locale')->options(['en' => 'English', 'fr' => 'Français'])])
            ->recordActions([
                EditAction::make(),
                Action::make('preview')->icon(Heroicon::OutlinedEye)->url(fn (Page $record) => route('pages.preview', $record->id))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Pages\Pages\ListPages::route('/'),
            'create' => \App\Filament\Resources\Pages\Pages\CreatePage::route('/create'),
            'edit' => \App\Filament\Resources\Pages\Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
