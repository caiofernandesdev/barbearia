<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Filament\Resources\Mensalistas\MensalistaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMensalista extends CreateRecord
{
    protected static string $resource = MensalistaResource::class;

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
