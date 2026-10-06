<?php

namespace App\Policies;

use App\Support\Permissions;

class FaqPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::CONTENT_MANAGE, 'view' => Permissions::CONTENT_MANAGE, 'create' => Permissions::CONTENT_MANAGE, 'update' => Permissions::CONTENT_MANAGE, 'delete' => Permissions::CONTENT_MANAGE];
    }
}
