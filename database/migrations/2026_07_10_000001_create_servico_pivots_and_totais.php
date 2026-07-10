<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Serviços que cada profissional realiza (vazio = todos) ──────────
        Schema::create('profissional_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->unique(['profissional_id', 'servico_id']);
        });

        // ── Serviços de cada agendamento (multi-serviço) ─────────────────────
        // agendamentos.servico_id continua preenchido com o PRIMEIRO serviço
        // para retrocompatibilidade com relatórios e telas existentes
        Schema::create('agendamento_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->unique(['agendamento_id', 'servico_id']);
        });

        // ── Totais denormalizados: soma de preço/duração dos serviços ───────
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->decimal('valor_total', 10, 2)->nullable()->after('servico_id');
            $table->integer('duracao_total_minutos')->nullable()->after('valor_total');
        });

        // ── Backfill dos agendamentos existentes (1 serviço cada) ───────────
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE agendamentos a
                JOIN servicos s ON s.id = a.servico_id
                SET a.valor_total = s.preco, a.duracao_total_minutos = s.duracao_minutos
                WHERE a.valor_total IS NULL
            ');
            DB::statement('
                INSERT INTO agendamento_servico (agendamento_id, servico_id)
                SELECT id, servico_id FROM agendamentos WHERE servico_id IS NOT NULL
            ');
        } else {
            DB::table('agendamentos')->orderBy('id')->chunkById(500, function ($ags) {
                foreach ($ags as $ag) {
                    $s = DB::table('servicos')->find($ag->servico_id);
                    if ($s) {
                        DB::table('agendamentos')->where('id', $ag->id)->update([
                            'valor_total' => $s->preco,
                            'duracao_total_minutos' => $s->duracao_minutos,
                        ]);
                    }
                    DB::table('agendamento_servico')->insertOrIgnore([
                        'agendamento_id' => $ag->id,
                        'servico_id' => $ag->servico_id,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn(['valor_total', 'duracao_total_minutos']);
        });
        Schema::dropIfExists('agendamento_servico');
        Schema::dropIfExists('profissional_servico');
    }
};
