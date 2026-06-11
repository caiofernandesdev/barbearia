<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Filament\Resources\Mensalistas\MensalistaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensalista extends EditRecord
{
    protected static string $resource = MensalistaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
