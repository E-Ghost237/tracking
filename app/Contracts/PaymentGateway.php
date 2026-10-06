<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Payment boundary (section 5.11). ManualProofGateway is the v1 implementation;
 * a StripeGateway can be added later without changing the order and payment tables.
 */
interface PaymentGateway
{
    public function select(Order $order, PaymentMethod $method, User $customer): OrderPayment;

    public function requiresProof(): bool;
}
