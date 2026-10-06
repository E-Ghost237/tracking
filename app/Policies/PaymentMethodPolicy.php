<?php

namespace App\Policies;

use App\Support\Permissions;

class PaymentMethodPolicy extends PermissionPolicy
{
    protected function abilities(): array
    {
        return ['viewAny' => Permissions::PAYMENT_METHODS_MANAGE, 'view' => Permissions::PAYMENT_METHODS_MANAGE, 'create' => Permissions::PAYMENT_METHODS_MANAGE, 'update' => Permissions::PAYMENT_METHODS_MANAGE, 'delete' => Permissions::PAYMENT_METHODS_MANAGE];
    }
}
