<?php

namespace App\Filament\Resources\Indisponibilidades\Pages;

use App\Filament\Resources\Indisponibilidades\IndisponibilidadeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIndisponibilidade extends CreateRecord
{
    protected static string $resource = IndisponibilidadeResource::class;

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
