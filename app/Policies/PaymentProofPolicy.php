<?php

namespace App\Policies;

use App\Support\Permissions;

class PaymentProofPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::PROOFS_VIEW, 'view' => Permissions::PROOFS_VIEW, 'create' => null, 'update' => null, 'delete' => null];
    }
}
