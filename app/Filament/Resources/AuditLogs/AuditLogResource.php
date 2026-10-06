<?php

namespace App\Filament\Resources\AuditLogs;

use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Append-only audit log, searchable by user, action, object and date (FR-130).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('user.email')->label('User')->placeholder('system'),
            TextEntry::make('action')->badge(),
            TextEntry::make('object')->state(fn (AuditLog $record) => $record->object_type.' '.$record->object_id),
            TextEntry::make('ip'),
            TextEntry::make('user_agent')->limit(120),
            CodeEntry::make('before')->state(fn (AuditLog $record) => json_encode($record->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
            CodeEntry::make('after')->state(fn (AuditLog $record) => json_encode($record->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.email')->label('User')->searchable()->placeholder('system'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('object_type')->label('Object')->searchable(),
                TextColumn::make('object_id')->fontFamily('mono')->searchable()->limit(28),
                TextColumn::make('ip')->fontFamily('mono'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('object_type')->options(fn () => AuditLog::query()->distinct()->orderBy('object_type')->pluck('object_type', 'object_type')->filter()->all()),
                SelectFilter::make('user_id')->label('User')->relationship('user', 'email')->searchable(),
                Filter::make('date')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/'), 'view' => Pages\ViewAuditLog::route('/{record}')];
    }
}
