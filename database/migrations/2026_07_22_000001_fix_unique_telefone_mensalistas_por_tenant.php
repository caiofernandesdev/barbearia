<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O telefone do cliente era único de forma GLOBAL, herança de antes do
 * multi-tenant. Isso impedia que a mesma pessoa fosse cliente de dois
 * estabelecimentos (o 2º recebia erro de SQL na cara).
 *
 * A unicidade correta é por tenant: (tenant_id, telefone). Como o índice
 * global já garantia que não há telefones repetidos, a troca não pode
 * gerar conflito de dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensalistas', function (Blueprint $table) {
            $table->dropUnique('mensalistas_telefone_unique');
            $table->unique(['tenant_id', 'telefone'], 'mensalistas_tenant_telefone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mensalistas', function (Blueprint $table) {
            $table->dropUnique('mensalistas_tenant_telefone_unique');
            $table->unique('telefone', 'mensalistas_telefone_unique');
        });
    }
};
