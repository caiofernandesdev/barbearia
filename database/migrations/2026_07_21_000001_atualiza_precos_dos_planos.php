<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinha os preços dos planos com a tabela exibida na landing.
 *
 * A landing passou a ler o preço do banco, então um ambiente ainda nos
 * valores antigos mostraria preço desatualizado no site.
 *
 * Cada plano só é alterado se o preço ainda for o antigo — quem já ajustou
 * pelo super admin (o caso de produção) não é tocado. O nome também não é
 * alterado: a ligação com o card da landing é pelo slug, não pelo nome.
 */
return new class extends Migration
{
    /** nome => [preço antigo, preço novo] */
    private const PRECOS = [
        'Básico' => [97.00, 79.90],
        'Starter' => [97.00, 79.90],
        'Pro' => [197.00, 159.90],
        'Enterprise' => [397.00, 239.90],
    ];

    public function up(): void
    {
        $this->aplicar(fn ($de, $para) => [$de, $para]);
    }

    public function down(): void
    {
        $this->aplicar(fn ($de, $para) => [$para, $de]);
    }

    /** @param  callable(float, float): array{0: float, 1: float}  $direcao */
    private function aplicar(callable $direcao): void
    {
        foreach (self::PRECOS as $nome => [$antigo, $novo]) {
            [$esperado, $aplicar] = $direcao($antigo, $novo);

            DB::table('planos')
                ->where('nome', $nome)
                ->where('preco_mensal', $esperado)
                ->update(['preco_mensal' => $aplicar]);
        }
    }
};
