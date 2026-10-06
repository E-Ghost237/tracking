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
            'datasets' => [['data' => $rows->values()->all(), 'backgroundColor' => [/* accent, navy ramp and route blue — see resources/css/app.css */ '#c2410c', '#375c86', '#e2703a', '#0f2740', '#7fb3e0', '#9a3412', '#94adc9', '#234669']]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
