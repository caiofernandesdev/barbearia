<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_personalizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nome');
            $table->string('slug');
            $table->string('tipo')->default('select'); // select, text, toggle
            $table->json('opcoes')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->json('dados_extras')->nullable()->after('is_avulso_mensalista_fixo');
        });

        Schema::table('tipos_estabelecimento', function (Blueprint $table) {
            $table->json('campos_sugeridos')->nullable()->after('icone');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_estabelecimento', function (Blueprint $table) {
            $table->dropColumn('campos_sugeridos');
        });
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('dados_extras');
        });
        Schema::dropIfExists('campos_personalizados');
    }
};
