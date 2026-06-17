<?php

namespace App\Filament\Resources\Indisponibilidades\Pages;

use App\Filament\Resources\Indisponibilidades\IndisponibilidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIndisponibilidade extends EditRecord
{
    protected static string $resource = IndisponibilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
