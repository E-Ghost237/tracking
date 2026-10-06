<?php

namespace App\Filament\Resources\Media;

use App\Models\Media;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hero videos and posters per scene (FR-01, FR-05, FR-128). Videos are MP4 or WebM under 5 MB.
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Hero media';

    protected static ?int $navigationSort = 50;

    /**
     * @return array<string, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (['air', 'sea', 'road'] as $scene) {
            $keys['hero_'.$scene.'_mp4'] = ucfirst($scene).' video (MP4 H.264)';
            $keys['hero_'.$scene.'_webm'] = ucfirst($scene).' video (WebM)';
            $keys['hero_'.$scene.'_poster'] = ucfirst($scene).' poster image';
        }

        return $keys;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('key')->options(self::keys())->required()->unique(ignoreRecord: true)->live(),
            FileUpload::make('path')->label('File')->disk('public')->directory('hero')->visibility('public')->required()
                ->acceptedFileTypes(fn (Get $get) => str_ends_with((string) $get('key'), '_poster') ? ['image/jpeg', 'image/webp', 'image/avif'] : (str_ends_with((string) $get('key'), '_webm') ? ['video/webm'] : ['video/mp4']))
                ->maxSize(5120),
            TextInput::make('alt_en')->label('Description (EN)')->maxLength(200),
            TextInput::make('alt_fr')->label('Description (FR)')->maxLength(200),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->formatStateUsing(fn (string $state) => self::keys()[$state] ?? $state),
                TextColumn::make('path'),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMedia::route('/'), 'create' => Pages\CreateMedia::route('/create'), 'edit' => Pages\EditMedia::route('/{record}/edit')];
    }
}
