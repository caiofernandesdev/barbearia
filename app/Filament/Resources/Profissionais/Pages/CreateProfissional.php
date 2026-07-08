<?php

namespace App\Filament\Resources\Profissionais\Pages;

use App\Filament\Resources\Profissionais\ProfissionalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProfissional extends CreateRecord
{
    protected static string $resource = ProfissionalResource::class;

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
