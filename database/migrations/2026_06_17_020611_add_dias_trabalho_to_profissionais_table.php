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
        Schema::table('profissionais', function (Blueprint $table) {
            // Dias da semana que este profissional atende (0=Dom ... 6=Sáb)
            $table->string('dias_trabalho')->default('[1,2,3,4,5,6]')->after('horarios_trabalho');
        });
    }

    public function down(): void
    {
        Schema::table('profissionais', function (Blueprint $table) {
            $table->dropColumn('dias_trabalho');
        });
    }
};
