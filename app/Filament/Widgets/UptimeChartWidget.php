<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Models\TestResult;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class UptimeChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'week';

    protected ?string $heading = 'Аптайм сайта';

    protected ?string $maxHeight = '300px';

    public Site|int|null $record = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'day' => 'За сутки',
            'week' => 'За неделю',
            'month' => 'За месяц',
            'year' => 'За год',
        ];
    }

    protected function getData(): array
    {
        $site = $this->record;

        if (! $site instanceof Site) {
            return ['datasets' => [], 'labels' => []];
        }

        $filter = $this->filter;

        [$startDate, $groupFormat, $labelFormat, $step] = match ($filter) {
            'day' => [now()->subDay(), 'Y-m-d H:00', 'H:i', 'hour'],
            'week' => [now()->subWeek(), 'Y-m-d', 'd.m', 'day'],
            'month' => [now()->subMonth(), 'Y-m-d', 'd.m', 'day'],
            'year' => [now()->subYear(), 'Y-m', 'M Y', 'month'],
            default => [now()->subWeek(), 'Y-m-d', 'd.m', 'day'],
        };

        // Получаем результаты availability-теста
        $results = TestResult::where('site_id', $site->id)
            ->where('test_type', 'availability')
            ->where('checked_at', '>=', $startDate)
            ->orderBy('checked_at')
            ->get();

        // Генерируем временные точки
        $periods = $this->generatePeriods($startDate, Carbon::now(), $step, $groupFormat);

        // Группируем результаты по периодам
        $grouped = [];
        foreach ($periods as $periodKey) {
            $grouped[$periodKey] = ['total' => 0, 'success' => 0];
        }

        foreach ($results as $result) {
            $key = $result->checked_at->format($groupFormat);
            if (isset($grouped[$key])) {
                $grouped[$key]['total']++;
                if ($result->status === 'success') {
                    $grouped[$key]['success']++;
                }
            }
        }

        // Вычисляем процент аптайма для каждого периода
        $labels = [];
        $uptimeData = [];

        foreach ($grouped as $periodKey => $data) {
            $labels[] = Carbon::createFromFormat($groupFormat, $periodKey)->format($labelFormat);
            $uptimeData[] = $data['total'] > 0
                ? round(($data['success'] / $data['total']) * 100, 1)
                : null;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Аптайм (%)',
                    'data' => $uptimeData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'spanGaps' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 100,
                    'ticks' => [
                        'callback' => '(value) => value + "%"',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => '(context) => context.parsed.y + "%"',
                    ],
                ],
            ],
        ];
    }

    /**
     * Генерация временных точек для графика
     */
    private function generatePeriods(Carbon $start, Carbon $end, string $step, string $format): array
    {
        $periods = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $periods[] = $current->format($format);

            $current = match ($step) {
                'hour' => $current->addHour(),
                'day' => $current->addDay(),
                'month' => $current->addMonth(),
                default => $current->addDay(),
            };
        }

        return $periods;
    }
}
