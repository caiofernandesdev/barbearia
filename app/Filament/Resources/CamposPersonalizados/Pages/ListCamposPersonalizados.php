<?php

namespace App\Filament\Resources\CamposPersonalizados\Pages;

use App\Filament\Resources\CamposPersonalizados\CampoPersonalizadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCamposPersonalizados extends ListRecords
{
    protected static string $resource = CampoPersonalizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
