<?php

namespace App\Filament\SuperAdmin\Resources\TiposEstabelecimento\Pages;

use App\Filament\SuperAdmin\Resources\TiposEstabelecimento\TiposEstabelecimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoEstabelecimento extends EditRecord
{
    protected static string $resource = TiposEstabelecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
