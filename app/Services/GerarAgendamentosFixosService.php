<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Mensalista;
use Carbon\Carbon;

/**
 * Gera os agendamentos reais a partir dos Horários Fixos Semanais de um
 * mensalista fixo, para as próximas N semanas. Não duplica os que já existem.
 * O status de cada agendamento é decidido pelo hook do model (pendente com
 * WhatsApp ativo, confirmado sem).
 */
class GerarAgendamentosFixosService
{
    public function gerar(Mensalista $mensalista, int $semanas = 8): int
    {
        if ($mensalista->tipo !== 'mensalista_fixo') {
            return 0;
        }

        $agora = now();
        $limite = $agora->copy()->addWeeks($semanas);
        $criados = 0;

        $fixos = $mensalista->horariosFixos()->where('ativo', true)->with('servico')->get();

        foreach ($fixos as $fixo) {
            // Primeira ocorrência do dia da semana a partir de hoje
            $data = $agora->copy()->startOfDay();
            while ($data->dayOfWeek !== (int) $fixo->dia_semana) {
                $data->addDay();
            }

            for ($d = $data->copy(); $d->lte($limite); $d->addWeek()) {
                $dataHora = Carbon::parse($d->format('Y-m-d').' '.substr($fixo->hora, 0, 5));

                if ($dataHora->lte($agora)) {
                    continue; // passado
                }

                $existe = Agendamento::withoutGlobalScopes()
                    ->where('tenant_id', $mensalista->tenant_id)
                    ->where('cliente_telefone', $mensalista->telefone)
                    ->where('data_hora', $dataHora)
                    ->whereIn('status', ['pendente', 'confirmado'])
                    ->exists();

                // Não sobrepõe outro cliente no mesmo profissional/horário
                $duracao = (int) ($fixo->servico?->duracao_minutos ?? 30);
                $conflito = Agendamento::temConflito(
                    (int) $fixo->profissional_id, $dataHora, $duracao, $mensalista->tenant_id
                );

                if ($existe || $conflito) {
                    continue;
                }

                // status omitido: o hook do Agendamento decide pendente/confirmado
                Agendamento::create([
                    'cliente_nome' => $mensalista->nome,
                    'cliente_telefone' => $mensalista->telefone,
                    'profissional_id' => $fixo->profissional_id,
                    'servico_id' => $fixo->servico_id,
                    'data_hora' => $dataHora,
                    'mensalista' => true,
                    'mensalista_id' => $mensalista->id,
                    'tenant_id' => $mensalista->tenant_id,
                ]);
                $criados++;
            }
        }

        return $criados;
    }
}
