<?php

namespace App\Livewire\Admin;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Livewire\Component;

class AgendaDiaTable extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public ?int $profissionalId = null;
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
            'clienteNome'     => 'required|max:100',
            'clienteTelefone' => 'required|max:20',
            'servicoId'       => 'required|exists:servicos,id',
        ], [
            'clienteNome.required'     => 'Informe o nome do cliente.',
            'clienteTelefone.required' => 'Informe o telefone.',
            'servicoId.required'       => 'Selecione um serviço.',
        ]);

        $telefone = preg_replace('/\D/', '', $this->clienteTelefone);
        $dataHora = $this->dataSelecionada . ' ' . $this->horaSelecionada . ':00';

        // Regra: 1 agendamento ativo por cliente
        $ativo = Agendamento::where('cliente_telefone', $telefone)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->first();

        if ($ativo) {
            $quando = $ativo->data_hora->format('d/m/Y H:i');
            Notification::make()
                ->title('Cliente já tem agendamento ativo')
                ->body("{$ativo->cliente_nome} já tem agendamento em {$quando}. Cancele antes de criar outro.")
                ->danger()
                ->send();
            return;
        }

        // Regra: slot não pode estar ocupado
        $slotOcupado = Agendamento::where('profissional_id', $this->profissionalId)
            ->where('data_hora', $dataHora)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->exists();

        if ($slotOcupado) {
            Notification::make()
                ->title('Horário já ocupado')
                ->body("O horário {$this->horaSelecionada} já tem um agendamento.")
                ->danger()
                ->send();
            return;
        }

        Agendamento::create([
            'cliente_nome'     => $this->clienteNome,
            'cliente_telefone' => $telefone,
            'profissional_id'  => $this->profissionalId,
            'servico_id'       => $this->servicoId,
            'data_hora'        => $dataHora,
            'status'           => 'pendente',
            'mensalista'       => false,
            'tenant_id'        => auth('admin')->user()?->tenant_id,
        ]);

        $this->showModal = false;

        Notification::make()
            ->title('Agendamento criado!')
            ->body($this->clienteNome . ' às ' . $this->horaSelecionada)
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
            if (! in_array($dia->dayOfWeek, $diasTrabalho)) continue;

            $totalAgs = Agendamento::whereDate('data_hora', $dia->format('Y-m-d'))
                ->when($this->profissionalId, fn ($q) => $q->where('profissional_id', $this->profissionalId))
                ->whereIn('status', ['pendente', 'confirmado'])
                ->count();

            $dias[] = [
                'data'        => $dia->format('Y-m-d'),
                'diaSemana'   => $dia->locale('pt_BR')->isoFormat('ddd'),
                'diaNum'      => $dia->format('d'),
                'mes'         => $dia->locale('pt_BR')->isoFormat('MMM'),
                'selecionado' => $dia->format('Y-m-d') === $this->dataSelecionada,
                'totalAgs'    => $totalAgs,
            ];
        }

        return $dias;
    }

    public function getSlots(): array
    {
        $config       = ConfiguracaoBarbearia::getInstance();
        $data         = Carbon::parse($this->dataSelecionada);
        $abertura     = $config->horario_abertura ?? '08:00';
        $encerramento = $config->horario_encerramento ?? '19:00';
        $intervalo    = $config->intervalo_minutos ?? 60;
        $pid          = $this->profissionalId;

        $agendados = Agendamento::whereDate('data_hora', $data->format('Y-m-d'))
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->whereIn('status', ['pendente', 'confirmado', 'concluido'])
            ->with('servico')
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->data_hora)->format('H:i'));

        $slots  = [];
        $cursor = Carbon::parse($data->format('Y-m-d') . ' ' . $abertura);
        $fim    = Carbon::parse($data->format('Y-m-d') . ' ' . $encerramento);
        $agora  = now();

        while ($cursor->lt($fim)) {
            $hora    = $cursor->format('H:i');
            $ag      = $agendados->get($hora);
            $passado = $data->isToday() && $cursor->lt($agora);

            $slots[] = [
                'hora'    => $hora,
                'ocupado' => $ag !== null,
                'passado' => $passado,
                'cliente' => $ag?->cliente_nome,
                'servico' => $ag?->servico?->nome,
            ];

            $cursor->addMinutes($intervalo);
        }

        return $slots;
    }

    public function render()
    {
        return view('livewire.admin.agenda-dia-table', [
            'dias'     => $this->getDias(),
            'slots'    => $this->getSlots(),
            'servicos' => $this->servicos,
        ]);
    }
}
