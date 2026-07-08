<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->boolean('cancelar_nao_confirmados')->default(false)->after('mensagem_repescagem');
            $table->integer('horas_antecedencia_cancelamento')->default(2)->after('cancelar_nao_confirmados');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn(['cancelar_nao_confirmados', 'horas_antecedencia_cancelamento']);
        });
    }
};
