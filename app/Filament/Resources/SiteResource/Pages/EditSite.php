<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Services\TestService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Редактирование сайта';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeFill(): void
    {
        app(TestService::class)->initializeTestsForSite($this->getRecord());
    }
}
