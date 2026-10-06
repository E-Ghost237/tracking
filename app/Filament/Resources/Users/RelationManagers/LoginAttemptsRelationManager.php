<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginAttempts';

    protected static ?string $title = 'Login history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                IconColumn::make('success')->boolean(),
                TextColumn::make('reason')->placeholder('—'),
                TextColumn::make('ip')->fontFamily('mono'),
                TextColumn::make('user_agent')->limit(60)->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
