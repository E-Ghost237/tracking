<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * New accounts get an unusable random password and a reset link: admins never set or see passwords.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private string $status = 'active';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->status = ($data['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
        unset($data['status']);
        $data['password'] = Str::password(40);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->forceFill(['email_verified_at' => now(), 'status' => $this->status])->save();
        Password::sendResetLink(['email' => $this->record->email]);
        app(AuditLogger::class)->log('user.created_by_admin', $this->record, null, ['roles' => $this->record->roles()->pluck('slug')->all()]);
    }
}
