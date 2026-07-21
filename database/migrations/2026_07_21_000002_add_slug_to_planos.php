<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Chave estável do plano, usada pela landing para saber qual card é qual.
 *
 * O nome não serve como chave: renomear "Starter" quebraria a ligação. A
 * posição também não: desativar um plano deslocaria os cards e o texto de
 * venda passaria a descrever o plano errado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('nome');
        });

        // Backfill: aceita o nome antigo e o novo, então independe da ordem
        // em que as migrations de plano rodaram
        $fixos = [
            'básico' => 'starter',
            'basico' => 'starter',
            'starter' => 'starter',
            'pro' => 'pro',
            'enterprise' => 'enterprise',
        ];

        foreach (DB::table('planos')->get() as $plano) {
            $chave = Str::lower($plano->nome);
            $slug = $fixos[$chave] ?? Str::slug($plano->nome);

            DB::table('planos')->where('id', $plano->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
