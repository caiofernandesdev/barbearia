<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'profissional_id', 'pode_cancelar', 'permissoes', 'tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    /**
     * Áreas do painel que podem ser liberadas por usuário.
     * slug => rótulo exibido no cadastro.
     */
    public const PERMISSOES = [
        'agenda' => 'Agenda',
        'agendamentos' => 'Agendamentos',
        'indisponibilidades' => 'Indisponibilidades',
        'lista_espera' => 'Lista de espera',
        'clientes' => 'Clientes',
        'agenda_fixa' => 'Agenda Fixa',
        'repescagem' => 'Repescagem',
        'profissionais' => 'Profissionais',
        'servicos' => 'Serviços',
        'campos_agendamento' => 'Campos do agendamento',
        'relatorios' => 'Relatórios',
        'salario_emocional' => 'Salário Emocional',
        'usuarios' => 'Usuários',
        'configuracoes' => 'Configurações',
    ];

    /** O que um barbeiro enxerga por padrão quando nada foi configurado. */
    public const PADRAO_BARBEIRO = ['agenda_fixa', 'salario_emocional'];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'super-admin') {
            return $this->role === 'super_admin';
        }

        return in_array($this->role, ['admin', 'barbeiro']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBarbeiro(): bool
    {
        return $this->role === 'barbeiro';
    }

    /** Admin sempre pode; profissional só se tiver a permissão marcada. */
    public function podeCancelar(): bool
    {
        return $this->isAdmin() || (bool) $this->pode_cancelar;
    }

    /**
     * Este usuário pode acessar a área indicada?
     *
     * - super admin: tudo.
     * - permissões definidas (array): vale exatamente o que está marcado.
     * - permissões NULL (usuário antigo): comportamento por papel — admin vê
     *   tudo, barbeiro vê o padrão. Preserva quem já existia.
     * - salvaguarda: admin nunca perde "usuarios", para não se trancar fora.
     */
    public function temPermissao(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isAdmin() && $slug === 'usuarios') {
            return true;
        }

        $permissoes = $this->permissoes;

        if ($permissoes === null) {
            return $this->isAdmin()
                ? true
                : in_array($slug, self::PADRAO_BARBEIRO, true);
        }

        return in_array($slug, $permissoes, true);
    }

    /** Permissões efetivas quando nada foi definido (para exibir no cadastro). */
    public static function padraoPermissoes(?string $role): array
    {
        return match ($role) {
            'admin' => array_keys(self::PERMISSOES),
            'barbeiro' => self::PADRAO_BARBEIRO,
            default => [],
        };
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pode_cancelar' => 'boolean',
            'permissoes' => 'array',
        ];
    }
}
