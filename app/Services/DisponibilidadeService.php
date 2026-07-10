<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Indisponibilidade;
use App\Models\MensalistaHorarioFixo;
use App\Models\Profissional;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula os horários disponíveis para agendamento com base nas lacunas reais da agenda.
 *
 * Dois modos de operação:
 *
 * MODO LISTA — quando o profissional tem `horarios_trabalho` configurados
 *   Usa os horários pré-definidos como candidatos e descarta os que colidem
 *   com agendamentos existentes. Retrocompatível com o fluxo de barbearia.
 *
 * MODO GAP-BASED — quando `horarios_trabalho` está vazio
 *   1. Monta todos os intervalos ocupados (agendamentos + mensalistas fixos)
 *   2. Mescla intervalos sobrepostos
 *   3. Identifica as lacunas livres entre abertura e encerramento
 *   4. Dentro de cada lacuna, gera slots espaçados por `intervalo_minutos`
 *      onde o serviço cabe completamente (slot_início + duração ≤ fim_da_lacuna)
 *
 * Isso torna o sistema multi-segmento: manicure, estética, barbearia e qualquer
 * negócio com serviços de duração variável funcionam sem ajustes de configuração.
 */
class DisponibilidadeService
{
    /**
     * Ponto de entrada principal.
     *
     * @return array<int, array{hora: string, datetime: string}>
     */
    public function calcular(
        Profissional $profissional,
        int $duracaoMinutos,
        Carbon $data,
        string $horaAbertura,
        string $horaEncerramento,
        int $intervaloMinutos
    ): array {
        // Duração total do atendimento — em multi-serviço é a soma das durações
        $duracao = $duracaoMinutos;
        $dataStr = $data->format('Y-m-d');

        $expedienteInicio = Carbon::parse($dataStr.' '.$horaAbertura);
        $expedienteFim = Carbon::parse($dataStr.' '.$horaEncerramento);

        // Agendamentos ativos do profissional neste dia (com serviço para obter duração)
        $agendamentos = Agendamento::where('profissional_id', $profissional->id)
            ->whereDate('data_hora', $dataStr)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->with('servico')
            ->get();

        // Indisponibilidades do profissional ou de toda a barbearia neste dia
        $indisponibilidades = Indisponibilidade::where(function ($q) use ($profissional) {
            $q->where('profissional_id', $profissional->id)
                ->orWhereNull('profissional_id');
        })
            ->where('inicio', '<', $dataStr.' 23:59:59')
            ->where('fim', '>', $dataStr.' 00:00:00')
            ->get();

        // Dia completamente bloqueado → sem slots
        foreach ($indisponibilidades as $ind) {
            if ($ind->inicio->lte($expedienteInicio) && $ind->fim->gte($expedienteFim)) {
                return [];
            }
        }

        // Horários fixos de mensalistas que bloqueiam este profissional neste dia da semana
        $fixosBloqueados = MensalistaHorarioFixo::where('profissional_id', $profissional->id)
            ->where('dia_semana', $data->dayOfWeek)
            ->where('ativo', true)
            ->pluck('hora')
            ->map(fn ($h) => substr($h, 0, 5))
            ->toArray();

        $horariosTrabalho = $profissional->horarios_trabalho ?? [];

        if (! empty($horariosTrabalho)) {
            return $this->calcularPorLista(
                $horariosTrabalho,
                $agendamentos,
                $fixosBloqueados,
                $indisponibilidades,
                $data,
                $duracao,
                $expedienteFim,
                $intervaloMinutos
            );
        }

        return $this->calcularPorGaps(
            $agendamentos,
            $fixosBloqueados,
            $indisponibilidades,
            $data,
            $duracao,
            $intervaloMinutos,
            $expedienteInicio,
            $expedienteFim
        );
    }

    // ─── Modo Lista ───────────────────────────────────────────────────────────

    /**
     * Usa os horários pré-configurados do profissional como candidatos e descarta
     * os que colidem com agendamentos, estão no passado ou ultrapassam o encerramento.
     */
    private function calcularPorLista(
        array $horariosTrabalho,
        Collection $agendamentos,
        array $fixosBloqueados,
        $indisponibilidades,
        Carbon $data,
        int $duracao,
        Carbon $expedienteFim,
        int $intervaloMinutos
    ): array {
        $dataStr = $data->format('Y-m-d');
        $agora = Carbon::now();
        $slots = [];

        $candidatos = collect($horariosTrabalho)
            ->map(fn ($h) => Carbon::parse($dataStr.' '.$h))
            ->sortBy(fn ($c) => $c->timestamp)
            ->values();

        foreach ($candidatos as $inicio) {
            $fim = $inicio->copy()->addMinutes($duracao);

            if ($fim->gt($expedienteFim)) {
                continue;
            }
            if ($data->isToday() && $inicio->lt($agora->copy()->addMinutes(30))) {
                continue;
            }
            if (in_array($inicio->format('H:i'), $fixosBloqueados)) {
                continue;
            }
            if ($this->temSobreposicao($inicio, $fim, $agendamentos, $intervaloMinutos)) {
                continue;
            }
            if ($this->bloqueadoPorIndisponibilidade($inicio, $fim, $indisponibilidades)) {
                continue;
            }

            $slots[] = ['hora' => $inicio->format('H:i'), 'datetime' => $inicio->format('Y-m-d H:i')];
        }

        return $slots;
    }

