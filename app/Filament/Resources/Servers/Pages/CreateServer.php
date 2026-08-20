<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use App\Models\Server;
use Filament\Resources\Pages\CreateRecord;

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    protected ?string $plainToken = null;

    public function getTitle(): string
    {
        return 'Новый сервер';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $plain = Server::generatePlainToken();
        $this->plainToken = $plain;

        $data['token_hash'] = Server::hashToken($plain);
        $data['token_prefix'] = Server::prefixFromPlain($plain);
        $data['settings'] = array_merge(Server::defaultSettings(), $data['settings'] ?? []);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (is_string($this->plainToken) && $this->plainToken !== '') {
            session()->flash('server_install_token', $this->plainToken);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
