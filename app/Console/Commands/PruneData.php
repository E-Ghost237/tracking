<?php

namespace App\Console\Commands;

use App\Models\PaymentProof;
use App\Models\StoredFile;
use App\Models\TrackingCache;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneData extends Command
{
    protected $signature = 'data:prune';

    protected $description = 'Apply retention rules: tokens, caches, proofs after 24 months (section 11.3).';

    public function handle(): int
    {
        DB::table('password_reset_tokens')->where('created_at', '<', now()->subHours(2))->delete();
        TrackingCache::query()->where('fetched_at', '<', now()->subDays(7))->delete();
        WebhookEvent::query()->whereNotNull('processed_at')->where('created_at', '<', now()->subDays(90))->delete();
        DB::table('login_attempts')->where('created_at', '<', now()->subMonths(12))->delete();
        DB::table('notifications_log')->where('created_at', '<', now()->subMonths(12))->delete();

        // Truncate IP addresses in logs older than 90 days (section 11.3).
        DB::table('login_attempts')->where('created_at', '<', now()->subDays(90))->where('ip', 'not like', '%.0')
            ->update(['ip' => DB::raw("regexp_replace(ip, '\\.[0-9]+$', '.0')")]);

        // Payment proof files are deleted after 24 months; the review records stay.
        $cutoff = now()->subMonths(24);
        PaymentProof::query()->where('submitted_at', '<', $cutoff)->with('files')->each(function (PaymentProof $proof): void {
            foreach ($proof->files as $file) {
                Storage::disk($file->disk)->delete(array_filter([$file->path, $file->thumbnail_path]));
                $file->forceFill(['path' => 'purged', 'thumbnail_path' => null, 'scan_status' => 'purged'])->save();
            }
            $proof->giftCard?->forceFill(['code' => 'purged', 'pin' => null])->save();
        });

        StoredFile::query()->where('purpose', 'claims')->where('created_at', '<', $cutoff)->each(function (StoredFile $file): void {
            Storage::disk($file->disk)->delete(array_filter([$file->path, $file->thumbnail_path]));
            $file->forceFill(['path' => 'purged', 'scan_status' => 'purged'])->save();
        });

        Storage::disk('local')->delete(collect(Storage::disk('local')->files('imports'))->all());

        $this->info('Retention rules applied.');

        return self::SUCCESS;
    }
}
