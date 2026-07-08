<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'slug', 'tipo', 'opcoes', 'obrigatorio', 'ordem', 'ativo', 'tenant_id'])]
class CampoPersonalizado extends Model
{
    use BelongsToTenant;

    protected $table = 'campos_personalizados';

    protected function casts(): array
    {
        return [
            'opcoes'      => 'array',
            'obrigatorio' => 'boolean',
            'ativo'       => 'boolean',
            'ordem'       => 'integer',
        ];
    }
}
