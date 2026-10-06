<?php

namespace App\Filament\Widgets;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Support\Permissions;
use Filament\Widgets\ChartWidget;

class ShipmentsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Shipments by status';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHIPMENTS_VIEW);
    }

    protected function getData(): array
    {
        $counts = Shipment::query()->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $labels = [];
        $values = [];
        foreach (ShipmentStatus::cases() as $status) {
            $labels[] = $status->label();
            $values[] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'datasets' => [['label' => 'Shipments', 'data' => $values, 'backgroundColor' => '#234669']],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
