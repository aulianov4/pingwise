<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Services\TestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Инициализировать тесты для нового сайта
        try {
            $testService = app(TestService::class);
            $testService->initializeTestsForSite($this->record);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to initialize tests for site {$this->record->id}: " . $e->getMessage());
        }
    }
}
