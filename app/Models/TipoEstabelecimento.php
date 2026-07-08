<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'icone', 'campos_sugeridos', 'ativo'])]
class TipoEstabelecimento extends Model
{
    protected $table = 'tipos_estabelecimento';

    protected function casts(): array
    {
        return [
            'ativo'            => 'boolean',
            'campos_sugeridos' => 'array',
        ];
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
