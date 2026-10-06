<?php

namespace App\Policies;

use App\Support\Permissions;

class RolePolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::ROLES_MANAGE, 'view' => Permissions::ROLES_MANAGE, 'create' => Permissions::ROLES_MANAGE, 'update' => Permissions::ROLES_MANAGE, 'delete' => null];
    }
}
