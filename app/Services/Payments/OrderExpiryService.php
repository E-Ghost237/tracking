<?php

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\NotificationService;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payment reminders at 24 h and 2 h before expiry, and expiry of orders without proof (section 5.10).
 */
class OrderExpiryService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly NotificationService $notifications,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{reminded: int, expired: int}
     */
    public function run(): array
    {
        return ['reminded' => $this->sendReminders(), 'expired' => $this->expire()];
    }

    public function sendReminders(): int
    {
        $count = 0;
        $windows = array_map('intval', (array) $this->settings->get('payment_reminder_hours', [24, 2]));
        $statuses = array_map(fn (OrderStatus $s) => $s->value, array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->canExpire()));

        Order::query()
            ->with('user')
            ->whereIn('status', $statuses)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addHours(max($windows ?: [24])))
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($windows, &$count): void {
                foreach ($orders as $order) {
                    $sent = $order->reminders_sent ?? [];
                    foreach ($windows as $hours) {
                        if (in_array($hours, $sent, true) || $order->expires_at->gt(now()->addHours($hours))) {
                            continue;
                        }

                        $locale = $order->user->preferredLocale();
                        $this->notifications->send('payment.reminder', $order->user, [
                            'order_number' => $order->number,
                            'reference' => $order->payment_reference,
                            'amount' => Money::format($order->balanceDue() ?: $order->total, $order->currency, $locale),
                            'hours' => (string) $hours,
                            'pay_url' => route($locale.'.account.orders.pay', $order),
                        ]);

                        // Mark smaller windows as sent too, so a late run never sends two reminders at once.
                        $sent = array_values(array_unique(array_merge($sent, array_filter($windows, fn ($w) => $w >= $hours))));
                        $order->forceFill(['reminders_sent' => $sent])->save();
                        $count++;

                        break;
                    }
                }
            });

        return $count;
    }

    public function expire(): int
    {
        $count = 0;
        $statuses = array_map(fn (OrderStatus $s) => $s->value, array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->canExpire()));

        Order::query()
            ->whereIn('status', $statuses)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    $expired = DB::transaction(function () use ($order): bool {
                        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                        if ($locked === null || ! $locked->status->canExpire() || $locked->expires_at->isFuture()) {
                            return false;
                        }

                        $locked->payments()->whereNull('superseded_at')->update(['status' => OrderPaymentStatus::Expired->value]);
                        $this->states->transition($locked, OrderStatus::Expired, 'payment_window_elapsed');

                        return true;
                    });

                    if ($expired) {
                        $count++;
                        $order->loadMissing('user');
                        $this->notifications->send('order.expired', $order->user, [
                            'order_number' => $order->number,
                            'reference' => $order->payment_reference,
                            'quote_url' => route($order->user->preferredLocale().'.quote'),
                        ]);
                    }
                }
            });

        return $count;
    }
}
