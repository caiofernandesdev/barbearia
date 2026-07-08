<?php

namespace App\Filament\SuperAdmin\Resources\TiposEstabelecimento\Pages;

use App\Filament\SuperAdmin\Resources\TiposEstabelecimento\TiposEstabelecimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTiposEstabelecimento extends ListRecords
{
    protected static string $resource = TiposEstabelecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
