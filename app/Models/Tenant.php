<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'nome', 'tipo', 'tipo_estabelecimento_id', 'plano_id', 'whatsapp_config', 'whatsapp_ativo', 'ativo', 'assinatura_inicio', 'dia_vencimento', 'proximo_vencimento'])]
class Tenant extends Model
{
    protected $table = 'tenants';

    protected function casts(): array
    {
        return [
            'whatsapp_config' => 'array',
            'whatsapp_ativo' => 'boolean',
            'ativo' => 'boolean',
            'assinatura_inicio' => 'date',
            'dia_vencimento' => 'integer',
            'proximo_vencimento' => 'date',
        ];
    }

    public function pagamentos()
    {
        return $this->hasMany(Pagamento::class);
    }

    /** Quanto este tenant paga por mês ao Atendix (preço do plano) */
    public function valorMensal(): float
    {
        return (float) ($this->plano?->preco_mensal ?? 0);
    }

    /**
     * Situação da cobrança, derivada do vencimento — nunca guardada, para não
     * ficar defasada. 'cortesia' quando não há valor de plano.
     *
     * @return 'cortesia'|'em_dia'|'vence_em_breve'|'atrasado'
     */
    public function statusCobranca(): string
    {
        if ($this->valorMensal() <= 0) {
            return 'cortesia';
        }

        $venc = $this->proximo_vencimento;
        if (! $venc) {
            return 'em_dia';
        }

        if ($venc->isPast() && ! $venc->isToday()) {
            return 'atrasado';
        }

        if ($venc->lte(now()->addDays(5))) {
            return 'vence_em_breve';
        }

        return 'em_dia';
    }

    public function estaAtrasado(): bool
    {
        return $this->statusCobranca() === 'atrasado';
    }

    /** Dias de atraso (0 se em dia) */
    public function diasAtraso(): int
    {
        if (! $this->estaAtrasado() || ! $this->proximo_vencimento) {
            return 0;
        }

        return $this->proximo_vencimento->diffInDays(now());
    }

    /**
     * Registra um pagamento e empurra o vencimento para o mês seguinte.
     * A competência default é o mês do vencimento que está sendo quitado.
     */
    public function registrarPagamento(float $valor, string $forma = 'pix', ?string $observacao = null): Pagamento
    {
        $competencia = ($this->proximo_vencimento ?? now())->format('Y-m');

        $pagamento = $this->pagamentos()->create([
            'valor' => $valor,
            'competencia' => $competencia,
            'pago_em' => now()->toDateString(),
            'forma' => $forma,
            'observacao' => $observacao,
        ]);

        // Avança um mês a partir do vencimento (não de hoje), para não
        // "perder" dias quando o pagamento entra adiantado ou atrasado.
        $base = $this->proximo_vencimento ?? now();
        $this->update(['proximo_vencimento' => $base->copy()->addMonth()->toDateString()]);

        return $pagamento;
    }

    /** Módulo WhatsApp ligado para este estabelecimento? Controla envio e status. */
    public function whatsappAtivo(): bool
    {
        return (bool) $this->whatsapp_ativo;
    }

    /** Ainda cabe mais um profissional no limite do plano? (0 = ilimitado) */
    public function podeAdicionarProfissional(): bool
    {
        $max = (int) ($this->plano?->max_profissionais ?? 0);
        if ($max <= 0) {
            return true;
        }

        return Profissional::withoutGlobalScopes()->where('tenant_id', $this->id)->count() < $max;
    }

    /** Ainda cabe mais um usuário no limite do plano? (0 = ilimitado) */
    public function podeAdicionarUsuario(): bool
    {
        $max = (int) ($this->plano?->max_usuarios ?? 0);
        if ($max <= 0) {
            return true;
        }

        return User::withoutGlobalScopes()->where('tenant_id', $this->id)->count() < $max;
    }

    public function tipoEstabelecimento()
    {
        return $this->belongsTo(TipoEstabelecimento::class);
    }

    public function plano()
    {
        return $this->belongsTo(Plano::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Tenant $tenant) {
            $tenant->users()->withoutGlobalScopes()->delete();
            ConfiguracaoBarbearia::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Profissional::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Servico::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Mensalista::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            MensalistaHorarioFixo::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Agendamento::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Indisponibilidade::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            Pagamento::where('tenant_id', $tenant->id)->delete();
        });
    }

    public function nomePadrao(): string
    {
        return $this->tipoEstabelecimento?->nome ?? $this->tipo ?? 'Estabelecimento';
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plano?->hasFeature($feature) ?? false;
    }

    /**
     * O plano inclui este relatório granular? (slug sem o prefixo rel_)
     *
     * Retrocompat: plano antigo com 'relatorios' e NENHUM rel_* marcado
     * mantém todos os relatórios liberados.
     */
    public function hasRelatorio(string $slug): bool
    {
        if (! $this->hasFeature('relatorios')) {
            return false;
        }

        $features = $this->plano?->features ?? [];
        $granulares = array_filter($features, fn ($f) => str_starts_with($f, 'rel_'));

        if ($granulares === []) {
            return true; // legado: sem granularidade configurada = tudo liberado
        }

        return in_array('rel_'.$slug, $features, true);
    }

    // Helpers de config WhatsApp
    public function whatsappBaseUrl(): string
    {
        return $this->whatsapp_config['base_url'] ?? config('services.evolution.url', '');
    }

    public function whatsappApiKey(): string
    {
        return $this->whatsapp_config['api_key'] ?? config('services.evolution.apikey', '');
    }

    public function whatsappInstance(): string
    {
        return $this->whatsapp_config['instance'] ?? config('services.evolution.instance', '');
    }
}
