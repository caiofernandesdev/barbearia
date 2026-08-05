<?php

namespace App\Filament\Resources\Profissionais\Pages;

use App\Filament\Concerns\TogglesTableLayout;
use App\Filament\Resources\Profissionais\ProfissionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfissionais extends ListRecords
{
    use TogglesTableLayout;

    protected static string $resource = ProfissionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->layoutToggleAction(),
            CreateAction::make()->label('Novo Profissional'),
        ];
    }
}
