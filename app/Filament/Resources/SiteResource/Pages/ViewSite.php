<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Filament\Widgets\TestResultsOverviewWidget;
use App\Filament\Widgets\UptimeChartWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UptimeChartWidget::class,
            TestResultsOverviewWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }
}
