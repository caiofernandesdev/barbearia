<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'nome', 'tipo', 'tipo_estabelecimento_id', 'plano_id', 'whatsapp_config', 'ativo'])]
class Tenant extends Model
{
    protected $table = 'tenants';

    protected function casts(): array
    {
        return [
            'whatsapp_config' => 'array',
            'ativo'           => 'boolean',
        ];
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

    // Helpers de config WhatsApp
    public function whatsappBaseUrl(): string
    {
        return $this->whatsapp_config['base_url'] ?? config('services.evolution.base_url', '');
    }

    public function whatsappApiKey(): string
    {
        return $this->whatsapp_config['api_key'] ?? config('services.evolution.api_key', '');
    }

    public function whatsappInstance(): string
    {
        return $this->whatsapp_config['instance'] ?? config('services.evolution.instance', '');
    }
}
