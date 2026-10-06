<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @var array<int, string>
     */
    private array $rolesBefore = [];

    private ?string $status = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->status = $data['status'] ?? null;
        unset($data['status']);

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->rolesBefore = $this->record->roles()->pluck('slug')->sort()->values()->all();
    }

    protected function afterSave(): void
    {
        if (in_array($this->status, ['active', 'disabled'], true) && $this->status !== $this->record->status) {
            $this->record->forceFill(['status' => $this->status])->save();
            app(AuditLogger::class)->log('user.status_changed', $this->record, null, ['status' => $this->status]);
        }

        $after = $this->record->roles()->pluck('slug')->sort()->values()->all();
        if ($after !== $this->rolesBefore) {
            app(AuditLogger::class)->log('user.roles_changed', $this->record, ['roles' => $this->rolesBefore], ['roles' => $after]);
        }
        $this->record->flushPermissionCache();
    }

    protected function getHeaderActions(): array
    {
        return UserResource::securityActions();
    }
}
