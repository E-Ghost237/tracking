<?php

namespace App\Policies;

use App\Models\User;

/**
 * Back-office policies are permission based (section 2): roles are data, so new roles
 * work without code changes. Abilities not listed are denied.
 */
abstract class PermissionPolicy
{
    /**
     * Ability => permission slug, or null to deny.
     *
     * @return array<string, string|null>
     */
    abstract protected function abilities(): array;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'viewAny');
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    protected function allows(User $user, string $ability): bool
    {
        $permission = $this->abilities()[$ability] ?? null;

        return $permission !== null && $user->isActive() && $user->hasPermission($permission);
    }
}
