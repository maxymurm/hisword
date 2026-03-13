<?php

namespace App\Filament\Widgets;

use App\Enums\ModuleType;
use App\Models\Module;
use Filament\Widgets\ChartWidget;

class ModuleTypeChart extends ChartWidget
{
    protected static ?string $heading = 'Modules by Type';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $counts = Module::select('type')
            ->selectRaw('count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [
            'bible' => 'rgb(99, 102, 241)',
            'commentary' => 'rgb(16, 185, 129)',
            'dictionary' => 'rgb(245, 158, 11)',
            'devotional' => 'rgb(236, 72, 153)',
            'genbook' => 'rgb(107, 114, 128)',
        ];

        foreach (ModuleType::cases() as $type) {
            $labels[] = ucfirst($type->value);
            $data[] = $counts[$type->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_values($colors),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
