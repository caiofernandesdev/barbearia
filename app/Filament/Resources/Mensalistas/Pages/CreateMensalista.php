<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Filament\Resources\Mensalistas\MensalistaResource;
use App\Services\GerarAgendamentosFixosService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMensalista extends CreateRecord
{
    protected static string $resource = MensalistaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth('admin')->user()?->tenant_id;

        return $data;
    }

    // Gera os agendamentos das próximas semanas a partir dos horários fixos
    protected function afterCreate(): void
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
