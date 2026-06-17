<?php

namespace App\Filament\Resources\Indisponibilidades\Pages;

use App\Filament\Resources\Indisponibilidades\IndisponibilidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndisponibilidades extends ListRecords
{
    protected static string $resource = IndisponibilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova indisponibilidade'),
        ];
    }
}
