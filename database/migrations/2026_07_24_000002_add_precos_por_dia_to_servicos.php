<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço por dia da semana no serviço. Ex.: {"1": 30.00, "2": 35.00} cobra
 * diferente na segunda e na terça. Dia sem valor definido usa o preço base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->json('precos_por_dia')->nullable()->after('preco');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('precos_por_dia');
        });
    }
};
