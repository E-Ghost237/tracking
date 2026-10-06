<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => auth()->user()->hasPermission(Permissions::AUDIT_VIEW) && auth()->user()->hasPermission(Permissions::SETTINGS_MANAGE))
                ->schema([DatePicker::make('from')->required()->default(now()->subMonth()), DatePicker::make('until')->required()->default(now())])
                ->action(function (array $data, AuditLogger $audit) {
                    $audit->log('audit_log.exported', 'AuditLog', null, $data);

                    return response()->streamDownload(function () use ($data): void {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['created_at', 'user', 'action', 'object_type', 'object_id', 'ip', 'before', 'after'], escape: '');
                        AuditLog::query()->with('user')
                            ->whereDate('created_at', '>=', $data['from'])->whereDate('created_at', '<=', $data['until'])
                            ->orderBy('id')->lazyById(500)->each(function (AuditLog $log) use ($out): void {
                                $row = [$log->created_at, $log->user?->email, $log->action, $log->object_type, $log->object_id, $log->ip, json_encode($log->before), json_encode($log->after)];
                                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v, $row), escape: '');
                            });
                        fclose($out);
                    }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}
