<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Filament\Resources\Mensalistas\MensalistaResource;
use App\Services\GerarAgendamentosFixosService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMensalista extends EditRecord
{
    protected static string $resource = MensalistaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    // Gera os agendamentos das próximas semanas a partir dos horários fixos
    protected function afterSave(): void
    {
        $criados = app(GerarAgendamentosFixosService::class)->gerar($this->record);

        if ($criados > 0) {
            Notification::make()
                ->title("{$criados} agendamento(s) fixo(s) gerado(s)")
                ->body('Já aparecem na agenda para as próximas semanas.')
                ->success()
                ->send();
        }
    }
}
