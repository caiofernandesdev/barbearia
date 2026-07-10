<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            // Tema da página pública de agendamento: 'escuro' (padrão) ou 'claro'
            $table->string('tema_agendamento', 10)->default('escuro');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn('tema_agendamento');
        });
    }
};
