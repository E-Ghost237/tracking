<?php

namespace App\Filament\Resources\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Users and roles (FR-127): staff creation, role assignment, forced password reset,
 * login history, disabling. Role changes require the roles permission.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Customers and support';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        $canAssignRoles = fn () => auth()->user()->hasPermission(Permissions::ROLES_MANAGE);

        return $schema->columns(2)->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->maxLength(190)->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => Str::lower(trim($state))),
                TextInput::make('phone')->maxLength(30),
                Select::make('locale')->options(['en' => 'English', 'fr' => 'Français'])->required()->default('en'),
                Select::make('status')->options(['active' => 'Active', 'disabled' => 'Disabled'])->required()->default('active'),
            ]),
            Section::make('Roles')->visible($canAssignRoles)->schema([
                CheckboxList::make('roles')->relationship('roles', 'name')->columns(3)
                    ->helperText('Staff roles require two-factor authentication before the back-office opens.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('locale'),
                TextEntry::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextEntry::make('roles.name')->badge(),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('last_login_at')->dateTime()->placeholder('Never'),
            ]),
            Section::make('Security')->columnSpan(1)->schema([
                IconEntry::make('email_verified_at')->label('Email verified')->boolean()->state(fn (User $record) => $record->email_verified_at !== null),
                IconEntry::make('two_factor')->label('Two-factor authentication')->boolean()->state(fn (User $record) => $record->hasTwoFactorEnabled()),
                TextEntry::make('locked_until')->dateTime()->placeholder('Not locked'),
                TextEntry::make('deletion_requested_at')->label('Deletion requested')->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean()->state(fn (User $record) => $record->two_factor_confirmed_at !== null),
                TextColumn::make('last_login_at')->since()->sortable()->placeholder('Never'),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name'),
                SelectFilter::make('status')->options(['active' => 'Active', 'disabled' => 'Disabled']),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /**
     * @return array<int, Action>
     */
    public static function securityActions(): array
    {
        $manage = fn (User $record) => auth()->user()->hasPermission(Permissions::USERS_MANAGE) && ! $record->is(auth()->user());

        return [
            Action::make('forceReset')->label('Force password reset')->icon(Heroicon::OutlinedKey)->color('warning')
                ->visible($manage)->requiresConfirmation()
                ->modalDescription('The current password stops working immediately and a reset link is emailed to the user.')
                ->action(function (User $record, AuditLogger $audit): void {
                    $record->forceFill(['password' => Str::password(40), 'remember_token' => Str::random(60)])->save();
                    Password::sendResetLink(['email' => $record->email]);
                    $audit->log('user.password_reset_forced', $record);
                    Notification::make()->success()->title('Password reset link sent.')->send();
                }),
            Action::make('unlock')->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (User $record) => $manage($record) && $record->isLocked())
                ->action(function (User $record, AuditLogger $audit): void {
                    $record->forceFill(['locked_until' => null, 'failed_logins' => 0])->save();
                    $audit->log('user.unlocked', $record);
                    Notification::make()->success()->title('Account unlocked.')->send();
                }),
            Action::make('reset2fa')->label('Reset two-factor')->icon(Heroicon::OutlinedDevicePhoneMobile)->color('danger')
                ->visible(fn (User $record) => $manage($record) && $record->hasTwoFactorEnabled())
                ->requiresConfirmation()
                ->modalDescription('Use only after verifying the user\'s identity. They will need to set up 2FA again.')
                ->action(function (User $record, AuditLogger $audit): void {
                    $record->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
                    $audit->log('user.2fa_reset', $record);
                    Notification::make()->success()->title('Two-factor authentication reset.')->send();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\LoginAttemptsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
