<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_antecedencia_lembrete')->default(1)->after('percentual_barbearia');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn('dias_antecedencia_lembrete');
        });
    }
};
