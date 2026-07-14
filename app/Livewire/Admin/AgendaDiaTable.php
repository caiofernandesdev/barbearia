<?php

namespace App\Livewire\Admin;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Indisponibilidade;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\Carbon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Livewire\Component;

class AgendaDiaTable extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public ?int $profissionalId = null;

    public string $heading = 'Minha Agenda';

    public string $dataSelecionada = '';

    public string $horaSelecionada = '';

    public bool $showModal = false;

    public ?string $clienteNome = '';

    public ?string $clienteTelefone = '';

    public ?string $servicoId = null;

    public function mount(): void
    {
        $this->dataSelecionada = now()->format('Y-m-d');
    }

    public function selecionarDia(string $data): void
    {
        $this->dataSelecionada = $data;
    }

    public function abrirAgendamento(string $hora): void
    {
        $this->horaSelecionada = $hora;
        $this->clienteNome = '';
        $this->clienteTelefone = '';
        $this->servicoId = null;
        $this->showModal = true;
    }

    public function fecharModal(): void
    {
        $this->showModal = false;
    }

    public function salvarAgendamento(): void
    {
        $this->validate([
            'clienteNome' => 'required|max:100',
            'clienteTelefone' => 'required|max:20',
            'servicoId' => 'required|exists:servicos,id',
        ], [
            'clienteNome.required' => 'Informe o nome do cliente.',
            'clienteTelefone.required' => 'Informe o telefone.',
            'servicoId.required' => 'Selecione um serviço.',
        ]);

        $telefone = preg_replace('/\D/', '', $this->clienteTelefone);
        $dataHora = $this->dataSelecionada.' '.$this->horaSelecionada.':00';

        // No painel interno o dono/profissional pode marcar várias sessões para o
        // mesmo cliente (mensalista, sessões futuras). A única trava é o conflito de
        // horário: o slot não pode se sobrepor a outro atendimento (considera a duração).
        $inicio = Carbon::parse($dataHora);
        $duracao = Servico::find($this->servicoId)?->duracao_minutos ?? 30;
        $tenantId = auth('admin')->user()?->tenant_id;

        if (Agendamento::temConflito((int) $this->profissionalId, $inicio, $duracao, $tenantId)) {
            Notification::make()
                ->title('Horário indisponível')
                ->body("O horário {$this->horaSelecionada} conflita com outro atendimento desse profissional.")
                ->danger()
                ->send();

            return;
        }

        // Não deixa marcar por cima de uma indisponibilidade (própria ou do estabelecimento)
        $slotFim = $inicio->copy()->addMinutes($duracao);
        $bloqueado = Indisponibilidade::where('inicio', '<', $slotFim)
            ->where('fim', '>', $inicio)
            ->where(function ($q) {
                $q->whereNull('profissional_id')
                    ->orWhere('profissional_id', $this->profissionalId);
            })
            ->exists();

        if ($bloqueado) {
            Notification::make()
                ->title('Horário indisponível')
                ->body("O horário {$this->horaSelecionada} está marcado como indisponível.")
                ->danger()
                ->send();

            return;
        }

        Agendamento::create([
            'cliente_nome' => $this->clienteNome,
            'cliente_telefone' => $telefone,
            'profissional_id' => $this->profissionalId,
            'servico_id' => $this->servicoId,
            'data_hora' => $dataHora,
            'status' => 'pendente',
            'mensalista' => false,
            'tenant_id' => auth('admin')->user()?->tenant_id,
        ]);

        $this->showModal = false;

        Notification::make()
            ->title('Agendamento criado!')
            ->body($this->clienteNome.' às '.$this->horaSelecionada)
            ->success()
            ->send();
    }

    public function getServicosProperty(): array
    {
        return Servico::where('ativo', true)->orderBy('ordem')->pluck('nome', 'id')->toArray();
    }

    public function getDias(): array
    {
        $dias = [];
        $prof = $this->profissionalId ? Profissional::find($this->profissionalId) : null;
        $diasTrabalho = $prof?->dias_trabalho ?? [1, 2, 3, 4, 5, 6];

        for ($i = 0; $i < 14; $i++) {
            $dia = now()->addDays($i);
            if (! in_array($dia->dayOfWeek, $diasTrabalho)) {
                continue;
            }

            $totalAgs = Agendamento::whereDate('data_hora', $dia->format('Y-m-d'))
                ->when($this->profissionalId, fn ($q) => $q->where('profissional_id', $this->profissionalId))
                ->whereIn('status', ['pendente', 'confirmado'])
                ->count();

            $dias[] = [
                'data' => $dia->format('Y-m-d'),
                'diaSemana' => $dia->locale('pt_BR')->isoFormat('ddd'),
                'diaNum' => $dia->format('d'),
                'mes' => $dia->locale('pt_BR')->isoFormat('MMM'),
                'selecionado' => $dia->format('Y-m-d') === $this->dataSelecionada,
                'totalAgs' => $totalAgs,
            ];
        }

        return $dias;
    }

    public function getSlots(): array
    {
        $config = ConfiguracaoBarbearia::getInstance();
        $data = Carbon::parse($this->dataSelecionada);
        $abertura = $config->horario_abertura ?? '08:00';
        $encerramento = $config->horario_encerramento ?? '19:00';
        $intervalo = $config->intervalo_minutos ?? 60;
        $pid = $this->profissionalId;

        // Intervalos ocupados [início, fim) — um atendimento de 1h bloqueia TODOS
        // os slots que ele cobre (4 slots de 15min, 2 de 30min etc.)
        $ocupados = Agendamento::whereDate('data_hora', $data->format('Y-m-d'))
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->whereIn('status', ['pendente', 'confirmado', 'concluido'])
            ->with(['servico', 'servicos'])
            ->get()
            ->map(function ($a) use ($intervalo) {
                $inicio = Carbon::parse($a->data_hora);
                $duracao = $a->duracao_total_minutos ?? $a->servico?->duracao_minutos ?? $intervalo;

                return ['inicio' => $inicio, 'fim' => $inicio->copy()->addMinutes($duracao), 'ag' => $a];
            });

        // Bloqueios de indisponibilidade que tocam este dia — do próprio profissional
        // ou de todo o estabelecimento (profissional_id nulo). O tenant já é filtrado
        // pelo global scope do BelongsToTenant.
        $diaInicio = Carbon::parse($data->format('Y-m-d').' 00:00:00');
        $diaFim = Carbon::parse($data->format('Y-m-d').' 23:59:59');
        $bloqueios = Indisponibilidade::where('inicio', '<=', $diaFim)
            ->where('fim', '>=', $diaInicio)
            ->where(function ($q) use ($pid) {
                $q->whereNull('profissional_id');
                if ($pid) {
                    $q->orWhere('profissional_id', $pid);
                }
            })
            ->get()
            ->map(fn ($i) => [
                'inicio' => Carbon::parse($i->inicio),
                'fim' => Carbon::parse($i->fim),
                'motivo' => $i->motivo,
            ]);

        $slots = [];
        $cursor = Carbon::parse($data->format('Y-m-d').' '.$abertura);
        $fim = Carbon::parse($data->format('Y-m-d').' '.$encerramento);
        $agora = now();

        while ($cursor->lt($fim)) {
            $slotInicio = $cursor->copy();
            $slotFim = $cursor->copy()->addMinutes($intervalo);

            // Sobreposição: slotInicio < fimAtendimento && slotFim > inicioAtendimento
            $ocupacao = $ocupados->first(fn ($o) => $slotInicio->lt($o['fim']) && $slotFim->gt($o['inicio']));
            $ag = $ocupacao['ag'] ?? null;
            // Primeiro slot do atendimento (os demais são continuação)
            $ehInicio = $ocupacao && $ocupacao['inicio']->between($slotInicio, $slotFim->copy()->subSecond());

            // Slot bloqueado por indisponibilidade (mesma regra de sobreposição)
            $bloqueio = $bloqueios->first(fn ($b) => $slotInicio->lt($b['fim']) && $slotFim->gt($b['inicio']));

            $slots[] = [
                'hora' => $slotInicio->format('H:i'),
                'ocupado' => $ag !== null,
                'indisponivel' => $bloqueio !== null,
                'motivo' => $bloqueio['motivo'] ?? null,
                'passado' => $data->isToday() && $slotInicio->lt($agora),
                'cliente' => $ag?->cliente_nome,
                'servico' => $ag ? ($ehInicio ? $ag->nomesServicos() : '⤷ continuação') : null,
                // Respostas dos campos personalizados só no slot inicial (menos ruído)
                'extras' => $ag && $ehInicio && ! empty($ag->dados_extras)
                    ? collect($ag->dados_extras)->map(fn ($v, $k) => ucfirst(str_replace('_', ' ', $k)).': '.$v)->implode(' · ')
                    : null,
            ];

            $cursor->addMinutes($intervalo);
        }

        return $slots;
    }

    public function render()
    {
        return view('livewire.admin.agenda-dia-table', [
            'dias' => $this->getDias(),
            'slots' => $this->getSlots(),
            'servicos' => $this->servicos,
        ]);
    }
}
