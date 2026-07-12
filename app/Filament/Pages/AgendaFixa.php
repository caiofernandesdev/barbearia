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
 * Agenda Fixa — monta o mês de um cliente mensalista escolhendo, em cada
 * ocorrência de um dia da semana, qual serviço ele fará. Gera os agendamentos
 * (o hook do model decide pendente/confirmado conforme o módulo WhatsApp).
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
        // Admin (dono) e barbeiro montam a agenda fixa dos clientes
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

    public string $hora = '09:00';

    public ?int $diaSemana = null;

    /** ['Y-m-d' => servico_id|null] — serviço escolhido em cada ocorrência. */
    public array $servicoPorData = [];

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
        // Pré-seleciona o cliente quando vem do botão na tabela de mensalistas
        if ($id = request('mensalista')) {
            $this->mensalistaId = (int) $id;
        }
    }

    // Ao trocar mês/dia, reseta os serviços escolhidos (as datas mudam)
    public function updatedMes(): void
    {
        $this->servicoPorData = [];
    }

    public function updatedDiaSemana(): void
    {
        $this->servicoPorData = [];
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
                $datas[] = [
                    'data' => $d->format('Y-m-d'),
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
            'hora' => 'required',
            'diaSemana' => 'required|integer',
        ], [], [
            'mensalistaId' => 'cliente',
            'profissionalId' => 'profissional',
            'diaSemana' => 'dia da semana',
        ]);

        $mensalista = Mensalista::findOrFail($this->mensalistaId);
        $escolhidos = array_filter($this->servicoPorData); // só datas com serviço

        if ($escolhidos === []) {
            Notification::make()->title('Nenhum serviço selecionado')->warning()->send();

            return;
        }

        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : null;
        $criados = 0;
        $pulados = 0;

        foreach ($escolhidos as $data => $servicoId) {
            $dataHora = Carbon::createFromFormat('Y-m-d H:i', $data.' '.substr($this->hora, 0, 5));

            // Não duplica: mesmo cliente, mesmo horário exato
            $existe = Agendamento::where('cliente_telefone', $mensalista->telefone)
                ->where('data_hora', $dataHora)
                ->whereIn('status', ['pendente', 'confirmado'])
                ->exists();

            if ($existe) {
                $pulados++;

                continue;
            }

            // status omitido: o hook do model decide pendente (WhatsApp on) ou confirmado (off)
            Agendamento::create([
                'cliente_nome' => $mensalista->nome,
                'cliente_telefone' => $mensalista->telefone,
                'profissional_id' => $this->profissionalId,
                'servico_id' => (int) $servicoId,
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
    }
}
