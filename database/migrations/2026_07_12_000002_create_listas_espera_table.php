<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->string('cliente_nome');
            $table->string('cliente_telefone');
            $table->date('data');               // dia desejado
            $table->time('hora_preferida');     // horário que o cliente gostaria
            $table->string('status')->default('aguardando'); // aguardando | encaixado | cancelado
            $table->timestamps();

            $table->index(['tenant_id', 'data', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listas_espera');
    }
};
