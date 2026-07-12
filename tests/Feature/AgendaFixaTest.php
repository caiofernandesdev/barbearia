<?php

namespace Tests\Feature;

use App\Filament\Pages\AgendaFixa;
use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgendaFixaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Mensalista $cliente;

    private Profissional $prof;

    private Servico $unha;

    private Servico $reparo;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate([
            'slug' => 'af-'.uniqid(), 'nome' => 'Salão', 'whatsapp_ativo' => true,
        ]);
        app()->instance('current_tenant', $this->tenant);

        $this->cliente = Mensalista::forceCreate([
            'nome' => 'Dona Maria', 'telefone' => '11987654321',
            'tipo' => 'mensalista_fixo', 'tenant_id' => $this->tenant->id,
        ]);
        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->unha = Servico::forceCreate(['nome' => 'Unha', 'preco' => 50, 'duracao_minutos' => 60, 'tenant_id' => $this->tenant->id]);
        $this->reparo = Servico::forceCreate(['nome' => 'Reparo', 'preco' => 25, 'duracao_minutos' => 30, 'tenant_id' => $this->tenant->id]);
    }

    private function pagina(): AgendaFixa
    {
        $p = new AgendaFixa;
        $p->mount();
        $p->mensalistaId = $this->cliente->id;
        $p->profissionalId = $this->prof->id;
        $p->hora = '14:00';

        return $p;
    }

    public function test_lista_as_ocorrencias_do_dia_da_semana_no_mes(): void
    {
        $p = $this->pagina();
        $p->mes = '2026-07'; // julho/2026
        $p->diaSemana = 2;    // terça

        $ocorrencias = $p->getOcorrenciasProperty();

        // Terças de julho/2026: 7, 14, 21, 28
        $this->assertCount(4, $ocorrencias);
        $this->assertSame('2026-07-07', $ocorrencias[0]['data']);
        $this->assertSame('2026-07-28', $ocorrencias[3]['data']);
    }

    public function test_gera_agendamentos_so_para_datas_com_servico(): void
    {
        $p = $this->pagina();
        $p->mes = '2026-07';
        $p->diaSemana = 2;

        // Alternância: unha nas semanas 1 e 3; reparo nas 2 e 4
        $p->servicoPorData = [
            '2026-07-07' => $this->unha->id,
            '2026-07-14' => $this->reparo->id,
            '2026-07-21' => $this->unha->id,
            '2026-07-28' => $this->reparo->id,
        ];

        $p->gerar();

        $ags = Agendamento::withoutGlobalScopes()->where('mensalista_id', $this->cliente->id)->get();
        $this->assertCount(4, $ags);
        // todos às 14h, do profissional certo, como pendente (whatsapp on)
        $this->assertTrue($ags->every(fn ($a) => $a->data_hora->format('H:i') === '14:00'));
        $this->assertTrue($ags->every(fn ($a) => $a->status === 'pendente'));
        $this->assertTrue($ags->every(fn ($a) => $a->mensalista === true));
    }

    public function test_datas_sem_servico_sao_ignoradas(): void
    {
        $p = $this->pagina();
        $p->mes = '2026-07';
        $p->diaSemana = 2;
        $p->servicoPorData = [
            '2026-07-07' => $this->unha->id,
            '2026-07-14' => '', // não vem
            '2026-07-21' => $this->reparo->id,
        ];

        $p->gerar();

        $this->assertSame(2, Agendamento::withoutGlobalScopes()->count());
    }

    public function test_nao_duplica_agendamento_no_mesmo_horario(): void
    {
        $p = $this->pagina();
        $p->mes = '2026-07';
        $p->diaSemana = 2;
        $p->servicoPorData = ['2026-07-07' => $this->unha->id];

        $p->gerar();
        // roda de novo com a mesma data
        $p->servicoPorData = ['2026-07-07' => $this->reparo->id];
        $p->gerar();

        $this->assertSame(1, Agendamento::withoutGlobalScopes()->count());
    }

    public function test_pagina_renderiza_para_admin_com_modulo_mensalistas(): void
    {
        $plano = Plano::forceCreate([
            'nome' => 'P-'.uniqid(), 'preco_mensal' => 100,
            'features' => ['mensalistas', 'agenda_fixa'], 'ativo' => true,
        ]);
        $this->tenant->update(['plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant->fresh());

        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'af-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/agenda-fixa?mensalista='.$this->cliente->id)
            ->assertOk()
            ->assertSee('Quem e quando');
    }

    public function test_whatsapp_desligado_gera_confirmado(): void
    {
        $this->tenant->update(['whatsapp_ativo' => false]);
        app()->instance('current_tenant', $this->tenant->fresh());

        $p = $this->pagina();
        $p->mes = '2026-07';
        $p->diaSemana = 2;
        $p->servicoPorData = ['2026-07-07' => $this->unha->id];

        $p->gerar();

        $this->assertSame('confirmado', Agendamento::withoutGlobalScopes()->first()->status);
    }
}
