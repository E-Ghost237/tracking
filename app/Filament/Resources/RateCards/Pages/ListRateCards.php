<?php

namespace App\Filament\Resources\RateCards\Pages;

use App\Filament\Resources\RateCards\RateCardResource;
use App\Services\Pricing\RateCardCsv;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListRateCards extends ListRecords
{
    protected static string $resource = RateCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV as new version')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn () => auth()->user()?->hasPermission(Permissions::RATES_MANAGE))
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->default('Imported rate card'),
                    FileUpload::make('file')
                        ->label('CSV file (zone_from, zone_to, mode, base_fee, weight_from_kg, weight_to_kg, price_per_kg, transit_min_days, transit_max_days)')
                        ->disk('local')->directory('imports')->visibility('private')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->maxSize(2048)->required(),
                ])
                ->action(function (array $data, RateCardCsv $csv): void {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $card = $csv->import($path, $data['name'], auth()->user());
                        Notification::make()->success()->title('Rate card version '.$card->version.' created (inactive). Review and activate it.')->send();
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()->danger()->title('Import failed')->body($e->getMessage())->persistent()->send();
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }
                }),
            CreateAction::make(),
        ];
    }
}
