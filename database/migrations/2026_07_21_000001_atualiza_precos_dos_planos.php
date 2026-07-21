<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinha os planos cadastrados com a tabela de preços da landing.
 *
 * A landing passou a ler nome e preço do banco, então sem isso o site
 * voltaria a exibir os valores antigos no deploy.
 *
 * Cada alteração é condicionada ao valor antigo: se alguém já ajustou o
 * preço pelo super admin, a migration não encosta naquele plano.
 */
return new class extends Migration
{
    /** nome atual => [nome novo, preço antigo, preço novo] */
    private const PLANOS = [
        'Básico' => ['Starter', 97.00, 79.90],
        'Pro' => ['Pro', 197.00, 159.90],
        'Enterprise' => ['Enterprise', 397.00, 239.90],
    ];

    public function up(): void
    {
        foreach (self::PLANOS as $nomeAtual => [$nomeNovo, $precoAntigo, $precoNovo]) {
            $plano = DB::table('planos')->where('nome', $nomeAtual)->first();

            if (! $plano) {
                continue;
            }

            $mudancas = [];

            // Só renomeia se o nome novo ainda não estiver em uso
            if ($nomeNovo !== $nomeAtual && ! DB::table('planos')->where('nome', $nomeNovo)->exists()) {
                $mudancas['nome'] = $nomeNovo;
            }

            // Preserva preço já ajustado manualmente
            if ((float) $plano->preco_mensal === $precoAntigo) {
                $mudancas['preco_mensal'] = $precoNovo;
            }

            if ($mudancas) {
                DB::table('planos')->where('id', $plano->id)->update($mudancas);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PLANOS as $nomeAntigo => [$nomeNovo, $precoAntigo, $precoNovo]) {
            $plano = DB::table('planos')->where('nome', $nomeNovo)->first();

            if (! $plano) {
                continue;
            }

            $mudancas = [];

            if ($nomeNovo !== $nomeAntigo && ! DB::table('planos')->where('nome', $nomeAntigo)->exists()) {
                $mudancas['nome'] = $nomeAntigo;
            }

            if ((float) $plano->preco_mensal === $precoNovo) {
                $mudancas['preco_mensal'] = $precoAntigo;
            }

            if ($mudancas) {
                DB::table('planos')->where('id', $plano->id)->update($mudancas);
            }
        }
    }
};
