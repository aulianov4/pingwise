<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Models\Server;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    public function getTitle(): string
    {
        return 'Редактирование сервера';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Server $record */
        $record = $this->getRecord();

        $data['settings'] = array_merge(
            Server::defaultSettings(),
            is_array($record->settings) ? $record->settings : [],
            $data['settings'] ?? [],
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
