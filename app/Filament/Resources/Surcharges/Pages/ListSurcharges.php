<?php

namespace App\Filament\Resources\Surcharges\Pages;

use App\Filament\Resources\Surcharges\SurchargeResource;
use Filament\Resources\Pages\ListRecords;

class ListSurcharges extends ListRecords
{
    protected static string $resource = SurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
