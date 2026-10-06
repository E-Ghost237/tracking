<?php

namespace App\Policies;

use App\Support\Permissions;

class ShipmentPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::SHIPMENTS_VIEW, 'view' => Permissions::SHIPMENTS_VIEW, 'create' => null, 'update' => Permissions::SHIPMENTS_MANAGE, 'delete' => null];
    }
}
