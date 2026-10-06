<?php

namespace App\Filament\Resources\Faqs;

use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?int $navigationSort = 20;

    public const CATEGORIES = ['tracking' => 'Tracking', 'payments' => 'Payments', 'shipping' => 'Shipping', 'customs' => 'Customs', 'account' => 'Account'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('locale')->options(['en' => 'English', 'fr' => 'Français'])->required(),
            Select::make('category')->options(self::CATEGORIES)->required(),
            TextInput::make('sort_order')->numeric()->default(0),
            TextInput::make('question')->required()->maxLength(255)->columnSpanFull(),
            MarkdownEditor::make('answer')->required()->maxLength(5000)->columnSpanFull()->disableToolbarButtons(['attachFiles']),
            Toggle::make('is_published')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->searchable()->limit(70),
                TextColumn::make('category')->badge(),
                TextColumn::make('locale')->badge(),
                IconColumn::make('is_published')->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([SelectFilter::make('locale')->options(['en' => 'English', 'fr' => 'Français']), SelectFilter::make('category')->options(self::CATEGORIES)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFaqs::route('/'), 'create' => Pages\CreateFaq::route('/create'), 'edit' => Pages\EditFaq::route('/{record}/edit')];
    }
}
