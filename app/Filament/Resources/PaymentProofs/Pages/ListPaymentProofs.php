<?php

namespace App\Filament\Resources\PaymentProofs\Pages;

use App\Filament\Resources\PaymentProofs\PaymentProofResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentProofs extends ListRecords
{
    protected static string $resource = PaymentProofResource::class;
}
