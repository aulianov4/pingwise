<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Models\SiteTest;
use App\Models\TestResult;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = Auth::id();

        // Количество сайтов пользователя
        $sitesCount = Site::where('user_id', $userId)->count();

        // Общий scope для результатов тестов пользователя
        $userResults = fn () => TestResult::whereHas('site', fn ($query) => $query->where('user_id', $userId));

        // Количество настроек тестов для сайтов пользователя
        $testsCount = SiteTest::whereHas('site', fn ($query) => $query->where('user_id', $userId))->count();

        // Количество выполненных тестов за последние сутки
        $testsLast24h = $userResults()
            ->where('checked_at', '>=', now()->subDay())
            ->count();

        // Количество отказов (failed тестов) за последние сутки
        $failuresLast24h = $userResults()
            ->where('checked_at', '>=', now()->subDay())
            ->where('status', 'failed')
            ->count();

        // Процент успешности за последние сутки
        $successRate = $testsLast24h > 0
            ? round((($testsLast24h - $failuresLast24h) / $testsLast24h) * 100, 1)
            : 100;

        return [
            Stat::make('Сайтов добавлено', $sitesCount)
                ->description('Всего сайтов в системе')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),

            Stat::make('Настроек тестов', $testsCount)
                ->description('Всего настроенных тестов')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('info'),

            Stat::make('Тестов за сутки', $testsLast24h)
                ->description("Успешность: {$successRate}%")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger')),

            Stat::make('Отказов за сутки', $failuresLast24h)
                ->description('Неудачных проверок')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failuresLast24h === 0 ? 'success' : ($failuresLast24h < 5 ? 'warning' : 'danger')),
        ];
    }
}
