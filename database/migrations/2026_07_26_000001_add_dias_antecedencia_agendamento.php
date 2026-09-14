<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantos dias no futuro o cliente pode agendar (janela do carrossel público).
 * O estabelecimento personaliza; padrão 14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_antecedencia_agendamento')->default(14)->after('dias_antecedencia_lembrete');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn('dias_antecedencia_agendamento');
        });
    }
};
