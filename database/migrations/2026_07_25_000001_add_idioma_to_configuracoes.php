<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idioma do estabelecimento: o dono escolhe e tanto a página pública de
 * agendamento quanto o painel passam a exibir naquele idioma.
 * Valores: pt_BR (padrão), en, es.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->string('idioma', 5)->default('pt_BR')->after('tema_agendamento');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn('idioma');
        });
    }
};
