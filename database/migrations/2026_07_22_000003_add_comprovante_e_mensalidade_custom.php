<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprovante anexado a cada pagamento e mensalidade personalizada por
 * tenant (sobrepõe o preço do plano — para descontos, cortesias parciais
 * ou acordos específicos, sem precisar criar um plano só para isso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('comprovante')->nullable()->after('observacao');
        });

        Schema::table('tenants', function (Blueprint $table) {
            // Nulo = usa o preço do plano
            $table->decimal('valor_mensalidade', 10, 2)->nullable()->after('plano_id');
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropColumn('comprovante');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('valor_mensalidade');
        });
    }
};
