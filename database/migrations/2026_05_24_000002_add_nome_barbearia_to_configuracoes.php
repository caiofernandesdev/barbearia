<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->string('nome_barbearia')->default('Barbearia')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_barbearia', function (Blueprint $table) {
            $table->dropColumn('nome_barbearia');
        });
    }
};