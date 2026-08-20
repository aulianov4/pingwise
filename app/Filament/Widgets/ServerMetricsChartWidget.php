<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use App\Models\ServerHeartbeat;
use Filament\Widgets\ChartWidget;

class ServerMetricsChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Метрики';

    protected ?string $maxHeight = '300px';

    public Server|int|null $record = null;

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $server = $this->record;

        if (! $server instanceof Server) {
            return ['datasets' => [], 'labels' => []];
        }

        $heartbeats = ServerHeartbeat::query()
            ->where('server_id', $server->id)
            ->where('source', ServerHeartbeat::SOURCE_HEARTBEAT)
            ->where('reported_at', '>=', now()->subDay())
            ->orderBy('reported_at')
            ->get();

        $labels = [];
        $ram = [];
        $disk = [];
        $load = [];

        foreach ($heartbeats as $heartbeat) {
            $labels[] = $heartbeat->reported_at->format('H:i');
            $ram[] = $heartbeat->memoryAvailablePercent();
            $disk[] = $heartbeat->worstDiskUsedPercent();
            $load[] = $heartbeat->loadPerCore();
        }

        return [
            'datasets' => [
                [
                    'label' => 'RAM доступно %',
                    'data' => $ram,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'spanGaps' => true,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Диск занято %',
                    'data' => $disk,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'spanGaps' => true,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Load на ядро',
                    'data' => $load,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'spanGaps' => true,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 100,
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => '%'],
                ],
                'y1' => [
                    'min' => 0,
                    'position' => 'right',
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'load'],
                ],
            ],
        ];
    }
}
