<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'descricao', 'preco_mensal', 'features', 'ativo'])]
class Plano extends Model
{
    protected $table = 'planos';

    protected function casts(): array
    {
        return [
            'features'     => 'array',
            'preco_mensal' => 'decimal:2',
            'ativo'        => 'boolean',
        ];
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
