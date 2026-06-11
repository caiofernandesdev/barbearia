<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_barbearia', function (Blueprint $table) {
            $table->id();
            // Dias da semana que a barbearia funciona (array JSON: [1,2,3,4,5,6] = Seg a Sáb)
            $table->string('dias_funcionamento')->default('[1,2,3,4,5,6]');
            // Limite de cortes por semana para clientes mensalistas (global)
            $table->unsignedTinyInteger('mensalista_limite_cortes_semana')->default(1);
            // Horário de abertura e encerramento usados para gerar os slots de agendamento
            $table->string('horario_abertura', 5)->default('08:00');
            $table->string('horario_encerramento', 5)->default('19:00');
            // Intervalo em minutos entre cada slot (60 = de hora em hora)
            $table->unsignedSmallInteger('intervalo_minutos')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_barbearia');
    }
};
