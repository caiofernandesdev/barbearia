<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_estabelecimento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('icone')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Migra tenants.tipo (string) para FK
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('tipo_estabelecimento_id')->nullable()->after('tipo')->constrained('tipos_estabelecimento')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['tipo_estabelecimento_id']);
            $table->dropColumn('tipo_estabelecimento_id');
        });
        Schema::dropIfExists('tipos_estabelecimento');
    }
};
