<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ShipmentDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private files behind a 5-minute signed URL AND an authorisation check:
 * the signature alone is never enough (section 5.5, FR-145).
 */
class FileController extends Controller
{
    public function __invoke(Request $request, string $file, AuditLogger $audit): StreamedResponse
    {
        $stored = StoredFile::query()->where('public_id', $file)->firstOrFail();
        $user = $request->user();

        abort_unless($this->canAccess($user, $stored), 404);
        abort_if($stored->scan_status === 'infected', 404);

        $thumbnail = $request->boolean('thumb') && $stored->thumbnail_path !== null;
        $path = $thumbnail ? $stored->thumbnail_path : $stored->path;
        $disk = Storage::disk($stored->disk);
        abort_unless($disk->exists($path), 404);

        // Every access to someone else's private file (proofs, claims, labels, invoices) is audited.
        if ($stored->owner_id !== $user->id) {
            $audit->log('file.viewed', $stored, null, ['purpose' => $stored->purpose, 'thumb' => $thumbnail]);
        }

        $mime = $thumbnail ? 'image/jpeg' : $stored->mime;
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($path, $stored->original_name, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes((string) $stored->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    private function canAccess(User $user, StoredFile $file): bool
    {
        return match ($file->purpose) {
            'payment_proofs' => $file->owner_id === $user->id || $user->hasPermission(Permissions::PROOFS_VIEW),
            'documents' => $this->documentOwner($user, $file) || $user->hasPermission(Permissions::SHIPMENTS_VIEW) || $user->hasPermission(Permissions::ORDERS_VIEW),
            'claims' => $file->owner_id === $user->id || $user->hasPermission(Permissions::CLAIMS_MANAGE),
            'payment_method_logos' => true,
            default => $user->hasPermission(Permissions::SETTINGS_MANAGE),
        };
    }

    private function documentOwner(User $user, StoredFile $file): bool
    {
        if ($file->owner_id !== $user->id) {
            return false;
        }

        // Documents are visible to the owner only once released (BR-01).
        return ShipmentDocument::query()->where('file_id', $file->id)->where('is_released', true)->exists()
            || Invoice::query()->where('pdf_file_id', $file->id)->exists();
    }
}
