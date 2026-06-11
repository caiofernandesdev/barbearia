<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            // Array JSON com os horários de trabalho do barbeiro, ex: ["08:00","09:00","10:00"]
            // Quando null/vazio, usa os horários globais configurados na barbearia
            $table->text('horarios_trabalho')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->dropColumn('horarios_trabalho');
        });
    }
};
