<?php

namespace App\Filament\Resources\Profissionais\Pages;

use App\Filament\Resources\Profissionais\ProfissionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfissionais extends ListRecords
{
    protected static string $resource = ProfissionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo Profissional'),
        ];
    }
}
