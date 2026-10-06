<?php

namespace App\Services\Notifications;

use App\Jobs\SendTemplatedNotification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Queues templated notifications (FR-110 to FR-113). Email is the v1 channel.
 */
class NotificationService
{
    /**
     * Events customers cannot opt out of: security and payment messages (FR-112).
     */
    public const REQUIRED_EVENTS = [
        'account.verify', 'account.password_reset', 'account.register_existing', 'security.new_login', 'security.password_changed',
        'booking.created', 'payment.reminder', 'proof.received', 'proof.approved', 'proof.rejected',
        'proof.more_info', 'order.expired', 'refund.processed', 'tracking.confirm_subscription',
    ];

    /**
     * Customer-facing events that can be switched off in notification preferences (FR-105).
     *
     * @return array<string, string>
     */
    public static function optionalCustomerEvents(): array
    {
        return [
            'shipment.status_changed' => __('Shipment status changes'),
            'ticket.reply' => __('Replies to my support tickets'),
            'claim.update' => __('Claim updates'),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function send(string $event, User|string $recipient, array $variables = [], ?string $locale = null): ?NotificationLog
    {
        $user = $recipient instanceof User ? $recipient : null;
        $email = $user?->email ?? (string) $recipient;
        $locale ??= $user?->preferredLocale() ?? app()->getLocale();
        $locale = in_array($locale, config('platform.locales'), true) ? $locale : 'en';

        $required = in_array($event, self::REQUIRED_EVENTS, true) || str_starts_with($event, 'admin.');
        if ($user !== null && ! $required && ! $user->wantsNotification($event)) {
            return null;
        }

        $template = $this->template($event, $locale);
        if ($template === null) {
            return null;
        }

        $variables += [
            'brand' => config('platform.brand.name'),
            'name' => $user?->name ?? '',
            'support_email' => config('platform.brand.support_email'),
            'site_url' => config('app.url'),
        ];

        if (! $required && $user !== null) {
            $variables['unsubscribe_url'] = URL::signedRoute('notifications.unsubscribe', ['user' => $user->public_id, 'event' => $event]);
        }

        $log = NotificationLog::query()->create([
            'user_id' => $user?->id,
            'recipient' => $email,
            'event' => $event,
            'channel' => 'mail',
            'status' => 'queued',
        ]);

        SendTemplatedNotification::dispatch($log->id, $template->id, $variables, $locale)->afterCommit();

        return $log;
    }

    /**
     * Sends an alert to every active staff member holding a permission.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function notifyStaff(string $event, string $permission, array $variables = []): void
    {
        User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('slug', $permission))
            ->each(fn (User $staff) => $this->send($event, $staff, $variables));
    }

    private function template(string $event, string $locale): ?NotificationTemplate
    {
        return NotificationTemplate::query()->where('event', $event)->where('locale', $locale)->where('is_active', true)->first()
            ?? NotificationTemplate::query()->where('event', $event)->where('locale', 'en')->where('is_active', true)->first();
    }
}
