<?php

namespace App\Policies;

use App\Support\Permissions;

class TransportModePolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::RATES_MANAGE, 'view' => Permissions::RATES_MANAGE, 'create' => Permissions::RATES_MANAGE, 'update' => Permissions::RATES_MANAGE, 'delete' => Permissions::RATES_MANAGE];
    }
}
