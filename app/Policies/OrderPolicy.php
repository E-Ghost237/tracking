<?php

namespace App\Policies;

use App\Support\Permissions;

class OrderPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::ORDERS_VIEW, 'view' => Permissions::ORDERS_VIEW, 'create' => null, 'update' => null, 'delete' => null];
    }
}
