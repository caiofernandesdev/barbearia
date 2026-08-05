<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões de acesso por usuário. NULL = comportamento por papel (admin vê
 * tudo, barbeiro vê o padrão) — mantém os usuários existentes funcionando.
 * Quando o dono define explicitamente, vira a lista exata de áreas liberadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissoes')->nullable()->after('pode_cancelar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissoes');
        });
    }
};
