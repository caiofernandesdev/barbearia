<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço "sem preço fixo" (ex: produto variável por cliente, tipo extensão
 * de cabelo): preco = null significa "a combinar" — o valor real é lançado
 * manualmente em cada agendamento (Agendamento::valor_total), não vem do
 * catálogo. Ver Servico::temPrecoFixo()/precoNoDia() e Servico::somarPrecos().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->decimal('preco', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sem valores nulos pra não quebrar o rollback (vira 0 por padrão).
        DB::table('servicos')->whereNull('preco')->update(['preco' => 0]);

        Schema::table('servicos', function (Blueprint $table) {
            $table->decimal('preco', 10, 2)->nullable(false)->change();
        });
    }
};
