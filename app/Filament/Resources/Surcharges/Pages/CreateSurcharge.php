<?php

namespace App\Filament\Resources\Surcharges\Pages;

use App\Filament\Resources\Surcharges\SurchargeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurcharge extends CreateRecord
{
    protected static string $resource = SurchargeResource::class;
}
