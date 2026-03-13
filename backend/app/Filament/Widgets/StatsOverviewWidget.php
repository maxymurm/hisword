<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\ReadingPlan;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $totalModules = Module::count();
        $installedModules = Module::where('is_installed', true)->count();
        $totalPlans = ReadingPlan::count();
        $recentUsers = User::where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description("{$recentUsers} new in last 30 days")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
            Stat::make('Verified Users', number_format($verifiedUsers))
                ->description($totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100) . '% verification rate' : '0%')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Modules', number_format($totalModules))
                ->description("{$installedModules} installed")
                ->descriptionIcon('heroicon-m-book-open')
                ->color('info'),
            Stat::make('Reading Plans', number_format($totalPlans))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
        ];
    }
}
