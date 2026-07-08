<?php

namespace App\Filament\SuperAdmin\Resources\Planos\Pages;

use App\Filament\SuperAdmin\Resources\Planos\PlanosResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanos extends ListRecords
{
    protected static string $resource = PlanosResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
