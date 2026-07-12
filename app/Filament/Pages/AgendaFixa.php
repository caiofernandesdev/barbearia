<?php

namespace App\Filament\Pages;

use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Agenda Fixa — monta o mês de um cliente escolhendo, em cada ocorrência de um
 * dia da semana, o serviço E o horário. O padrão montado vira um ciclo que pode
 * ser repetido por vários meses, mantendo a alternância contínua (ex: unha /
 * reparo quinzenal, com horário que desliza conforme a duração).
 *
 * Gera agendamentos reais (o hook do model decide pendente/confirmado conforme
 * o módulo WhatsApp). Não duplica os que já existem.
 */
class AgendaFixa extends Page
{
    protected string $view = 'filament.pages.agenda-fixa';

    protected static ?string $navigationLabel = 'Agenda Fixa';

    protected static ?string $title = 'Agenda Fixa';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return 'Clientes';
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();
        if (! $u || ! ($u->isAdmin() || $u->isBarbeiro())) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('agenda_fixa') ?? false;
    }

    // ─── Estado (Livewire) ────────────────────────────────────────────────────

    public ?int $mensalistaId = null;

    public string $mes = '';

    public ?int $profissionalId = null;

    /** Horário padrão que preenche todas as ocorrências (cada uma pode ser ajustada). */
    public string $horaPadrao = '09:00';

    public ?int $diaSemana = null;

    /** Quantos meses gerar, contando a partir do mês escolhido (1 = só ele). */
    public int $repetirMeses = 1;

    /** ['Y-m-d' => servico_id|null] — serviço escolhido em cada ocorrência. */
    public array $servicoPorData = [];

    /** ['Y-m-d' => 'H:i'] — horário de cada ocorrência (default = horaPadrao). */
    public array $horaPorData = [];

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
        if ($id = request('mensalista')) {
            $this->mensalistaId = (int) $id;
        }
    }

    // Ao trocar mês/dia, as datas mudam → zera o que foi montado
    public function updatedMes(): void
    {
        $this->servicoPorData = [];
        $this->horaPorData = [];
    }

    public function updatedDiaSemana(): void
    {
        $this->servicoPorData = [];
        $this->horaPorData = [];
    }

    // "Horário padrão" preenche todas as ocorrências de uma vez
    public function updatedHoraPadrao(): void
    {
        foreach ($this->ocorrencias as $oc) {
            $this->horaPorData[$oc['data']] = $this->horaPadrao;
        }
    }

    // ─── Opções dos selects ───────────────────────────────────────────────────

    public function getMensalistasProperty(): array
    {
        return Mensalista::orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    public function getProfissionaisProperty(): array
    {
        return Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    public function getServicosProperty(): array
    {
        return Servico::where('ativo', true)->orderBy('ordem')->pluck('nome', 'id')->toArray();
    }

    public function getMesesProperty(): array
    {
        $meses = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < 6; $i++) {
            $meses[$cursor->format('Y-m')] = ucfirst($cursor->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
            $cursor->addMonth();
        }

        return $meses;
    }

    public function getDiasSemanaProperty(): array
    {
        return [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];
    }

    /** Datas do mês selecionado que caem no dia da semana escolhido. */
    public function getOcorrenciasProperty(): array
    {
        if ($this->diaSemana === null || $this->mes === '') {
            return [];
        }

        $inicio = Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();
        $datas = [];

        for ($d = $inicio->copy(); $d->lte($fim); $d->addDay()) {
            if ($d->dayOfWeek === (int) $this->diaSemana) {
                $key = $d->format('Y-m-d');
                // Garante um horário padrão para a linha
                if (! isset($this->horaPorData[$key])) {
                    $this->horaPorData[$key] = $this->horaPadrao;
                }
                $datas[] = [
                    'data' => $key,
                    'label' => $d->locale('pt_BR')->isoFormat('DD/MM (ddd)'),
                    'semana' => (int) ceil($d->day / 7),
                ];
            }
        }

        return $datas;
    }

    // ─── Ação ─────────────────────────────────────────────────────────────────

    public function gerar(): void
    {
        $this->validate([
            'mensalistaId' => 'required|integer',
            'profissionalId' => 'required|integer',
            'diaSemana' => 'required|integer',
            'repetirMeses' => 'required|integer|min:1|max:12',
        ], [], [
            'mensalistaId' => 'cliente',
            'profissionalId' => 'profissional',
            'diaSemana' => 'dia da semana',
            'repetirMeses' => 'meses',
        ]);

        $mensalista = Mensalista::findOrFail($this->mensalistaId);

        // Monta a lista de (data, serviço, hora) a criar.
        $planejados = $this->repetirMeses <= 1
            ? $this->planejarMesBase()   // respeita exatamente o montado (inclui "não vem")
            : $this->planejarComCiclo(); // repete o padrão continuamente pelos meses

        if ($planejados === []) {
            Notification::make()->title('Nenhum serviço selecionado')->warning()->send();

            return;
        }

        $agora = now();
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : null;
        $criados = 0;
        $pulados = 0;

        foreach ($planejados as $pl) {
            $dataHora = Carbon::parse($pl['data'].' '.$pl['hora']);
            if ($dataHora->lte($agora)) {
                continue; // passado
            }

            $existe = Agendamento::where('cliente_telefone', $mensalista->telefone)
                ->where('data_hora', $dataHora)
                ->whereIn('status', ['pendente', 'confirmado'])
                ->exists();

            if ($existe) {
                $pulados++;

                continue;
            }

            Agendamento::create([
                'cliente_nome' => $mensalista->nome,
                'cliente_telefone' => $mensalista->telefone,
                'profissional_id' => $this->profissionalId,
                'servico_id' => $pl['servico'],
                'data_hora' => $dataHora,
                'mensalista' => true,
                'mensalista_id' => $mensalista->id,
                'tenant_id' => $tenantId,
            ]);
            $criados++;
        }

        Notification::make()
            ->title("Agenda fixa gerada: {$criados} agendamento(s)")
            ->body($pulados > 0 ? "{$pulados} já existiam e foram mantidos." : 'Aparecem na agenda e nos relatórios.')
            ->success()
            ->send();

        $this->servicoPorData = [];
        $this->horaPorData = [];
    }

    /** Só o mês base: exatamente as datas preenchidas (as "não vem" ficam de fora). */
    private function planejarMesBase(): array
    {
        $planejados = [];
        foreach ($this->ocorrencias as $oc) {
            $servicoId = $this->servicoPorData[$oc['data']] ?? null;
            if ($servicoId) {
                $planejados[] = [
                    'data' => $oc['data'],
                    'servico' => (int) $servicoId,
                    'hora' => substr($this->horaPorData[$oc['data']] ?? $this->horaPadrao, 0, 5),
                ];
            }
        }

        return $planejados;
    }

    /**
     * Repetição: o padrão montado (serviço+hora das semanas preenchidas) vira um
     * ciclo aplicado continuamente a cada ocorrência do dia da semana na janela,
     * ancorado no início do mês base — mantém a alternância na virada do mês.
     */
    private function planejarComCiclo(): array
    {
        $ciclo = $this->planejarMesBase();
        if ($ciclo === []) {
            return [];
        }

        $inicio = Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfMonth();
        $fim = $inicio->copy()->addMonths($this->repetirMeses - 1)->endOfMonth();

        $planejados = [];
        $i = 0;
        for ($d = $inicio->copy(); $d->lte($fim); $d->addDay()) {
            if ($d->dayOfWeek !== (int) $this->diaSemana) {
                continue;
            }
            $item = $ciclo[$i % count($ciclo)];
            $i++;
            $planejados[] = [
                'data' => $d->format('Y-m-d'),
                'servico' => $item['servico'],
                'hora' => $item['hora'],
            ];
        }

        return $planejados;
    }
}
