<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class UserRegistrationChart extends ChartWidget
{
    protected static ?string $heading = 'User Registrations';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->format('M Y');
            $data[] = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $data,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
