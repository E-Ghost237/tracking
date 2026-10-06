<?php

namespace App\Jobs;

use App\Contracts\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Renders and delivers one notification. Retried three times with back-off and logged (FR-111).
 */
class SendTemplatedNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function __construct(
        public int $logId,
        public int $templateId,
        public array $variables,
        public string $locale,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(TemplateRenderer $renderer, NotificationChannel $channel): void
    {
        $log = NotificationLog::query()->find($this->logId);
        $template = NotificationTemplate::query()->find($this->templateId);
        if ($log === null || $template === null || $log->status === 'sent') {
            return;
        }

        $log->increment('attempts');
        $rendered = $renderer->render($template->subject, $template->body, $this->variables);

        $providerId = $channel->deliver($log, [
            'subject' => $rendered['subject'],
            'body' => $rendered['html'],
            'unsubscribe_url' => isset($this->variables['unsubscribe_url']) ? (string) $this->variables['unsubscribe_url'] : null,
            'locale' => $this->locale,
        ]);

        $log->forceFill(['status' => 'sent', 'provider_id' => $providerId, 'sent_at' => now(), 'error' => null])->save();
    }

    public function failed(?Throwable $exception): void
    {
        NotificationLog::query()->whereKey($this->logId)->update([
            'status' => 'failed',
            'error' => substr((string) $exception?->getMessage(), 0, 250),
        ]);
    }
}
