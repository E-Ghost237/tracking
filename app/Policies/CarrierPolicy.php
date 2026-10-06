<?php

namespace App\Policies;

use App\Support\Permissions;

class CarrierPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::CARRIERS_MANAGE, 'view' => Permissions::CARRIERS_MANAGE, 'create' => Permissions::CARRIERS_MANAGE, 'update' => Permissions::CARRIERS_MANAGE, 'delete' => null];
    }
}
