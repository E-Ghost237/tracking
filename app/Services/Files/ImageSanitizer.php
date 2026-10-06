<?php

namespace App\Services\Files;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Strips metadata (EXIF, GPS) by re-encoding images and builds a review thumbnail (section 5.5).
 */
class ImageSanitizer
{
    /**
     * 40 megapixels: far above any phone screenshot or scanned receipt.
     */
    public const MAX_PIXELS = 40_000_000;

    public function sanitize(StoredFile $file): void
    {
        if (! $file->isImage() || ! class_exists(\Imagick::class)) {
            return;
        }

        $disk = Storage::disk($file->disk);
        $path = $disk->path($file->path);

        try {
            // Cap decoder resources so a small, highly compressed image cannot exhaust the worker.
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_AREA, self::MAX_PIXELS);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, 1024 * 1024 * 1024);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, 30);

            $image = new \Imagick($path);
            $image->autoOrient();
            $image->stripImage();

            if (in_array($file->mime, ['image/heic', 'image/heif'], true)) {
                // Browsers cannot display HEIC: keep a JPEG rendition for review.
                $image->setImageFormat('jpeg');
                $newPath = preg_replace('/\.(heic|heif)$/', '.jpg', $file->path);
                $image->writeImage($disk->path($newPath));
                $disk->delete($file->path);
                $file->path = $newPath;
                $file->mime = 'image/jpeg';
            } else {
                $image->writeImage($path);
            }

            $thumb = clone $image;
            $thumb->thumbnailImage(480, 480, true);
            $thumb->setImageFormat('jpeg');
            $thumb->setImageCompressionQuality(78);
            $thumbPath = preg_replace('/\.[a-z]+$/', '.thumb.jpg', $file->path);
            $thumb->writeImage($disk->path($thumbPath));

            $file->thumbnail_path = $thumbPath;
            $file->size = (int) filesize($disk->path($file->path));
            $file->save();

            $image->clear();
            $thumb->clear();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
