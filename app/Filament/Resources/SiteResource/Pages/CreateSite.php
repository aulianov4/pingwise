<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Создание сайта';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    // Инициализация тестов происходит автоматически в SiteObserver::created()
}
