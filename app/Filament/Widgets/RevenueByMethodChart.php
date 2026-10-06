<?php

namespace App\Filament\Widgets;

use App\Enums\OrderPaymentStatus;
use App\Models\OrderPayment;
use App\Support\Permissions;
use Filament\Widgets\ChartWidget;

class RevenueByMethodChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Approved payments by method (30 days)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::ORDERS_VIEW);
    }

    protected function getData(): array
    {
        $rows = OrderPayment::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'order_payments.payment_method_id')
            ->where('order_payments.status', OrderPaymentStatus::Approved->value)
            ->where('order_payments.updated_at', '>=', now()->subDays(30))
            ->selectRaw('payment_methods.name as name, COUNT(*) as count')
            ->groupBy('payment_methods.name')
            ->pluck('count', 'name');

        return [
            'datasets' => [['data' => $rows->values()->all(), 'backgroundColor' => ['#ff6b2c', '#22b8f0', '#0a1628', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#64748b']]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
