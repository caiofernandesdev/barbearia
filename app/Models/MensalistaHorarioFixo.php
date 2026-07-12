<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mensalista_id', 'profissional_id', 'servico_id', 'dia_semana', 'hora', 'ativo', 'tenant_id'])]
class MensalistaHorarioFixo extends Model
{
    use BelongsToTenant;

    protected $table = 'mensalista_horarios_fixos';

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Normaliza a hora para "H:i" (sem segundos).
     *
     * O banco guarda TIME como "09:00:00"; o Select do form usa opções "09:00".
     * Sem cortar os segundos, a edição não recarrega o horário e o save quebra.
     */
    protected function hora(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : $value,
        );
    }

    public function mensalista()
    {
        return $this->belongsTo(Mensalista::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }
}
