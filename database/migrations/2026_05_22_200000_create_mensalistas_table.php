<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensalistas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('telefone', 20)->unique(); // chave de identificação do cliente
            $table->enum('tipo', ['avulso', 'mensalista', 'mensalista_fixo'])->default('avulso');
            // Limite individual de cortes por semana (sobrescreve o limite global da barbearia)
            $table->unsignedTinyInteger('limite_cortes_semana')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensalistas');
    }
};