    // ─── Modo Gap-Based ───────────────────────────────────────────────────────

    /**
     * Encontra as lacunas livres na agenda e gera slots dentro delas.
     */
    private function calcularPorGaps(
        Collection $agendamentos,
        array $fixosBloqueados,
        $indisponibilidades,
        Carbon $data,
        int $duracao,
        int $intervaloMinutos,
        Carbon $expedienteInicio,
        Carbon $expedienteFim
    ): array {
        $agora = Carbon::now();

        $ocupados = $this->montarIntervalosOcupados(
            $agendamentos,
            $fixosBloqueados,
            $indisponibilidades,
            $data->format('Y-m-d'),
            $intervaloMinutos
        );

        $gaps = $this->encontrarGaps($ocupados, $expedienteInicio, $expedienteFim);
        $slots = [];

        foreach ($gaps as [$gapInicio, $gapFim]) {
            $t = $gapInicio->copy();

            while ($t->copy()->addMinutes($duracao)->lte($gapFim)) {
                // Buffer de 30 min no dia atual
                if (! ($data->isToday() && $t->lt($agora->copy()->addMinutes(30)))) {
                    $slots[] = ['hora' => $t->format('H:i'), 'datetime' => $t->format('Y-m-d H:i')];
                }
                $t->addMinutes($intervaloMinutos);
            }
        }

        return $slots;
    }

    // ─── Helpers de intervalo ─────────────────────────────────────────────────

    /**
     * Constrói a lista de intervalos ocupados a partir de agendamentos e fixos,
     * já mesclados para eliminar sobreposições.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function montarIntervalosOcupados(
        Collection $agendamentos,
        array $fixosBloqueados,
        $indisponibilidades,
        string $dataStr,
        int $intervaloMinutos
    ): array {
        $ocupados = [];

        foreach ($agendamentos as $ag) {
            $inicio = Carbon::parse($ag->data_hora);
            // duracao_total_minutos cobre agendamentos multi-serviço; fallback p/ antigos
            $duracaoAg = $ag->duracao_total_minutos ?? $ag->servico?->duracao_minutos ?? $intervaloMinutos;
            $ocupados[] = [$inicio, $inicio->copy()->addMinutes($duracaoAg)];
        }

        foreach ($fixosBloqueados as $hora) {
            $inicio = Carbon::parse($dataStr.' '.$hora);
            $ocupados[] = [$inicio, $inicio->copy()->addMinutes($intervaloMinutos)];
        }

        foreach ($indisponibilidades as $ind) {
            $ocupados[] = [$ind->inicio->copy(), $ind->fim->copy()];
        }

        usort($ocupados, fn ($a, $b) => $a[0]->lt($b[0]) ? -1 : 1);

        return $this->mesclarIntervalos($ocupados);
    }

    private function bloqueadoPorIndisponibilidade(Carbon $inicio, Carbon $fim, $indisponibilidades): bool
    {
        foreach ($indisponibilidades as $ind) {
            if ($inicio->lt($ind->fim) && $fim->gt($ind->inicio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mescla intervalos sobrepostos ou adjacentes em um conjunto disjunto.
     *
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $intervalos  ordenados por início
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function mesclarIntervalos(array $intervalos): array
    {
        if (empty($intervalos)) {
            return [];
        }

        $merged = [$intervalos[0]];

        for ($i = 1, $n = count($intervalos); $i < $n; $i++) {
            [$inicio, $fim] = $intervalos[$i];
            $ultimo = &$merged[count($merged) - 1];

            if ($inicio->lte($ultimo[1])) {
                // Sobrepõe ou é adjacente: expande o fim se necessário
                if ($fim->gt($ultimo[1])) {
                    $ultimo[1] = $fim;
                }
            } else {
                $merged[] = [$inicio, $fim];
            }
        }

        return $merged;
    }

    /**
     * Retorna as lacunas livres dentro da janela do expediente.
     *
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $ocupados  intervalos mesclados
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function encontrarGaps(
        array $ocupados,
        Carbon $expedienteInicio,
        Carbon $expedienteFim
    ): array {
        $gaps = [];
        $cursor = $expedienteInicio->copy();

        foreach ($ocupados as [$busyInicio, $busyFim]) {
            if ($cursor->lt($busyInicio)) {
                $gaps[] = [$cursor->copy(), $busyInicio->copy()];
            }
            if ($busyFim->gt($cursor)) {
                $cursor = $busyFim->copy();
            }
        }

        // Lacuna final entre o último ocupado e o encerramento
        if ($cursor->lt($expedienteFim)) {
            $gaps[] = [$cursor->copy(), $expedienteFim->copy()];
        }

        return $gaps;
    }

    /**
     * Verifica se [início, fim) se sobrepõe a algum agendamento existente.
     * Overlap clássico: A.início < B.fim AND A.fim > B.início
     */
    private function temSobreposicao(
        Carbon $inicio,
        Carbon $fim,
        Collection $agendamentos,
        int $intervaloMinutos
    ): bool {
        foreach ($agendamentos as $ag) {
            $agInicio = Carbon::parse($ag->data_hora);
            $agFim = $agInicio->copy()->addMinutes($ag->duracao_total_minutos ?? $ag->servico?->duracao_minutos ?? $intervaloMinutos);

            if ($inicio->lt($agFim) && $fim->gt($agInicio)) {
                return true;
            }
        }

        return false;
    }
}
