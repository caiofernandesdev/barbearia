<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\ListaEspera;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ListaEsperaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00'));
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => ['lista_espera'], 'ativo' => true]);
        $this->tenant = Tenant::forceCreate(['slug' => 'le-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);

        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->servico = Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60, 'tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'cliente_nome' => 'Maria',
            'cliente_telefone' => '11987654321',
            'profissional_id' => $this->prof->id,
            'servico_id' => $this->servico->id,
            'data' => '2026-07-20',
            'hora_preferida' => '14:00',
        ], $o);
    }

    public function test_cliente_entra_na_lista_de_espera(): void
    {
        $resp = $this->postJson("/{$this->tenant->slug}/lista-espera", $this->payload());

        $resp->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, ListaEspera::withoutGlobalScopes()->count());
        $le = ListaEspera::withoutGlobalScopes()->first();
        $this->assertSame('aguardando', $le->status);
        $this->assertSame('14:00', $le->hora_preferida);
    }

    public function test_nao_duplica_mesmo_pedido(): void
    {
        $this->postJson("/{$this->tenant->slug}/lista-espera", $this->payload());
        $this->postJson("/{$this->tenant->slug}/lista-espera", $this->payload());

        $this->assertSame(1, ListaEspera::withoutGlobalScopes()->count());
    }

    public function test_sem_o_modulo_retorna_404(): void
    {
        $plano = Plano::forceCreate(['nome' => 'Basic-'.uniqid(), 'preco_mensal' => 50, 'features' => ['mensalistas'], 'ativo' => true]);
        $this->tenant->update(['plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant->fresh());

        $this->postJson("/{$this->tenant->slug}/lista-espera", $this->payload())->assertNotFound();
        $this->assertSame(0, ListaEspera::withoutGlobalScopes()->count());
    }

    public function test_grade_horarios_retorna_o_expediente_do_profissional(): void
    {
        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'X', 'horario_abertura' => '08:00', 'horario_encerramento' => '11:00',
            'intervalo_minutos' => 60, 'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);

        $resp = $this->getJson("/{$this->tenant->slug}/api/grade-horarios?profissional_id={$this->prof->id}&data=2026-07-20");

        $resp->assertOk();
        // 08–11h de hora em hora → 08:00, 09:00, 10:00
        $this->assertEqualsCanonicalizing(['08:00', '09:00', '10:00'], $resp->json());
    }

    public function test_admin_ve_a_pagina_lista_de_espera(): void
    {
        ListaEspera::forceCreate([
            'tenant_id' => $this->tenant->id, 'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'cliente_nome' => 'Maria Espera', 'cliente_telefone' => '11987654321',
            'data' => '2026-07-20', 'hora_preferida' => '14:00', 'status' => 'aguardando',
        ]);

        $admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'le-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/listas-espera/lista-esperas')
            ->assertOk()
            ->assertSee('Maria Espera');
    }

    public function test_encaixar_cria_agendamento_quando_livre(): void
    {
        $le = ListaEspera::forceCreate([
            'tenant_id' => $this->tenant->id, 'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'cliente_nome' => 'Maria', 'cliente_telefone' => '11987654321',
            'data' => '2026-07-20', 'hora_preferida' => '14:00', 'status' => 'aguardando',
        ]);

        // Simula a ação "Encaixar": cria agendamento se não houver conflito
        $inicio = Carbon::parse('2026-07-20 14:00');
        $this->assertFalse(Agendamento::temConflito($this->prof->id, $inicio, 60, $this->tenant->id));

        Agendamento::create([
            'cliente_nome' => $le->cliente_nome, 'cliente_telefone' => $le->cliente_telefone,
            'profissional_id' => $le->profissional_id, 'servico_id' => $le->servico_id,
            'data_hora' => $inicio, 'tenant_id' => $this->tenant->id,
        ]);
        $le->update(['status' => 'encaixado']);

        $this->assertSame(1, Agendamento::withoutGlobalScopes()->count());
        $this->assertSame('encaixado', $le->fresh()->status);
    }
}
