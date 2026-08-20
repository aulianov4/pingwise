<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Filament\Widgets\ServerMetricsChartWidget;
use App\Models\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewServer extends ViewRecord
{
    protected static string $resource = ServerResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rotateToken')
                ->label('Сменить токен')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Сменить токен агента?')
                ->modalDescription('Старый токен сразу перестанет работать. На сервере нужно заново выполнить команду установки.')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false)
                ->action(function (): void {
                    /** @var Server $server */
                    $server = $this->getRecord();
                    $token = $server->rotateToken();
                    session()->flash('server_install_token', $token);

                    Notification::make()
                        ->title('Токен обновлён')
                        ->body('Скопируйте новую команду установки на этой странице.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $server]));
                }),
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ServerMetricsChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }
}
