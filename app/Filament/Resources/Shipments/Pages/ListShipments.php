<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Pages\ImportEvents;
use App\Filament\Resources\Shipments\ShipmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Import events (CSV)')->icon(Heroicon::OutlinedArrowUpTray)
                ->url(ImportEvents::getUrl())
                ->visible(fn () => ImportEvents::canAccess()),
        ];
    }
}
