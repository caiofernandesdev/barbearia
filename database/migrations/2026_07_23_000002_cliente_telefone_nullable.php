<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O telefone do cliente passa a ser opcional: no atendimento presencial o
 * barbeiro nem sempre tem o número (cliente de balcão). O envio de WhatsApp
 * já ignora agendamento sem telefone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('cliente_telefone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('cliente_telefone')->nullable(false)->change();
        });
    }
};
