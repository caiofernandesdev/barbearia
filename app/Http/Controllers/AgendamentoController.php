<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\CampoPersonalizado;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Mensalista;
use App\Models\MensalistaHorarioFixo;
use App\Models\Profissional;
use App\Models\Servico;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendamentoController extends Controller
{
    public function index(Request $request)
    {
        $config = ConfiguracaoBarbearia::getInstance();
        return view('pages.agendamento.index', [
            'nomeBarbearia' => $config->nome_barbearia ?? 'Barbearia',
            'logoUrl'       => $config->logo ? url('storage/' . $config->logo) : null,
            'tenantSlug'    => $request->route('tenant'),
        ]);
    }

    // ─── APIs públicas ────────────────────────────────────────────────────────

    /**
     * Retorna profissionais ativos com URL pública da foto.
     */
    public function profissionais(): JsonResponse
    {
        $profissionais = Profissional::where('ativo', true)->get()->map(fn($p) => [
            'id'           => $p->id,
            'nome'         => $p->nome,
            'foto_url'     => $p->foto ? url('storage/' . $p->foto) : null,
            'dias_trabalho' => $p->dias_trabalho ?? [1, 2, 3, 4, 5, 6],
        ]);

        return response()->json($profissionais);
    }

    /**
     * Retorna serviços ativos ordenados pelo campo 'ordem'.
     */
    public function servicos(): JsonResponse
    {
        return response()->json(
            Servico::where('ativo', true)->orderBy('ordem')->get()
                ->map(fn ($s) => array_merge($s->toArray(), [
                    'foto_url' => $s->foto ? url('storage/' . $s->foto) : null,
                ]))
        );
    }

    public function camposExtras(): JsonResponse
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $tenant?->hasFeature('campos_agendamento')) {
            return response()->json([]);
        }

        return response()->json(
            CampoPersonalizado::where('ativo', true)
                ->orderBy('ordem')
                ->get(['nome', 'slug', 'tipo', 'opcoes', 'obrigatorio'])
        );
    }

    /**
     * Verifica o telefone e retorna o tipo do cliente com as regras aplicáveis.
     *
     * ── Regras de negócio ────────────────────────────────────────────────────
     *
     * AVULSO (não cadastrado em mensalistas):
     *   - Pode agendar livremente
     *   - Só pode ter 1 agendamento ativo (pendente/confirmado) por vez
     *   - Para remarcar: precisa cancelar ou concluir o atual
     *
     * MENSALISTA (tipo = 'mensalista'):
     *   - Tem limite de cortes por semana (individual ou global da barbearia)
     *   - Bloqueado se já atingiu o limite semanal
     *   - Bloqueado se já tem agendamento ativo (mesma regra do avulso)
     *
     * MENSALISTA FIXO (tipo = 'mensalista_fixo'):
     *   - Possui horários fixos semanais cadastrados pelo dono
     *   - Ao identificar o telefone, mostra os próximos horários fixos em modal
     *   - PODE agendar avulso, mas o agendamento é marcado como is_avulso_mensalista_fixo
     *   - O dono vê esse alerta no painel admin
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function verificarTelefone(Request $request): JsonResponse
    {
        $telefone = preg_replace('/\D/', '', $request->telefone);

        // Verifica se o telefone pertence a um mensalista cadastrado
        $mensalista = Mensalista::where('telefone', $telefone)->first();

        if ($mensalista) {

            // ── Mensalista Fixo ───────────────────────────────────────────────
            if ($mensalista->tipo === 'mensalista_fixo') {
                $proximos = $this->gerarProximosHorariosFixos($mensalista, 30);
                $temAtivo = Agendamento::where('cliente_telefone', $telefone)
                    ->whereIn('status', ['pendente', 'confirmado'])
                    ->exists();

                return response()->json([
                    'tipo_cliente'           => 'mensalista_fixo',
                    'mensalista_nome'        => $mensalista->nome,
                    'proximos_horarios_fixos' => $proximos,
                    'tem_agendamento_ativo'  => $temAtivo,
                ]);
            }

            // ── Mensalista (com limite semanal) ───────────────────────────────
            if ($mensalista->tipo === 'mensalista') {
                $config        = ConfiguracaoBarbearia::getInstance();
                $limiteGlobal  = $config->mensalista_limite_cortes_semana;
                // Limite individual do mensalista sobrescreve o global quando configurado
                $limite        = $mensalista->limite_cortes_semana ?: $limiteGlobal;
                $cortesSemana  = $this->cortesEssaSemana($telefone);
                $agAtivo       = Agendamento::where('cliente_telefone', $telefone)
                    ->whereIn('status', ['pendente', 'confirmado'])
                    ->with(['profissional', 'servico'])
                    ->first();

                return response()->json([
                    'tipo_cliente'        => 'mensalista',
                    'mensalista_nome'     => $mensalista->nome,
                    'cortes_esta_semana'  => $cortesSemana,
                    'limite_semana'       => $limite,
                    'limite_atingido'     => $cortesSemana >= $limite,
                    'tem_agendamento'     => $agAtivo !== null,
                    'agendamento'         => $agAtivo,
                ]);
            }
        }

        // ── Avulso (não cadastrado) ───────────────────────────────────────────
        $agAtivo = Agendamento::where('cliente_telefone', $telefone)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->with(['profissional', 'servico'])
            ->first();

        return response()->json([
            'tipo_cliente'   => 'avulso',
            'tem_agendamento' => $agAtivo !== null,
            'agendamento'    => $agAtivo,
        ]);
    }

    /**
     * Retorna os horários disponíveis para um profissional em uma data específica.
     *
     * ── Regras de negócio ────────────────────────────────────────────────────
     *
     * 1. JANELA: data deve estar entre hoje e 14 dias no futuro
     *
     * 2. CONFIGURAÇÃO GLOBAL (tabela configuracoes_barbearia):
     *    - intervalo_minutos : intervalo entre slots (padrão 60)
     *    - horario_abertura  : primeiro slot (padrão 08:00)
     *    - horario_encerramento : limite do dia (padrão 19:00)
     *
     * 3. HORÁRIO DO BARBEIRO (profissional.horarios_trabalho):
     *    - Se configurado: apenas os horários da lista são válidos
     *    - Se vazio/null: usa o intervalo global da barbearia
     *
     * 4. HORÁRIOS FIXOS DE MENSALISTAS:
     *    - Slots ocupados por horários fixos de mensalistas_fixos são bloqueados
     *
     * 5. AGENDAMENTOS ATIVOS: slots que colidem com agendamentos pendentes/confirmados
     *    são excluídos (considera a duração real do serviço)
     *
     * 6. SLOTS PASSADOS: para o dia atual, aplica buffer de 30 minutos
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function horariosDisponiveis(Request $request): JsonResponse
    {
        $request->validate([
            'profissional_id' => 'required|exists:profissionais,id',
            'data'            => 'required|date_format:Y-m-d',
            'servico_id'      => 'required|exists:servicos,id',
        ]);

        $data = Carbon::parse($request->data)->startOfDay();
        $hoje = Carbon::today();

        if ($data->lt($hoje) || $data->gt($hoje->copy()->addDays(14))) {
            return response()->json(['error' => 'Data fora do limite permitido (máximo 14 dias)'], 422);
        }

        $config       = ConfiguracaoBarbearia::getInstance();
        $profissional = Profissional::findOrFail($request->profissional_id);

        $diasTrabalho = $profissional->dias_trabalho ?? [1, 2, 3, 4, 5, 6];
        if (!in_array($data->dayOfWeek, array_map('intval', $diasTrabalho))) {
            return response()->json([], 200);
        }
        $servico      = Servico::findOrFail($request->servico_id);

        $slots = app(DisponibilidadeService::class)->calcular(
            $profissional,
            $servico,
            $data,
            $config->horario_abertura,
            $config->horario_encerramento,
            $config->intervalo_minutos
        );

        return response()->json($slots);
    }

    /**
     * Salva o agendamento aplicando todas as regras de negócio por tipo de cliente.
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'cliente_nome'     => 'required|string|max:100',
                'cliente_telefone' => 'required|string',
                'profissional_id'  => 'required|exists:profissionais,id',
                'servico_id'       => 'required|exists:servicos,id',
                'data_hora'        => 'required|date|after:now',
            ],
            [
                'cliente_nome.required'     => 'Informe seu nome.',
                'cliente_telefone.required' => 'Informe seu telefone.',
                'profissional_id.required'  => 'Escolha um profissional.',
                'servico_id.required'       => 'Escolha um serviço.',
                'data_hora.required'        => 'Escolha data e hora.',
                'data_hora.after'           => 'O agendamento deve ser em um horário futuro.',
            ]
        );

        $telefone           = preg_replace('/\D/', '', $request->cliente_telefone);
        $mensalista         = Mensalista::where('telefone', $telefone)->first();
        $mensalistaId       = $mensalista?->id;
        $isMensalista       = $mensalista !== null;
        $isAvulsoFixo       = false;

        // ── Regras por tipo de cliente ────────────────────────────────────────

        if ($mensalista && $mensalista->tipo === 'mensalista') {
            // Valida limite semanal
            $config       = ConfiguracaoBarbearia::getInstance();
            $limite       = $mensalista->limite_cortes_semana ?: $config->mensalista_limite_cortes_semana;
            $cortesSemana = $this->cortesEssaSemana($telefone);

            if ($cortesSemana >= $limite) {
                return back()->withErrors([
                    'cliente_telefone' => "Você já utilizou {$cortesSemana} de {$limite} corte(s) desta semana.",
                ])->withInput();
            }

            // Verifica agendamento ativo (mesma regra do avulso)
            if (Agendamento::where('cliente_telefone', $telefone)->whereIn('status', ['pendente', 'confirmado'])->exists()) {
                return back()->withErrors(['cliente_telefone' => 'Você já possui um agendamento ativo. Cancele-o antes de fazer um novo.'])->withInput();
            }
        } elseif ($mensalista && $mensalista->tipo === 'mensalista_fixo') {
            // Mensalista fixo agendando fora do horário fixo = avulso marcado como alerta
            $isAvulsoFixo = true;

            if (Agendamento::where('cliente_telefone', $telefone)->whereIn('status', ['pendente', 'confirmado'])->exists()) {
                return back()->withErrors(['cliente_telefone' => 'Você já possui um agendamento ativo. Cancele-o antes de fazer um novo.'])->withInput();
            }
        } else {
            // Avulso padrão: apenas 1 agendamento ativo por vez
            if (Agendamento::where('cliente_telefone', $telefone)->whereIn('status', ['pendente', 'confirmado'])->exists()) {
                return back()->withErrors(['cliente_telefone' => 'Você já possui um agendamento ativo. Cancele-o antes de fazer um novo.'])->withInput();
            }
        }

        $dadosExtras = $request->input('dados_extras');
        if (is_string($dadosExtras)) {
            $dadosExtras = json_decode($dadosExtras, true);
        }

        $agendamento = Agendamento::create([
            'cliente_nome'              => $request->cliente_nome,
            'cliente_telefone'          => $telefone,
            'profissional_id'           => $request->profissional_id,
            'servico_id'                => $request->servico_id,
            'data_hora'                 => Carbon::parse($request->data_hora),
            'status'                    => 'pendente',
            'mensalista'                => $isMensalista,
            'mensalista_id'             => $mensalistaId,
            'is_avulso_mensalista_fixo' => $isAvulsoFixo,
            'dados_extras'              => $dadosExtras,
        ]);

        // Guarda o telefone na sessão para que "Meus Agendamentos" não precise expor o número na URL
        session(['agendamentos_telefone' => $telefone]);

        return redirect()->route('agendamento.confirmado', ['tenant' => $request->route('tenant'), 'agendamentoId' => $agendamento->id]);
    }

    public function confirmado(Request $request)
    {
        $agendamento   = Agendamento::with(['profissional', 'servico'])->findOrFail($request->route('agendamentoId'));
        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia ?? 'Barbearia';
        $tenantSlug    = $request->route('tenant');
        return view('pages.agendamento.confirmado', compact('agendamento', 'nomeBarbearia', 'tenantSlug'));
    }

    public function meusAgendamentos(Request $request)
    {
        // Aceita POST (form) > GET param > sessão (redirect pós-cancelamento)
        $raw      = $request->input('telefone') ?? session('agendamentos_telefone', '');
        $telefone = preg_replace('/\D/', '', $raw);

        $agendamentos = Agendamento::where('cliente_telefone', $telefone)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->with(['profissional', 'servico'])
            ->orderBy('data_hora')
            ->get();

        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia ?? 'Barbearia';
        $tenantSlug    = $request->route('tenant');
        return view('pages.agendamento.meus-agendamentos', compact('agendamentos', 'telefone', 'nomeBarbearia', 'tenantSlug'));
    }

    public function cancelar(Request $request)
    {
        $telefone = preg_replace('/\D/', '', $request->telefone);

        $agendamento = Agendamento::where('id', $request->route('agendamentoId'))
            ->where('cliente_telefone', $telefone)
            ->whereIn('status', ['pendente', 'confirmado'])
            ->firstOrFail();

        $agendamento->update(['status' => 'cancelado']);

        // Telefone vai para a sessão — evita expor o número na URL do redirect
        session(['agendamentos_telefone' => $telefone]);

        return redirect()->route('agendamento.meus-agendamentos', ['tenant' => $request->route('tenant')])
            ->with('sucesso', 'Agendamento cancelado com sucesso.');
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Conta quantos cortes o cliente realizou ou tem agendados na semana atual (seg a dom).
     */
    private function cortesEssaSemana(string $telefone): int
    {
        return Agendamento::where('cliente_telefone', $telefone)
            ->whereIn('status', ['pendente', 'confirmado', 'concluido'])
            ->whereBetween('data_hora', [
                Carbon::now()->startOfWeek(Carbon::MONDAY),
                Carbon::now()->endOfWeek(Carbon::SUNDAY),
            ])
            ->count();
    }

    /**
     * Gera os próximos N dias de horários fixos de um mensalista_fixo.
     * Cada horário_fixo é recorrente semanalmente (mesmo dia + mesma hora toda semana).
     */
    private function gerarProximosHorariosFixos(Mensalista $mensalista, int $dias = 30): array
    {
        $proximos = [];
        $hoje     = Carbon::today();
        $limite   = $hoje->copy()->addDays($dias);
        $nomeDias = [
            0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça',
            3 => 'Quarta',  4 => 'Quinta',  5 => 'Sexta', 6 => 'Sábado',
        ];

        foreach ($mensalista->horariosFixos()->where('ativo', true)->with(['profissional', 'servico'])->get() as $fixo) {
            // Encontra a próxima ocorrência do dia da semana configurado
            $data = $hoje->copy();
            while ($data->dayOfWeek !== $fixo->dia_semana) {
                $data->addDay();
            }

            // Adiciona todas as ocorrências dentro da janela
            while ($data->lte($limite)) {
                $dataHora = Carbon::parse($data->format('Y-m-d') . ' ' . $fixo->hora);
                if ($dataHora->gt(Carbon::now())) {
                    $proximos[] = [
                        'data'         => $dataHora->format('d/m/Y'),
                        'hora'         => substr($fixo->hora, 0, 5),
                        'datetime'     => $dataHora->format('Y-m-d H:i'),
                        'profissional' => $fixo->profissional->nome,
                        'servico'      => $fixo->servico->nome,
                        'dia_nome'     => $nomeDias[$fixo->dia_semana],
                    ];
                }
                $data->addWeek();
            }
        }

        // Ordena por data e retorna máximo de 10 próximos
        usort($proximos, fn($a, $b) => strcmp($a['datetime'], $b['datetime']));
        return array_slice($proximos, 0, 10);
    }
}
