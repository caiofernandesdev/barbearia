<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agendamentos: queries frequentes por telefone e por barbeiro+data
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->index('cliente_telefone', 'idx_agendamentos_telefone');
            $table->index(['profissional_id', 'data_hora'], 'idx_agendamentos_profissional_data');
        });

        // Mensalistas: lookup frequente por telefone na verificação do cliente
        Schema::table('mensalistas', function (Blueprint $table) {
            $table->index('telefone', 'idx_mensalistas_telefone');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropIndex('idx_agendamentos_telefone');
            $table->dropIndex('idx_agendamentos_profissional_data');
        });

        Schema::table('mensalistas', function (Blueprint $table) {
            $table->dropIndex('idx_mensalistas_telefone');
        });
    }
};
