<?php

namespace App\Filament\Resources\Agendamentos\Pages;

use App\Filament\Concerns\TogglesTableLayout;
use App\Filament\Resources\Agendamentos\AgendamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgendamentos extends ListRecords
{
    use TogglesTableLayout;

    protected static string $resource = AgendamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->layoutToggleAction(),
            CreateAction::make()->label('Novo Agendamento'),
        ];
    }
}
