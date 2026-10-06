<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Permissions;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Revenue by day (USD, last 14 days)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::ORDERS_VIEW);
    }

    protected function getData(): array
    {
        $rows = Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->where('paid_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(paid_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('M j');
            $values[] = round(((int) ($rows[$day] ?? 0)) / 100, 2);
        }

        return [
            'datasets' => [['label' => 'Revenue', 'data' => $values, 'borderColor' => '#ff6b2c', 'backgroundColor' => 'rgba(255,107,44,0.12)', 'fill' => true, 'tension' => 0.35]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
