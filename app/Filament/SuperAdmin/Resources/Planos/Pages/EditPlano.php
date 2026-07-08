<?php

namespace App\Filament\SuperAdmin\Resources\Planos\Pages;

use App\Filament\SuperAdmin\Resources\Planos\PlanosResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlano extends EditRecord
{
    protected static string $resource = PlanosResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
