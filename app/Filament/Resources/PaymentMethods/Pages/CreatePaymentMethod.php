<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Gift cards start disabled (FR-90): an admin enables them explicitly after creation.
        if (($data['kind'] ?? null) === 'gift_card') {
            $data['is_enabled'] = false;
            $data['risk_level'] = 'high';
        }

        return $data;
    }
}
