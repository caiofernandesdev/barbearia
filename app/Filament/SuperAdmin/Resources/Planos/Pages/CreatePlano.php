<?php

namespace App\Filament\SuperAdmin\Resources\Planos\Pages;

use App\Filament\SuperAdmin\Resources\Planos\PlanosResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlano extends CreateRecord
{
    protected static string $resource = PlanosResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlanosResource::mesclarRelatoriosNasFeatures($data);
    }
}
