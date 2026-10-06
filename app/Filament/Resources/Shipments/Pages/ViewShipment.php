<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Resources\Shipments\ShipmentResource;
use App\Jobs\ScanStoredFile;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\StoredFile;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('pod')->label('Upload proof of delivery')->icon(Heroicon::OutlinedDocumentCheck)
                ->visible(fn (Shipment $record) => $record->isReleased() && auth()->user()->hasPermission(Permissions::SHIPMENTS_MANAGE))
                ->schema([
                    FileUpload::make('file')->disk('private')->directory('pod')->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(8192)->required(),
                ])
                ->action(function (Shipment $record, array $data, AuditLogger $audit): void {
                    $disk = Storage::disk('private');
                    $path = $data['file'];
                    $absolute = $disk->path($path);
                    $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
                    if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
                        $disk->delete($path);
                        Notification::make()->danger()->title('Unsupported file type.')->send();

                        return;
                    }

                    $file = StoredFile::query()->create([
                        'disk' => 'private', 'path' => $path, 'mime' => $mime, 'size' => (int) filesize($absolute),
                        'sha256' => hash_file('sha256', $absolute), 'owner_id' => $record->user_id, 'is_private' => true,
                        'purpose' => 'documents', 'original_name' => 'proof-of-delivery-'.$record->tracking_number.'.'.pathinfo($path, PATHINFO_EXTENSION),
                        'scan_status' => 'pending',
                    ]);
                    ShipmentDocument::query()->updateOrCreate(['shipment_id' => $record->id, 'type' => 'pod'], ['file_id' => $file->id, 'is_released' => true]);
                    ScanStoredFile::dispatch($file->id);
                    $audit->log('shipment.pod_uploaded', $record, null, ['file' => $file->public_id]);
                    Notification::make()->success()->title('Proof of delivery uploaded.')->send();
                }),
        ];
    }
}
