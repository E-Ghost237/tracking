<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return [
            'viewAny' => Permissions::CUSTOMERS_VIEW,
            'view' => Permissions::CUSTOMERS_VIEW,
            'create' => Permissions::USERS_MANAGE,
            'update' => Permissions::USERS_MANAGE,
            'delete' => Permissions::USERS_MANAGE,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'viewAny') || $user->hasPermission(Permissions::USERS_MANAGE);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Nobody edits or disables their own account from the back-office, so an admin cannot
     * lift their own restrictions or lock themselves out by mistake.
     */
    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'update') && ! ($model instanceof User && $model->is($user));
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete') && ! ($model instanceof User && $model->is($user));
    }
}
