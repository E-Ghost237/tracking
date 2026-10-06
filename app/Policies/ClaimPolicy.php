<?php

namespace App\Policies;

use App\Support\Permissions;

class ClaimPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::CLAIMS_MANAGE, 'view' => Permissions::CLAIMS_MANAGE, 'create' => null, 'update' => Permissions::CLAIMS_MANAGE, 'delete' => null];
    }
}
