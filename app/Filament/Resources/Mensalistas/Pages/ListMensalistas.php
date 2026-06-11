<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Filament\Resources\Mensalistas\MensalistaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMensalistas extends ListRecords
{
    protected static string $resource = MensalistaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
