<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano personalizado por tenant: permite configurar módulos e limites
 * direto no cadastro do estabelecimento, sem precisar criar um Plano
 * (linha em `planos`) exclusivo pra um único cliente.
 *
 * Quando `personalizado` = true, Tenant::hasFeature()/podeAdicionarProfissional()/
 * podeAdicionarUsuario() ignoram o `plano_id` e usam os campos *_custom daqui.
 * O `plano_id` continua salvo (pode ficar nulo) só de referência histórica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('personalizado')->default(false)->after('plano_id');
            $table->json('features_custom')->nullable()->after('personalizado');
            $table->unsignedInteger('max_profissionais_custom')->nullable()->after('features_custom');
            $table->unsignedInteger('max_usuarios_custom')->nullable()->after('max_profissionais_custom');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['personalizado', 'features_custom', 'max_profissionais_custom', 'max_usuarios_custom']);
        });
    }
};
