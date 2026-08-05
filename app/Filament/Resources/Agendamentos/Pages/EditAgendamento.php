<?php

namespace App\Filament\Resources\Agendamentos\Pages;

use App\Filament\Resources\Agendamentos\AgendamentoResource;
use App\Models\Servico;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAgendamento extends EditRecord
{
    protected static string $resource = AgendamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Excluir'),
        ];
    }

    /** Pré-seleciona os serviços do pivot (ou o servico_id antigo, se pivot vazio). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $ids = $this->record->servicos()->pluck('servicos.id')->all();

        if (empty($ids) && $this->record->servico_id) {
            $ids = [$this->record->servico_id];
        }

        $data['servico_ids'] = $ids;

        return $data;
    }

    /** Recomputa totais e sincroniza o pivot ao editar (mesma regra do create). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $servicoIds = $data['servico_ids'] ?? [];
        unset($data['servico_ids']);

        $servicos = Servico::whereIn('id', $servicoIds)->orderBy('ordem')->get();
        $diaSemana = Carbon::parse($data['data_hora'])->dayOfWeek;

        $data['servico_id'] = $servicos->first()?->id;
        $data['valor_total'] = $servicos->sum(fn ($s) => $s->precoNoDia($diaSemana));
        $data['duracao_total_minutos'] = (int) $servicos->sum('duracao_minutos');

        $record->update($data);
        $record->servicos()->sync($servicos->pluck('id')->all());

        return $record;
    }
}
