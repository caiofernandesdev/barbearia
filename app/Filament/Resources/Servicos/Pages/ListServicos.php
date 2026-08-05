<?php

namespace App\Filament\Resources\Servicos\Pages;

use App\Filament\Concerns\TogglesTableLayout;
use App\Filament\Resources\Servicos\ServicoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServicos extends ListRecords
{
    use TogglesTableLayout;

    protected static string $resource = ServicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->layoutToggleAction(),
            CreateAction::make()->label('Novo Serviço'),
        ];
    }
}
