<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Pages\Financeiro;
use App\Models\Pagamento;
use App\Models\Plano;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retaguarda financeira do SaaS: status de cobrança derivado do vencimento,
 * registro de pagamento e a página do super-admin.
 */
class FinanceiroSaasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00'));
        app()->forgetInstance('current_tenant');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plano(float $preco = 159.90): Plano
    {
        return Plano::forceCreate([
            'nome' => 'P-'.uniqid(), 'slug' => 's-'.uniqid(),
            'preco_mensal' => $preco, 'features' => [], 'ativo' => true,
        ]);
    }

    private function tenant(array $attrs = []): Tenant
    {
        return Tenant::forceCreate(array_merge([
            'slug' => 'fin-'.uniqid(), 'nome' => 'Salão', 'ativo' => true,
            'plano_id' => $this->plano()->id,
            'proximo_vencimento' => '2026-08-10',
        ], $attrs));
    }

    // ─── Status derivado ──────────────────────────────────────────────────────

    public function test_status_em_dia_quando_vencimento_no_futuro(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-08-10']);
        $this->assertSame('em_dia', $t->statusCobranca());
        $this->assertFalse($t->estaAtrasado());
    }

    public function test_status_atrasado_quando_vencimento_passou(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-10']);
        $this->assertSame('atrasado', $t->statusCobranca());
        $this->assertTrue($t->estaAtrasado());
        $this->assertSame(5, $t->diasAtraso());
    }

    public function test_status_vence_em_breve_dentro_de_cinco_dias(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-18']);
        $this->assertSame('vence_em_breve', $t->statusCobranca());
    }

    public function test_plano_sem_valor_e_cortesia(): void
    {
        $t = $this->tenant(['plano_id' => $this->plano(0)->id, 'proximo_vencimento' => '2026-01-01']);
        // Mesmo com vencimento vencido, sem valor não é cobrança atrasada
        $this->assertSame('cortesia', $t->statusCobranca());
        $this->assertFalse($t->estaAtrasado());
    }

    // ─── Registro de pagamento ────────────────────────────────────────────────

    public function test_registrar_pagamento_cria_historico_e_avanca_vencimento(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-10']);

        $t->registrarPagamento(159.90, 'pix', 'julho');

        $this->assertDatabaseHas('pagamentos', [
            'tenant_id' => $t->id, 'valor' => 159.90,
            'competencia' => '2026-07', 'forma' => 'pix',
        ]);
        // Vencimento avança a partir do vencimento antigo, não de hoje
        $this->assertSame('2026-08-10', $t->fresh()->proximo_vencimento->toDateString());
    }

    public function test_pagamento_tira_o_tenant_do_atraso(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-10']);
        $this->assertTrue($t->estaAtrasado());

        $t->registrarPagamento(159.90);

        $this->assertFalse($t->fresh()->estaAtrasado());
    }

    public function test_dois_pagamentos_avancam_dois_meses(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-10']);

        $t->registrarPagamento(159.90);
        $t->fresh()->registrarPagamento(159.90);

        $this->assertSame('2026-09-10', $t->fresh()->proximo_vencimento->toDateString());
        $this->assertSame(2, Pagamento::where('tenant_id', $t->id)->count());
    }

    // ─── Mensalidade personalizada ────────────────────────────────────────────

    public function test_mensalidade_custom_sobrepoe_o_plano(): void
    {
        $t = $this->tenant(['plano_id' => $this->plano(159.90)->id, 'valor_mensalidade' => 99.00]);

        $this->assertSame(99.0, $t->valorMensal());
        $this->assertTrue($t->temMensalidadeCustom());
    }

    public function test_sem_custom_usa_o_preco_do_plano(): void
    {
        $t = $this->tenant(['plano_id' => $this->plano(159.90)->id, 'valor_mensalidade' => null]);

        $this->assertSame(159.90, $t->valorMensal());
        $this->assertFalse($t->temMensalidadeCustom());
    }

    public function test_pagamento_usa_o_valor_efetivamente_pago(): void
    {
        // Mesmo com plano de 159,90, se o acordo é 99 e paga 99, é isso que entra
        $t = $this->tenant(['plano_id' => $this->plano(159.90)->id, 'valor_mensalidade' => 99.00]);

        $t->registrarPagamento(99.00);

        $this->assertDatabaseHas('pagamentos', ['tenant_id' => $t->id, 'valor' => 99.00]);
    }

    // ─── Comprovante ──────────────────────────────────────────────────────────

    public function test_pagamento_guarda_o_comprovante(): void
    {
        $t = $this->tenant();

        $t->registrarPagamento(159.90, 'pix', null, 'comprovantes/nota.pdf');

        $this->assertDatabaseHas('pagamentos', [
            'tenant_id' => $t->id, 'comprovante' => 'comprovantes/nota.pdf',
        ]);
    }

    public function test_download_do_comprovante_exige_super_admin(): void
    {
        $t = $this->tenant();
        Storage::fake('local');
        Storage::disk('local')->put('comprovantes/x.pdf', 'conteudo');
        $pg = $t->registrarPagamento(159.90, 'pix', null, 'comprovantes/x.pdf');

        // Sem login não baixa
        $this->get(route('superadmin.comprovante', $pg))->assertRedirect();

        // Super admin baixa
        $super = User::forceCreate([
            'name' => 'S', 'email' => 'c-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'super_admin', 'tenant_id' => null,
        ]);
        $this->actingAs($super, 'super_admin')
            ->get(route('superadmin.comprovante', $pg))
            ->assertOk();
    }

    public function test_download_404_quando_pagamento_sem_comprovante(): void
    {
        $t = $this->tenant();
        $pg = $t->registrarPagamento(159.90);
        $super = User::forceCreate([
            'name' => 'S', 'email' => 'c-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'super_admin', 'tenant_id' => null,
        ]);

        $this->actingAs($super, 'super_admin')
            ->get(route('superadmin.comprovante', $pg))
            ->assertNotFound();
    }

    // ─── Página do super-admin ────────────────────────────────────────────────

    public function test_pagina_financeiro_carrega_para_super_admin(): void
    {
        $this->tenant();
        $super = User::forceCreate([
            'name' => 'Super', 'email' => 'sa-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'super_admin', 'tenant_id' => null,
        ]);
        $this->actingAs($super, 'super_admin');

        Livewire::test(Financeiro::class)->assertOk();
    }

    public function test_registrar_pagamento_pela_pagina(): void
    {
        $t = $this->tenant(['proximo_vencimento' => '2026-07-10']);
        $super = User::forceCreate([
            'name' => 'Super', 'email' => 'sa-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'super_admin', 'tenant_id' => null,
        ]);
        $this->actingAs($super, 'super_admin');

        Livewire::test(Financeiro::class)
            ->callTableAction('registrar_pagamento', $t, data: [
                'valor' => 159.90, 'forma' => 'pix', 'observacao' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Pagamento::where('tenant_id', $t->id)->count());
        $this->assertSame('2026-08-10', $t->fresh()->proximo_vencimento->toDateString());
    }

    public function test_ajustar_mensalidade_pela_pagina(): void
    {
        $t = $this->tenant(['plano_id' => $this->plano(159.90)->id]);
        $super = User::forceCreate([
            'name' => 'Super', 'email' => 'sa-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'super_admin', 'tenant_id' => null,
        ]);
        $this->actingAs($super, 'super_admin');

        // Define custom
        Livewire::test(Financeiro::class)
            ->callTableAction('ajustar_mensalidade', $t, data: ['valor_mensalidade' => 79.00, 'usar_plano' => false])
            ->assertHasNoTableActionErrors();
        $this->assertSame(79.0, $t->fresh()->valorMensal());

        // Volta ao plano
        Livewire::test(Financeiro::class)
            ->callTableAction('ajustar_mensalidade', $t->fresh(), data: ['valor_mensalidade' => 79.00, 'usar_plano' => true])
            ->assertHasNoTableActionErrors();
        $this->assertNull($t->fresh()->valor_mensalidade);
        $this->assertSame(159.90, $t->fresh()->valorMensal());
    }
}
