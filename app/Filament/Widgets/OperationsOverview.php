<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Shipment;
use App\Support\Money;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * FR-120: proofs waiting (and oldest age), orders awaiting payment, late shipments, revenue.
 */
class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::DASHBOARD_VIEW);
    }

    protected function getStats(): array
    {
        $pending = PaymentProof::query()->whereIn('status', [ProofStatus::UnderReview->value, ProofStatus::FirstApproved->value]);
        $waiting = (clone $pending)->count();
        $oldest = (clone $pending)->min('submitted_at');
        $oldestMinutes = $oldest ? (int) now()->diffInMinutes($oldest, true) : 0;
        $alert = (int) config('platform.settings.review_alert_minutes', 60);

        $awaiting = Order::query()->whereIn('status', [OrderStatus::AwaitingPayment->value, OrderStatus::MethodSelected->value, OrderStatus::ProofRejected->value, OrderStatus::MoreInfoRequested->value])->count();
        $late = Shipment::query()->whereNotNull('released_at')->where('eta_at', '<', now())->whereNotIn('status', ['delivered', 'returned', 'cancelled'])->count();

        $stats = [
            Stat::make('Proofs waiting', (string) $waiting)
                ->description($waiting ? 'Oldest: '.$oldestMinutes.' min' : 'Queue is empty')
                ->color($oldestMinutes > $alert ? 'danger' : ($waiting ? 'warning' : 'success'))
                ->icon('heroicon-o-shield-check'),
            Stat::make('Orders awaiting payment', (string) $awaiting)->icon('heroicon-o-clock'),
            Stat::make('Late shipments', (string) $late)->color($late ? 'danger' : 'success')->icon('heroicon-o-exclamation-triangle'),
        ];

        if (auth()->user()->hasPermission(Permissions::ORDERS_VIEW)) {
            $today = (int) Order::query()->where('status', OrderStatus::Paid->value)->where('paid_at', '>=', now()->startOfDay())->sum('total');
            $week = (int) Order::query()->where('status', OrderStatus::Paid->value)->where('paid_at', '>=', now()->subDays(7))->sum('total');
            $stats[] = Stat::make('Revenue today', Money::format($today, 'USD', 'en'))->description('7 days: '.Money::format($week, 'USD', 'en'))->icon('heroicon-o-banknotes');
        }

        return $stats;
    }
}
