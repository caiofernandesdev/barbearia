<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoBarbearia;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Horários de trabalho definidos por dia da semana.
 *
 * 2026-07-20 = segunda (dayOfWeek 1) · 2026-07-25 = sábado (6) · 2026-07-21 = terça (2)
 */
class HorariosPorDiaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00'));
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => [], 'ativo' => true]);
        $this->tenant = Tenant::forceCreate(['slug' => 'hpd-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão',
            'horario_abertura' => '08:00',
            'horario_encerramento' => '18:00',
            'intervalo_minutos' => 60,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60,
            'ativo' => true, 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function profissional(array $attrs = []): Profissional
    {
        return Profissional::forceCreate(array_merge([
            'nome' => 'Ana',
            'ativo' => true,
            'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6],
            'tenant_id' => $this->tenant->id,
        ], $attrs));
    }

    // ─── Model ────────────────────────────────────────────────────────────────

    public function test_horarios_do_dia_usa_a_lista_daquele_dia_quando_ativo(): void
    {
        $p = $this->profissional([
            'horarios_trabalho' => ['07:00'],
            'horarios_por_dia_ativo' => true,
            'horarios_por_dia' => [1 => ['08:00', '09:00'], 6 => ['10:00']],
        ]);

        $this->assertSame(['08:00', '09:00'], $p->horariosDoDia(1), 'segunda usa a lista da segunda');
        $this->assertSame(['10:00'], $p->horariosDoDia(6), 'sábado usa a lista do sábado');
    }

    public function test_horarios_do_dia_cai_na_lista_global_quando_desligado(): void
    {
        $p = $this->profissional([
            'horarios_trabalho' => ['07:00', '08:00'],
            'horarios_por_dia_ativo' => false,
            // Mesmo preenchido, é ignorado enquanto o toggle está desligado
            'horarios_por_dia' => [1 => ['15:00']],
        ]);

        $this->assertSame(['07:00', '08:00'], $p->horariosDoDia(1));
        $this->assertSame(['07:00', '08:00'], $p->horariosDoDia(6));
    }

    public function test_dia_sem_horario_configurado_retorna_vazio(): void
    {
        $p = $this->profissional([
            'horarios_por_dia_ativo' => true,
            'horarios_por_dia' => [1 => ['08:00']],
        ]);

        // Vazio faz o DisponibilidadeService cair no modo gap (expediente inteiro)
        $this->assertSame([], $p->horariosDoDia(2));
    }

    // ─── API pública de horários ──────────────────────────────────────────────

    public function test_api_respeita_horarios_diferentes_por_dia(): void
    {
        $p = $this->profissional([
            'horarios_por_dia_ativo' => true,
            'horarios_por_dia' => [1 => ['08:00', '09:00'], 6 => ['14:00']],
        ]);

        $slug = $this->tenant->slug;

        $segunda = $this->getJson("/{$slug}/api/horarios-disponiveis?data=2026-07-20&servico_id={$this->servico->id}&profissional_id={$p->id}");
        $segunda->assertOk();
        $this->assertSame(['08:00', '09:00'], collect($segunda->json())->pluck('hora')->all());

        $sabado = $this->getJson("/{$slug}/api/horarios-disponiveis?data=2026-07-25&servico_id={$this->servico->id}&profissional_id={$p->id}");
        $sabado->assertOk();
        $this->assertSame(['14:00'], collect($sabado->json())->pluck('hora')->all());
    }

    public function test_lista_global_continua_valendo_para_todos_os_dias(): void
    {
        $p = $this->profissional(['horarios_trabalho' => ['08:00', '09:00']]);
        $slug = $this->tenant->slug;

        foreach (['2026-07-20', '2026-07-25'] as $data) {
            $r = $this->getJson("/{$slug}/api/horarios-disponiveis?data={$data}&servico_id={$this->servico->id}&profissional_id={$p->id}");
            $r->assertOk();
            $this->assertSame(['08:00', '09:00'], collect($r->json())->pluck('hora')->all(), "falhou em {$data}");
        }
    }

    // ─── Grade da lista de espera ─────────────────────────────────────────────

    public function test_grade_horarios_respeita_a_configuracao_por_dia(): void
    {
        $p = $this->profissional([
            'horarios_por_dia_ativo' => true,
            'horarios_por_dia' => [1 => ['08:00', '09:00'], 6 => ['14:00']],
        ]);

        $slug = $this->tenant->slug;

        $this->getJson("/{$slug}/api/grade-horarios?data=2026-07-20&profissional_id={$p->id}")
            ->assertOk()
            ->assertExactJson(['08:00', '09:00']);

        $this->getJson("/{$slug}/api/grade-horarios?data=2026-07-25&profissional_id={$p->id}")
            ->assertOk()
            ->assertExactJson(['14:00']);
    }
}
