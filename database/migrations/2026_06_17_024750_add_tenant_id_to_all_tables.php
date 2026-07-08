<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'profissionais',
            'servicos',
            'mensalistas',
            'mensalista_horarios_fixos',
            'agendamentos',
            'configuracoes_barbearia',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'profissionais',
            'servicos',
            'mensalistas',
            'mensalista_horarios_fixos',
            'agendamentos',
            'configuracoes_barbearia',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(["{$tableName}_tenant_id_foreign"]);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
