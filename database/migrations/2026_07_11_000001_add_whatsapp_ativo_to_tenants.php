<?php

use App\Models\Plano;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Interruptor do módulo WhatsApp por estabelecimento (super admin)
            $table->boolean('whatsapp_ativo')->default(true)->after('whatsapp_config');
        });

        // Backfill: liga só para quem já tem o módulo whatsapp no plano
        Tenant::withoutGlobalScopes()->with('plano')->get()->each(function (Tenant $t) {
            $temModulo = $t->plano?->hasFeature('whatsapp') ?? false;
            $t->forceFill(['whatsapp_ativo' => $temModulo])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('whatsapp_ativo');
        });
    }
};
