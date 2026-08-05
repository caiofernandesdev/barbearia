<?php

namespace App\Filament\Resources\Agendamentos\Pages;

use App\Filament\Resources\Agendamentos\AgendamentoResource;
use App\Models\Servico;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAgendamento extends CreateRecord
{
    protected static string $resource = AgendamentoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth('admin')->user()?->tenant_id;

        return $data;
    }

    /**
     * Multi-serviço: servico_ids não é coluna — vira o pivot. servico_id guarda
     * o primeiro (retrocompat) e os totais somam pelo preço do dia do atendimento.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $servicoIds = $data['servico_ids'] ?? [];
        unset($data['servico_ids']);

        $servicos = Servico::whereIn('id', $servicoIds)->orderBy('ordem')->get();
        $diaSemana = Carbon::parse($data['data_hora'])->dayOfWeek;

        $data['servico_id'] = $servicos->first()?->id;
        $data['valor_total'] = $servicos->sum(fn ($s) => $s->precoNoDia($diaSemana));
        $data['duracao_total_minutos'] = (int) $servicos->sum('duracao_minutos');

        $record = static::getModel()::create($data);
        $record->servicos()->attach($servicos->pluck('id')->all());

        return $record;
    }
}
