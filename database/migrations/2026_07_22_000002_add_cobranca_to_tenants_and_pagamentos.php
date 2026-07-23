<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retaguarda financeira: dados de cobrança da assinatura de cada tenant
 * (o que ele paga ao Atendix) e o histórico de pagamentos recebidos.
 *
 * O status "em dia / atrasado" NÃO é uma coluna — é derivado de
 * proximo_vencimento, para nunca ficar defasado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('assinatura_inicio')->nullable()->after('plano_id');
            $table->unsignedTinyInteger('dia_vencimento')->default(10)->after('assinatura_inicio');
            $table->date('proximo_vencimento')->nullable()->after('dia_vencimento');
        });

        // Tenants existentes começam a contar a partir de hoje, com o
        // vencimento no mês que vem — evita marcar todo mundo como atrasado.
        DB::table('tenants')->whereNull('assinatura_inicio')->update([
            'assinatura_inicio' => now()->toDateString(),
            'proximo_vencimento' => now()->addMonth()->startOfMonth()->addDays(9)->toDateString(),
        ]);

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            // Mês de referência da mensalidade: "2026-07"
            $table->string('competencia', 7);
            $table->date('pago_em');
            $table->string('forma')->default('pix'); // pix, dinheiro, cartao, transferencia, outro
            $table->string('observacao')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['assinatura_inicio', 'dia_vencimento', 'proximo_vencimento']);
        });
    }
};
