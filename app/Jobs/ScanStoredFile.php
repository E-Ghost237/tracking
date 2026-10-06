<?php

namespace App\Jobs;

use App\Contracts\MalwareScanner;
use App\Models\StoredFile;
use App\Services\Files\ImageSanitizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Malware scan and metadata stripping for any uploaded file (claims, proof of delivery).
 */
class ScanStoredFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function __construct(public int $fileId) {}

    public function handle(MalwareScanner $scanner, ImageSanitizer $sanitizer): void
    {
        $file = StoredFile::query()->find($this->fileId);
        if ($file === null || $file->scan_status !== 'pending') {
            return;
        }

        $result = $scanner->scan(Storage::disk($file->disk)->path($file->path));
        $file->scan_status = $result['clean'] ? 'clean' : 'infected';
        $file->meta = array_merge($file->meta ?? [], ['scan_signature' => $result['signature']]);
        $file->save();

        if (! $result['clean']) {
            Storage::disk($file->disk)->delete($file->path);

            return;
        }

        $sanitizer->sanitize($file);
    }
}
