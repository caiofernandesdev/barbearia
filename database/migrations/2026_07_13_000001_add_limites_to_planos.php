<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // 0 = ilimitado
            $table->unsignedInteger('max_profissionais')->default(0)->after('features');
            $table->unsignedInteger('max_usuarios')->default(0)->after('max_profissionais');
        });
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['max_profissionais', 'max_usuarios']);
        });
    }
};
