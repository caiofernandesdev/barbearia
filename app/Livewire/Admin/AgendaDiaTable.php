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

    public string $heading = '';

    public string $dataSelecionada = '';

    /** 'slots' (grade) ou 'agenda' (timeline por horário) */
    public string $modoAgenda = 'slots';

    public bool $showCancelModal = false;

    public ?int $cancelarId = null;

    public string $cancelarResumo = '';

    public function mount(): void
    {
        // Título padrão traduzido (não sobrescreve um heading passado pela página)
        $this->heading = $this->heading ?: __('painel.agenda.titulo');
        $this->dataSelecionada = now()->format('Y-m-d');
    }

    /** Só admin, ou profissional com a permissão "pode cancelar" */
    public function getPodeCancelarProperty(): bool
    {
        return auth('admin')->user()?->podeCancelar() ?? false;
    }

    /** Pode criar indisponibilidade pela agenda (permissão + feature do plano) */
    public function getPodeIndisponibilidadeProperty(): bool
    {
        if (! auth('admin')->user()?->temPermissao('indisponibilidades')) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('indisponibilidades') ?? false;
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
            ->title(__('painel.agenda.n_cancelado'))
            ->body(__('painel.agenda.n_cancelado_body', ['nome' => $cliente]))
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
            ->modalHeading(fn (array $arguments) => __('painel.agenda.ag_heading', ['hora' => $arguments['hora'] ?? '']))
            // Slot já passou: avisa mas deixa marcar (registro de balcão/atraso)
            ->modalDescription(fn (array $arguments) => ($arguments['passado'] ?? false)
                ? __('painel.agenda.ag_passado_desc')
                : null)
            ->modalSubmitActionLabel(__('painel.agenda.ag_submit'))
            ->schema([
                Select::make('cliente_id')
                    ->label(__('painel.agenda.ag_buscar'))
                    ->placeholder(__('painel.agenda.ag_buscar_ph'))
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
                    ->helperText(__('painel.agenda.ag_buscar_help')),

                TextInput::make('cliente_nome')
                    ->label(__('painel.agenda.ag_nome'))
                    ->required()
                    ->maxLength(100),

                TextInput::make('cliente_telefone')
                    ->label(__('painel.agendamento.telefone'))
                    // Sem ->tel(): a validação de formato do Filament rejeita
                    // número colado/autopreenchido no iOS (caractere invisível).
                    // O campo é livre e limpamos para só dígitos ao salvar.
                    ->extraInputAttributes(['inputmode' => 'tel'])
                    ->maxLength(30),

                Select::make('servico_ids')
                    ->label(__('painel.agendamento.servicos'))
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(Servico::where('ativo', true)->orderBy('ordem')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->nome.' — R$ '.number_format((float) $s->preco, 2, ',', '.')])->all())
                    ->helperText(__('painel.agenda.ag_servicos_help')),
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
            Notification::make()->title(__('painel.agenda.n_selecione_servico'))->danger()->send();

            return;
        }

        $duracao = (int) $servicos->sum('duracao_minutos');
        $slotFim = $inicio->copy()->addMinutes($duracao);

        // Trava 1: não pode sobrepor outro atendimento (considera a duração total)
        if (Agendamento::temConflito((int) $this->profissionalId, $inicio, $duracao, $tenantId)) {
            Notification::make()->title(__('painel.agenda.n_indisponivel'))
                ->body(__('painel.agenda.n_conflito_body', ['hora' => $hora]))->danger()->send();

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
            Notification::make()->title(__('painel.agenda.n_indisponivel'))
                ->body(__('painel.agenda.n_bloqueado_body', ['hora' => $hora]))->danger()->send();

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
            // Preço do dia do slot (serviço pode cobrar diferente por dia)
            'valor_total' => $servicos->sum(fn ($s) => $s->precoNoDia($inicio->dayOfWeek)),
            'duracao_total_minutos' => $duracao,
            'data_hora' => $inicio->format('Y-m-d H:i:s'),
            'status' => 'pendente',
            'mensalista' => false,
            'tenant_id' => $tenantId,
        ]);
        $agendamento->servicos()->attach($servicos->pluck('id')->all());

        Notification::make()->title(__('painel.agenda.n_criado'))
            ->body(__('painel.agenda.n_criado_body', ['nome' => $data['cliente_nome'], 'hora' => $hora]))->success()->send();
    }

    /**
     * Inverter agendamentos — troca o horário de dois clientes entre si.
     * Ex.: dois clientes combinaram de trocar de horário. Selecione os dois
     * agendamentos e o sistema troca as datas (o observer avisa os clientes).
     */
    public function inverterAction(): Action
    {
        return Action::make('inverter')
            ->modalHeading(__('painel.agenda.inv_heading'))
            ->modalDescription(__('painel.agenda.inv_desc'))
            ->modalSubmitActionLabel(__('painel.agenda.inv_submit'))
            ->modalIcon('heroicon-o-arrows-right-left')
            ->schema([
                Select::make('agendamento_a')
                    ->label(__('painel.agenda.inv_ag1'))
                    ->required()
                    ->searchable()
                    ->options(fn () => $this->opcoesAgendamentos())
                    ->helperText(__('painel.agenda.inv_ag_help')),

                Select::make('agendamento_b')
                    ->label(__('painel.agenda.inv_ag2'))
                    ->required()
                    ->searchable()
                    ->different('agendamento_a')
                    ->options(fn () => $this->opcoesAgendamentos()),
            ])
            ->action(fn (array $data) => $this->inverterAgendamentos((int) $data['agendamento_a'], (int) $data['agendamento_b']));
    }

    /**
     * Bloquear horário (indisponibilidade) direto da agenda, já pegando o dia
     * selecionado. Praticidade: evita ir até o recurso de Indisponibilidades.
     */
    public function indisponibilidadeAction(): Action
    {
        return Action::make('indisponibilidade')
            ->modalHeading(fn () => __('painel.agenda.ind_heading', ['data' => Carbon::parse($this->dataSelecionada)->locale(app()->getLocale())->isoFormat('ddd, D MMM')]))
            ->modalDescription(__('painel.agenda.ind_desc'))
            ->modalSubmitActionLabel(__('painel.agenda.ind_submit'))
            ->modalIcon('heroicon-o-lock-closed')
            ->schema([
                Select::make('profissional_id')
                    ->label(__('painel.agendamento.profissional'))
                    ->options(fn () => Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all())
                    ->default($this->profissionalId)
                    ->placeholder(__('painel.agenda.ind_prof_ph'))
                    ->helperText(__('painel.agenda.ind_prof_help')),

                TextInput::make('hora_inicio')
                    ->label(__('painel.agenda.ind_inicio'))
                    ->type('time')
                    ->required()
                    ->default('09:00'),

                TextInput::make('hora_fim')
                    ->label(__('painel.agenda.ind_fim'))
                    ->type('time')
                    ->required()
                    ->default('10:00'),

                TextInput::make('motivo')
                    ->label(__('painel.agenda.ind_motivo'))
                    ->placeholder(__('painel.agenda.ind_motivo_ph'))
                    ->maxLength(255),
            ])
            ->action(fn (array $data) => $this->criarIndisponibilidade($data));
    }

    private function criarIndisponibilidade(array $data): void
    {
        if (! $this->podeIndisponibilidade) {
            return;
        }

        $inicio = Carbon::parse($this->dataSelecionada.' '.$data['hora_inicio']);
        $fim = Carbon::parse($this->dataSelecionada.' '.$data['hora_fim']);

        if ($fim->lte($inicio)) {
            Notification::make()->title(__('painel.agenda.ind_fim_antes'))->danger()->send();

            return;
        }

        Indisponibilidade::create([
            'profissional_id' => $data['profissional_id'] ?: null,
            'inicio' => $inicio,
            'fim' => $fim,
            'motivo' => $data['motivo'] ?: null,
            'tenant_id' => auth('admin')->user()?->tenant_id,
        ]);

        Notification::make()->title(__('painel.agenda.ind_bloqueado'))
            ->body($inicio->format('H:i').' – '.$fim->format('H:i'))
            ->success()->send();
    }

    /** Agendamentos ativos (hoje em diante) para escolher na troca. */
    private function opcoesAgendamentos(): array
    {
        return Agendamento::query()
            ->when($this->profissionalId, fn ($q) => $q->where('profissional_id', $this->profissionalId))
            ->whereIn('status', ['pendente', 'confirmado'])
            ->where('data_hora', '>=', now()->startOfDay())
            ->orderBy('data_hora')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($a) => [
                $a->id => $a->data_hora->format('d/m H:i').' — '.$a->cliente_nome,
            ])->all();
    }

    private function inverterAgendamentos(int $idA, int $idB): void
    {
        if ($idA === $idB) {
            Notification::make()->title(__('painel.agenda.inv_diferentes'))->danger()->send();

            return;
        }

        $a = Agendamento::find($idA);
        $b = Agendamento::find($idB);

        // Só troca agendamentos ativos (não faz sentido em cancelado/concluído)
        foreach ([$a, $b] as $ag) {
            if (! $ag || ! in_array($ag->status, ['pendente', 'confirmado'], true)) {
                Notification::make()->title(__('painel.agenda.inv_invalido'))
                    ->body(__('painel.agenda.inv_invalido_body'))->danger()->send();

                return;
            }
        }

        $tempoA = $a->data_hora->copy();
        $tempoB = $b->data_hora->copy();

        // Durações diferentes podem gerar sobreposição com vizinhos: valida os
        // novos horários ignorando os dois que estão trocando entre si.
        if ($this->conflitoNaTroca($a, $tempoB, [$a->id, $b->id])
            || $this->conflitoNaTroca($b, $tempoA, [$a->id, $b->id])) {
            Notification::make()->title(__('painel.agenda.inv_conflito'))
                ->body(__('painel.agenda.inv_conflito_body'))
                ->danger()->send();

            return;
        }

        // Cada save dispara o observer → cliente e barbeiro recebem "reagendado"
        $a->update(['data_hora' => $tempoB]);
        $b->update(['data_hora' => $tempoA]);

        Notification::make()->title(__('painel.agenda.inv_trocado'))
            ->body(__('painel.agenda.inv_trocado_body', ['a' => $a->cliente_nome, 'b' => $b->cliente_nome]))
            ->success()->send();
    }

    /** Há conflito ao mover $ag para $novoInicio, ignorando os ids em troca? */
    private function conflitoNaTroca(Agendamento $ag, Carbon $novoInicio, array $ignorarIds): bool
    {
        $dur = (int) ($ag->duracao_total_minutos ?? 30);
        $fim = $novoInicio->copy()->addMinutes(max(1, $dur));

        return Agendamento::withoutGlobalScopes()
            ->where('profissional_id', $ag->profissional_id)
            ->where('tenant_id', $ag->tenant_id)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->whereDate('data_hora', $novoInicio->toDateString())
            ->whereNotIn('id', $ignorarIds)
            ->with('servico')
            ->get()
            ->contains(function ($outro) use ($novoInicio, $fim) {
                $oi = Carbon::parse($outro->data_hora);
                $of = $oi->copy()->addMinutes((int) ($outro->duracao_total_minutos ?? $outro->servico?->duracao_minutos ?? 30));

                return $novoInicio->lt($of) && $fim->gt($oi);
            });
    }

    public function getDias(): array
    {
        $dias = [];
        $prof = $this->profissionalId ? Profissional::find($this->profissionalId) : null;
        $diasTrabalho = $prof?->dias_trabalho ?? [1, 2, 3, 4, 5, 6];

        // Carrossel do painel: 7 dias anteriores (para rever/registrar atrasados)
        // até 21 dias à frente. Começa no passado; o JS rola até o dia selecionado.
        $hoje = now()->startOfDay();
        for ($i = -7; $i <= 21; $i++) {
            $dia = $hoje->copy()->addDays($i);
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
                'passado' => $i < 0,
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

        // A agenda cobre o expediente do estabelecimento, MAS se estende para
        // incluir os horários específicos que o profissional configurou — senão
        // horários fora da janela padrão não apareceriam aqui.
        $prof = $pid ? Profissional::find($pid) : null;
        $horariosProf = $prof ? $prof->horariosDoDia($data->dayOfWeek) : [];
        if (! empty($horariosProf)) {
            $marcos = collect($horariosProf)
                ->map(fn ($h) => Carbon::parse($data->format('Y-m-d').' '.$h))
                ->sort()->values();
            if ($marcos->first()->lt($cursor)) {
                $cursor = $marcos->first()->copy();
            }
            $ultimoFim = $marcos->last()->copy()->addMinutes($intervalo);
            if ($ultimoFim->gt($fim)) {
                $fim = $ultimoFim->copy();
            }
        }

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

    private function hhmmParaMin(string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, '0');

        return (int) $h * 60 + (int) $m;
    }

    /**
     * Vista de agenda (timeline): cada agendamento vira um bloco posicionado
     * pela hora de início, com altura proporcional à duração. Inclui as marcas
     * de hora e a linha do "agora". Posições em pixels (pxPorMin).
     */
    public function getTimeline(): array
    {
        $config = ConfiguracaoBarbearia::getInstance();
        $data = Carbon::parse($this->dataSelecionada);
        $dataStr = $data->format('Y-m-d');
        $pid = $this->profissionalId;
        $intervalo = (int) ($config->intervalo_minutos ?? 60);
        $pxPorMin = 2.2; // ~132px por hora — respiro confortável no mobile

        $ags = Agendamento::whereDate('data_hora', $dataStr)
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->whereIn('status', ['pendente', 'confirmado', 'concluido'])
            ->with(['servico', 'servicos'])
            ->orderBy('data_hora')
            ->get();

        // Janela do dia: cobre a config, os horários do profissional e os agendamentos
        $aberturaMin = $this->hhmmParaMin($config->horario_abertura ?? '08:00');
        $encerraMin = $this->hhmmParaMin($config->horario_encerramento ?? '19:00');

        $prof = $pid ? Profissional::find($pid) : null;
        foreach (($prof ? $prof->horariosDoDia($data->dayOfWeek) : []) as $h) {
            $m = $this->hhmmParaMin($h);
            $aberturaMin = min($aberturaMin, $m);
            $encerraMin = max($encerraMin, $m + $intervalo);
        }
        foreach ($ags as $a) {
            $ini = Carbon::parse($a->data_hora);
            $iniMin = $ini->hour * 60 + $ini->minute;
            $dur = (int) ($a->duracao_total_minutos ?? $a->servico?->duracao_minutos ?? $intervalo);
            $aberturaMin = min($aberturaMin, $iniMin);
            $encerraMin = max($encerraMin, $iniMin + $dur);
        }

        $inicioMin = intdiv($aberturaMin, 60) * 60;          // arredonda p/ hora cheia
        $fimMin = (int) (ceil($encerraMin / 60) * 60);

        $blocos = [];
        foreach ($ags as $a) {
            $ini = Carbon::parse($a->data_hora);
            $iniMin = $ini->hour * 60 + $ini->minute;
            $dur = (int) ($a->duracao_total_minutos ?? $a->servico?->duracao_minutos ?? $intervalo);
            $blocos[] = [
                'id' => $a->id,
                'top' => round(($iniMin - $inicioMin) * $pxPorMin, 1),
                'height' => max(40.0, round($dur * $pxPorMin, 1)),
                'inicio' => $ini->format('H:i'),
                'fim' => $ini->copy()->addMinutes($dur)->format('H:i'),
                'cliente' => $a->cliente_nome,
                'servico' => $a->nomesServicos(),
                'status' => $a->status,
                'cancelavel' => in_array($a->status, ['pendente', 'confirmado'], true) && $this->podeCancelar,
            ];
        }

        $horas = [];
        for ($m = $inicioMin; $m <= $fimMin; $m += 60) {
            $horas[] = [
                'label' => sprintf('%02d:00', intdiv($m, 60)),
                'top' => round(($m - $inicioMin) * $pxPorMin, 1),
            ];
        }

        // Listras intermediárias a cada intervalo de agendamento (ex.: 10 em 10min),
        // fora as que caem na hora cheia (essas já têm a linha + rótulo).
        $subLinhas = [];
        for ($m = $inicioMin; $m <= $fimMin; $m += max(5, $intervalo)) {
            if ($m % 60 !== 0) {
                $subLinhas[] = round(($m - $inicioMin) * $pxPorMin, 1);
            }
        }

        $agoraTop = null;
        $agoraLabel = null;
        if ($data->isToday()) {
            $agora = now();
            $agoraMin = $agora->hour * 60 + $agora->minute;
            if ($agoraMin >= $inicioMin && $agoraMin <= $fimMin) {
                $agoraTop = round(($agoraMin - $inicioMin) * $pxPorMin, 1);
                $agoraLabel = $agora->format('H:i');
            }
        }

        return [
            'inicioMin' => $inicioMin,
            'pxPorMin' => $pxPorMin,
            'alturaTotal' => round(($fimMin - $inicioMin) * $pxPorMin, 1),
            'horas' => $horas,
            'subLinhas' => $subLinhas,
            'blocos' => $blocos,
            'agoraTop' => $agoraTop,
            'agoraLabel' => $agoraLabel,
        ];
    }

    public function render()
    {
        return view('livewire.admin.agenda-dia-table', [
            'dias' => $this->getDias(),
            'slots' => $this->modoAgenda === 'slots' ? $this->getSlots() : [],
            'timeline' => $this->modoAgenda === 'agenda' ? $this->getTimeline() : null,
        ]);
    }
}
