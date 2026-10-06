<?php

namespace App\Filament\Resources\Roles;

use App\Models\Role;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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
 * Roles are data, so new roles can be added without code changes (section 2.1).
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(60),
            TextInput::make('slug')->required()->alphaDash()->maxLength(60)->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record !== null && in_array($record->slug, ['admin', 'customer'], true))->dehydrated(),
            TextInput::make('description')->maxLength(255)->columnSpanFull(),
            Toggle::make('is_staff')->label('Staff role (back-office, 2FA required)'),
            CheckboxList::make('permissions')->relationship('permissions', 'name')->columns(3)->columnSpanFull()->bulkToggleable()
                ->disabled(fn (?Role $record) => $record?->slug === 'admin')
                ->helperText('The Admin role always keeps every permission.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('slug')->badge(),
                IconColumn::make('is_staff')->boolean(),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
                TextColumn::make('users_count')->counts('users')->label('Users'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
