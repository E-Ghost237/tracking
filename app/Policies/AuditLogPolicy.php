<?php

namespace App\Policies;

use App\Support\Permissions;

class AuditLogPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::AUDIT_VIEW, 'view' => Permissions::AUDIT_VIEW, 'create' => null, 'update' => null, 'delete' => null];
    }
}
