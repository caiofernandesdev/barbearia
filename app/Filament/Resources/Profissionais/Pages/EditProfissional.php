<?php

namespace App\Filament\Resources\Profissionais\Pages;

use App\Filament\Resources\Profissionais\ProfissionalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProfissional extends EditRecord
{
    protected static string $resource = ProfissionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Excluir'),
        ];
    }
}
