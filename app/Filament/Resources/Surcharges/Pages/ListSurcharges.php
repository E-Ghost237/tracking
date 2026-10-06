<?php

namespace App\Filament\Resources\Surcharges\Pages;

use App\Filament\Resources\Surcharges\SurchargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSurcharges extends ListRecords
{
    protected static string $resource = SurchargeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
