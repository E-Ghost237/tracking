<?php

namespace App\Services\Files;

use App\Exceptions\DomainRuleException;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Stores uploads in private object storage under random names, after checking the
 * real content type (not the extension), and serves them only through short-lived
 * signed URLs (FR-145, section 5.5).
 */
class FileStorageService
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'application/pdf' => 'pdf',
    ];

    public function disk(): string
    {
        return (string) config('filesystems.private_disk', 'private');
    }

    /**
     * @param  array<int, string>  $allowedMimes
     */
    public function storeUpload(UploadedFile $upload, string $purpose, ?User $owner, array $allowedMimes, int $maxKb): StoredFile
    {
        if (! $upload->isValid()) {
            throw new DomainRuleException('upload_failed', __('The file could not be uploaded.'));
        }

        if ($upload->getSize() > $maxKb * 1024) {
            throw new DomainRuleException('file_too_large', __('Each file must be :size MB or smaller.', ['size' => (int) round($maxKb / 1024)]));
        }

        $realPath = (string) $upload->getRealPath();
        $mime = $this->sniffMime($realPath);

        if (! in_array($mime, $allowedMimes, true) || ! $this->contentMatches($realPath, $mime)) {
            throw new DomainRuleException('file_type_not_allowed', __('Only JPG, PNG, WebP, HEIC images and PDF documents are accepted.'));
        }

        $extension = self::EXTENSIONS[$mime] ?? 'bin';
        $path = sprintf('%s/%s/%s.%s', $purpose, now()->format('Y/m'), Str::ulid(), $extension);

        $stream = fopen($realPath, 'rb');
        Storage::disk($this->disk())->put($path, $stream, ['visibility' => 'private']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return StoredFile::query()->create([
            'disk' => $this->disk(),
            'path' => $path,
            'mime' => $mime,
            'size' => (int) $upload->getSize(),
            'sha256' => hash_file('sha256', $realPath),
            'owner_id' => $owner?->id,
            'is_private' => true,
            'purpose' => $purpose,
            'original_name' => $this->safeName($upload->getClientOriginalName(), $extension),
            'scan_status' => 'pending',
        ]);
    }

    /**
     * Stores generated content (PDF labels, invoices).
     */
    public function storeGenerated(string $contents, string $purpose, string $mime, string $extension, ?User $owner, string $downloadName): StoredFile
    {
        $path = sprintf('%s/%s/%s.%s', $purpose, now()->format('Y/m'), Str::ulid(), $extension);
        Storage::disk($this->disk())->put($path, $contents, ['visibility' => 'private']);

        return StoredFile::query()->create([
            'disk' => $this->disk(),
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'owner_id' => $owner?->id,
            'is_private' => true,
            'purpose' => $purpose,
            'original_name' => $downloadName,
            'scan_status' => 'clean',
        ]);
    }

    /**
     * Signed link valid for a few minutes. The route still re-checks authorisation.
     */
    public function temporaryUrl(StoredFile $file, bool $thumbnail = false, bool $download = false): string
    {
        $minutes = (int) config('platform.upload.signed_url_minutes', 5);

        return URL::temporarySignedRoute('files.show', now()->addMinutes($minutes), array_filter([
            'file' => $file->public_id,
            'thumb' => $thumbnail ? 1 : null,
            'download' => $download ? 1 : null,
        ]));
    }

    public function absolutePath(StoredFile $file, bool $thumbnail = false): string
    {
        return Storage::disk($file->disk)->path($thumbnail && $file->thumbnail_path ? $file->thumbnail_path : $file->path);
    }

    public function sniffMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        return match ($mime) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            default => $mime,
        };
    }

    /**
     * Second check on top of libmagic: images must decode, PDFs must start with the PDF header.
     */
    private function contentMatches(string $path, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            $head = (string) file_get_contents($path, false, null, 0, 5);

            return $head === '%PDF-';
        }

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            $head = (string) file_get_contents($path, false, null, 4, 8);

            return str_starts_with($head, 'ftyp');
        }

        $info = @getimagesize($path);

        return is_array($info) && $info[0] > 0 && $info[1] > 0 && $info[0] <= 12000 && $info[1] <= 12000;
    }

    private function safeName(string $name, string $extension): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return Str::limit($base, 60, '').'.'.$extension;
    }
}
