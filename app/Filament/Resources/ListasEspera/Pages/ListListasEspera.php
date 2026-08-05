<?php

namespace App\Filament\Resources\ListasEspera\Pages;

use App\Filament\Concerns\TogglesTableLayout;
use App\Filament\Resources\ListasEspera\ListaEsperaResource;
use Filament\Resources\Pages\ListRecords;

class ListListasEspera extends ListRecords
{
    use TogglesTableLayout;

    protected static string $resource = ListaEsperaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->layoutToggleAction(),
        ];
    }
}
