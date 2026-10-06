<?php

namespace App\Policies;

use App\Support\Permissions;

class NotificationTemplatePolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::NOTIFICATIONS_MANAGE, 'view' => Permissions::NOTIFICATIONS_MANAGE, 'create' => null, 'update' => Permissions::NOTIFICATIONS_MANAGE, 'delete' => null];
    }
}
