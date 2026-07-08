<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
            'ativo'      => 'boolean',
        ];
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
