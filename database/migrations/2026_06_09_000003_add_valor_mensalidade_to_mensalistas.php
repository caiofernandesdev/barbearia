<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensalistas', function (Blueprint $table) {
            $table->decimal('valor_mensalidade', 8, 2)->default(0)->after('limite_cortes_semana');
        });
    }

    public function down(): void
    {
        Schema::table('mensalistas', function (Blueprint $table) {
            $table->dropColumn('valor_mensalidade');
        });
    }
};
