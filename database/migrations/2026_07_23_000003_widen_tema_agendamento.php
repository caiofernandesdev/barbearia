<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coluna do tema tinha 10 caracteres — não cabia "tecnologico" (11).
 * Alarga para acomodar os temas novos (tecnologico/feminino/neutro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->string('tema_agendamento', 30)->default('escuro')->change();
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->string('tema_agendamento', 10)->default('escuro')->change();
        });
    }
};
