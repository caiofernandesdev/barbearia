<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            // Vínculo com o cadastro de mensalistas (null = cliente avulso não cadastrado)
            $table->foreignId('mensalista_id')
                ->nullable()
                ->after('observacao')
                ->constrained('mensalistas')
                ->onDelete('set null');
            // Flag que indica mensalista_fixo agendando fora do horário fixo (gera notificação no admin)
            $table->boolean('is_avulso_mensalista_fixo')->default(false)->after('mensalista_id');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropForeign(['mensalista_id']);
            $table->dropColumn(['mensalista_id', 'is_avulso_mensalista_fixo']);
        });
    }
};
