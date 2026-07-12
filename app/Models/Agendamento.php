<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cliente_nome', 'cliente_telefone', 'profissional_id', 'servico_id',
    'valor_total', 'duracao_total_minutos',
    'data_hora', 'status', 'mensalista', 'observacao',
    'mensalista_id', 'is_avulso_mensalista_fixo', 'dados_extras', 'tenant_id',
])]
class Agendamento extends Model
{
    use BelongsToTenant;

    protected $table = 'agendamentos';

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'mensalista' => 'boolean',
            'is_avulso_mensalista_fixo' => 'boolean',
            'dados_extras' => 'array',
            'valor_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Preenche os totais a partir do serviço único quando não informados —
        // agendamentos multi-serviço (booking público) setam os totais explicitamente
        static::saving(function (Agendamento $ag) {
            if ($ag->valor_total === null && $ag->servico_id) {
                $s = Servico::withoutGlobalScopes()->find($ag->servico_id);
                if ($s) {
                    $ag->valor_total = $s->preco;
                    $ag->duracao_total_minutos = $s->duracao_minutos;
                }
            }
        });

        // Sem WhatsApp ativo não há como o cliente confirmar → nasce confirmado.
        // Centralizado aqui: cobre booking público, painel admin e agenda do dia.
        static::creating(function (Agendamento $ag) {
            if (in_array($ag->status, [null, 'pendente'], true)) {
                $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
                $ag->status = ($tenant && ! $tenant->whatsappAtivo()) ? 'confirmado' : 'pendente';
            }
        });
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }

    /** Todos os serviços do agendamento (multi-serviço); servico_id guarda o primeiro. */
    public function servicos()
    {
        return $this->belongsToMany(Servico::class, 'agendamento_servico');
    }

    /**
     * O profissional já tem um agendamento ativo que se sobrepõe a esta janela?
     *
     * Considera a duração real de cada agendamento (não só o horário de início) —
     * é a checagem central de conflito usada em todos os pontos de criação.
     * Overlap: novoInicio < fimExistente && novoFim > inicioExistente.
     */
    public static function temConflito(int $profissionalId, Carbon $inicio, int $duracaoMin, ?int $tenantId, ?int $ignorarId = null): bool
    {
        $fim = $inicio->copy()->addMinutes(max(1, $duracaoMin));

        return static::withoutGlobalScopes()
            ->where('profissional_id', $profissionalId)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['pendente', 'confirmado'])
            ->whereDate('data_hora', $inicio->toDateString())
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->with('servico')
            ->get()
            ->contains(function ($ag) use ($inicio, $fim) {
                $agInicio = Carbon::parse($ag->data_hora);
                $agFim = $agInicio->copy()->addMinutes($ag->duracao_total_minutos ?? $ag->servico?->duracao_minutos ?? 30);

                return $inicio->lt($agFim) && $fim->gt($agInicio);
            });
    }

    /** Nomes dos serviços para exibição — cobre agendamentos antigos (pivot vazio). */
    public function nomesServicos(): string
    {
        $servicos = $this->relationLoaded('servicos') ? $this->servicos : $this->servicos()->get();

        return $servicos->isNotEmpty()
            ? $servicos->pluck('nome')->implode(' + ')
            : ($this->servico?->nome ?? '');
    }

    public function mensalistaCliente()
    {
        return $this->belongsTo(Mensalista::class, 'mensalista_id');
    }
}
