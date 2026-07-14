<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            // Horários por dia da semana: {"1": ["08:00","09:00"], "6": ["10:00"]}
            // Só é consultado quando horarios_por_dia_ativo = true; caso contrário
            // vale a lista única de horarios_trabalho (comportamento antigo).
            $table->json('horarios_por_dia')->nullable()->after('horarios_trabalho');
            $table->boolean('horarios_por_dia_ativo')->default(false)->after('horarios_por_dia');
        });
    }

    public function down(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->dropColumn(['horarios_por_dia', 'horarios_por_dia_ativo']);
        });
    }
};
