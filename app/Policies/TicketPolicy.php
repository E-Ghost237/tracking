<?php

namespace App\Policies;

use App\Support\Permissions;

class TicketPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::TICKETS_MANAGE, 'view' => Permissions::TICKETS_MANAGE, 'create' => null, 'update' => Permissions::TICKETS_MANAGE, 'delete' => null];
    }
}
