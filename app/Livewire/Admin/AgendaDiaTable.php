<?php

namespace App\Livewire\Admin;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Indisponibilidade;
use App\Models\Mensalista;
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

    public string $heading = 'Minha Agenda';

    public string $dataSelecionada = '';

    public bool $showCancelModal = false;

    public ?int $cancelarId = null;

    public string $cancelarResumo = '';

    public function mount(): void
    {
        $this->dataSelecionada = now()->format('Y-m-d');
    }

    /** Só admin, ou profissional com a permissão "pode cancelar" */
    public function getPodeCancelarProperty(): bool
    {
        return auth('admin')->user()?->podeCancelar() ?? false;
    }

    public function abrirCancelamento(int $agendamentoId): void
    {
        if (! $this->podeCancelar) {
            return;
        }

        $ag = Agendamento::find($agendamentoId);
        // Concluído não se cancela — o atendimento já aconteceu
        if (! $ag || ! in_array($ag->status, ['pendente', 'confirmado'], true)) {
            return;
        }

        $this->cancelarId = $ag->id;
        $this->cancelarResumo = $ag->cliente_nome.' — '.$ag->data_hora->format('d/m \à\s H:i');
        $this->showCancelModal = true;
    }

    public function fecharCancelModal(): void
    {
        $this->showCancelModal = false;
        $this->cancelarId = null;
        $this->cancelarResumo = '';
    }

    public function confirmarCancelamento(): void
    {
        // Revalida no servidor: o modal pode ter sido aberto com estado velho
        if (! $this->podeCancelar || ! $this->cancelarId) {
            $this->fecharCancelModal();

            return;
        }

        $ag = Agendamento::find($this->cancelarId);
        if (! $ag || ! in_array($ag->status, ['pendente', 'confirmado'], true)) {
            $this->fecharCancelModal();

            return;
        }

        // O observer cuida de avisar cliente e profissional por WhatsApp
        $ag->update(['status' => 'cancelado']);

        $cliente = $ag->cliente_nome;
        $this->fecharCancelModal();

        Notification::make()
            ->title('Agendamento cancelado')
            ->body("O horário de {$cliente} foi liberado.")
            ->success()
            ->send();
    }

    public function selecionarDia(string $data): void
    {
        $this->dataSelecionada = $data;
    }

    /**
     * Caixa de agendamento rápido — Filament Action com busca de cliente,
     * múltiplos serviços e telefone opcional.
     */
    public function agendarAction(): Action
    {
        return Action::make('agendar')
            ->modalHeading(fn (array $arguments) => 'Agendar — '.($arguments['hora'] ?? ''))
            ->modalSubmitActionLabel('Confirmar agendamento')
            ->schema([
                Select::make('cliente_id')
                    ->label('Buscar cliente cadastrado')
                    ->placeholder('Digite o nome ou telefone')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Mensalista::query()
                        ->where(fn ($q) => $q->where('nome', 'like', "%{$search}%")->orWhere('telefone', 'like', "%{$search}%"))
                        ->orderBy('nome')->limit(20)->get()
                        ->mapWithKeys(fn ($m) => [$m->id => $m->nome.($m->telefone ? ' · '.$m->telefone : '')])->all())
                    ->getOptionLabelUsing(fn ($value) => Mensalista::find($value)?->nome)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $cliente = $state ? Mensalista::find($state) : null;
                        if ($cliente) {
                            $set('cliente_nome', $cliente->nome);
                            $set('cliente_telefone', $cliente->telefone);
                        }
                    })
                    // Só serve para preencher os campos abaixo; não vai para o $data
                    ->dehydrated(false)
                    ->helperText('Opcional — preenche nome e telefone automaticamente.'),

                TextInput::make('cliente_nome')
                    ->label('Nome do cliente')
                    ->required()
                    ->maxLength(100),

                TextInput::make('cliente_telefone')
                    ->label('Telefone (opcional)')
                    ->tel()
                    ->maxLength(20),

                Select::make('servico_ids')
                    ->label('Serviços')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(Servico::where('ativo', true)->orderBy('ordem')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->nome.' — R$ '.number_format((float) $s->preco, 2, ',', '.')])->all())
                    ->helperText('Pode escolher mais de um.'),
            ])
            ->action(fn (array $data, array $arguments) => $this->criarAgendamentoRapido($arguments['hora'] ?? '', $data));
    }

    /**
     * Cria o agendamento a partir da caixa. Aceita vários serviços (soma
     * duração e valor) e telefone vazio. Mantém as travas de conflito e
     * indisponibilidade.
     */
    private function criarAgendamentoRapido(string $hora, array $data): void
    {
        if ($hora === '') {
            return;
        }

        $tenantId = auth('admin')->user()?->tenant_id;
        $inicio = Carbon::parse($this->dataSelecionada.' '.$hora.':00');

        $servicos = Servico::whereIn('id', $data['servico_ids'] ?? [])->where('ativo', true)->get();
        if ($servicos->isEmpty()) {
            Notification::make()->title('Selecione ao menos um serviço')->danger()->send();

            return;
        }

        $duracao = (int) $servicos->sum('duracao_minutos');
        $slotFim = $inicio->copy()->addMinutes($duracao);

        // Trava 1: não pode sobrepor outro atendimento (considera a duração total)
        if (Agendamento::temConflito((int) $this->profissionalId, $inicio, $duracao, $tenantId)) {
            Notification::make()->title('Horário indisponível')
                ->body("O horário {$hora} conflita com outro atendimento desse profissional.")->danger()->send();

            return;
        }

        // Trava 2: não pode marcar por cima de indisponibilidade
        $bloqueado = Indisponibilidade::where('inicio', '<', $slotFim)
            ->where('fim', '>', $inicio)
            ->where(function ($q) {
                $q->whereNull('profissional_id')->orWhere('profissional_id', $this->profissionalId);
            })
            ->exists();

        if ($bloqueado) {
            Notification::make()->title('Horário indisponível')
                ->body("O horário {$hora} está marcado como indisponível.")->danger()->send();

            return;
        }

        // Telefone é opcional: sem número vira null (atendimento de balcão)
        $telefone = preg_replace('/\D/', '', $data['cliente_telefone'] ?? '') ?: null;

        $agendamento = Agendamento::create([
            'cliente_nome' => $data['cliente_nome'],
            'cliente_telefone' => $telefone,
            'profissional_id' => $this->profissionalId,
            // servico_id = primeiro (retrocompat); todos vão no pivot
            'servico_id' => $servicos->first()->id,
            'valor_total' => $servicos->sum('preco'),
            'duracao_total_minutos' => $duracao,
            'data_hora' => $inicio->format('Y-m-d H:i:s'),
            'status' => 'pendente',
            'mensalista' => false,
            'tenant_id' => $tenantId,
        ]);
        $agendamento->servicos()->attach($servicos->pluck('id')->all());

        Notification::make()->title('Agendamento criado!')
            ->body($data['cliente_nome'].' às '.$hora)->success()->send();
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
                // Clicar no slot ocupado abre o cancelamento — só o que ainda não
                // aconteceu e só para quem tem a permissão
                'agendamento_id' => $ag?->id,
                'cancelavel' => $ag !== null
                    && in_array($ag->status, ['pendente', 'confirmado'], true)
                    && $this->podeCancelar,
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
        ]);
    }
}
