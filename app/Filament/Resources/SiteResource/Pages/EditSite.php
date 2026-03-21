<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Services\TestService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Восстанавливаем исходные ключи для test_type, если они были изменены на названия
        if (isset($data['siteTests']) && is_array($data['siteTests'])) {
            $testService = app(TestService::class);
            $tests = $testService->getAllTests();
            $nameToKey = [];

            foreach ($tests as $key => $test) {
                $nameToKey[$test->getName()] = $key;
            }

            foreach ($data['siteTests'] as &$siteTest) {
                if (isset($siteTest['test_type'])) {
                    $testType = $siteTest['test_type'];
                    // Если это название (не ключ), заменяем на ключ
                    if (isset($nameToKey[$testType])) {
                        $siteTest['test_type'] = $nameToKey[$testType];
                    }
                }
            }
        }

        return $data;
    }
}
