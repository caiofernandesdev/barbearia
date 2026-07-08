<?php

namespace App\Filament\Resources\Servicos\Pages;

use App\Filament\Resources\Servicos\ServicoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServico extends CreateRecord
{
    protected static string $resource = ServicoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth('admin')->user()?->tenant_id;
        return $data;
    }
}
