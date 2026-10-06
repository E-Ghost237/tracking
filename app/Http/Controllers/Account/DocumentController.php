<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Files\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Label, invoices and receipt downloads, only once released (BR-01, FR-33).
 */
class DocumentController extends Controller
{
    public function __invoke(Request $request, string $shipment, string $type, FileStorageService $files): RedirectResponse
    {
        $model = Shipment::query()->whereBelongsTo($request->user())->where('public_id', $shipment)->whereNotNull('released_at')->firstOrFail();
        $document = $model->documents()->where('type', $type)->where('is_released', true)->whereNotNull('file_id')->with('file')->first();

        if ($document === null) {
            return back()->with('status', __('This document is being prepared. Please try again in a minute.'));
        }

        return redirect()->away($files->temporaryUrl($document->file, download: $request->boolean('download')));
    }
}
