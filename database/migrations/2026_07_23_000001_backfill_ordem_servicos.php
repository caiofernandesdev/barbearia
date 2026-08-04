<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Serviços sem ordem definida ficavam em 0 e, como 0 < 1, apareciam ANTES
 * dos que o dono numerou à mão (1, 2, 3…). Aqui empurramos os zerados para
 * o fim de cada estabelecimento, preservando a ordem já escolhida.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantIds = DB::table('servicos')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            // Maior ordem já definida neste tenant (os numerados à mão)
            $max = (int) DB::table('servicos')
                ->where('tenant_id', $tenantId)
                ->where('ordem', '>', 0)
                ->max('ordem');

            // Zerados vão para depois do último, em ordem estável (por id)
            $zerados = DB::table('servicos')
                ->where('tenant_id', $tenantId)
                ->where('ordem', 0)
                ->orderBy('id')
                ->pluck('id');

            foreach ($zerados as $id) {
                DB::table('servicos')->where('id', $id)->update(['ordem' => ++$max]);
            }
        }
    }

    public function down(): void
    {
        // Sem volta: não há como saber quais eram 0 originalmente, e reverter
        // não traz ganho. Deixar como está é seguro.
    }
};
