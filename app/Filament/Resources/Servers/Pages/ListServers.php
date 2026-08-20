<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    public function getTitle(): string
    {
        return 'Серверы';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Добавить сервер'),
        ];
    }
}
