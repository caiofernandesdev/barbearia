<?php

namespace App\Filament\Resources\CamposPersonalizados\Pages;

use App\Filament\Resources\CamposPersonalizados\CampoPersonalizadoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampoPersonalizado extends EditRecord
{
    protected static string $resource = CampoPersonalizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
