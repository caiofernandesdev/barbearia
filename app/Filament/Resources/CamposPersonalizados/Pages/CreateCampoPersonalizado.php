<?php

namespace App\Filament\Resources\CamposPersonalizados\Pages;

use App\Filament\Resources\CamposPersonalizados\CampoPersonalizadoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCampoPersonalizado extends CreateRecord
{
    protected static string $resource = CampoPersonalizadoResource::class;

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
