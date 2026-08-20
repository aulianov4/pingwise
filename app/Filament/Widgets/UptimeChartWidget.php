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

    protected ?string $heading = 'Время отклика';

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
            $grouped[$periodKey] = ['total' => 0, 'sum_ms' => 0, 'count_ms' => 0];
        }

        foreach ($results as $result) {
            $key = $result->checked_at->format($groupFormat);
            if (isset($grouped[$key])) {
                $grouped[$key]['total']++;
                $ms = $result->responseTimeMs();

                if ($ms !== null) {
                    $grouped[$key]['sum_ms'] += $ms;
                    $grouped[$key]['count_ms']++;
                }
            }
        }

        $labels = [];
        $pingData = [];

        foreach ($grouped as $periodKey => $data) {
            $labels[] = Carbon::createFromFormat($groupFormat, $periodKey)->format($labelFormat);
            $pingData[] = $data['count_ms'] > 0
                ? (int) round($data['sum_ms'] / $data['count_ms'])
                : null;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Пинг (мс)',
                    'data' => $pingData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
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
                    'ticks' => [
                        'callback' => '(value) => value + " мс"',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => '(context) => context.parsed.y + " мс"',
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
