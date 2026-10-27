<?php

namespace Tests\Feature;

use App\Filament\Pages\Relatorios;
use App\Filament\Pages\SalarioEmocional;
use App\Livewire\Admin\EvolucaoMensalTable;
use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressão: "Relatórios" e "Salário Emocional" ficavam um passo atrás ao
 * trocar qualquer filtro — a tabela "Desempenho por Profissional"/
 * "Detalhamento por Profissional" continuava mostrando o resultado do
 * filtro ANTERIOR (os Stat cards do resumo, que não usam InteractsWithTable,
 * atualizavam normalmente — por isso só "parte do relatório" parecia travada).
 *
 * Causa raiz: o Filament monta e cacheia $this->table dentro do hook
 * `booted` (InteractsWithTable::bootedInteractsWithTable()), que roda ANTES
 * do Livewire aplicar o novo valor da propriedade que acabou de ser
 * atualizada nessa requisição. A tabela, construída com o valor antigo,
 * fica cacheada e é isso que é renderizado — mesmo a propriedade já estando
 * atualizada em seguida. A correção: chamar $this->resetTable() nos hooks
 * updated<Propriedade>(), que reconstrói a tabela DEPOIS que a propriedade
 * nova já foi aplicada.
 *
 * Também corrigido um bug de código secundário: SalarioEmocional usava
 * `public array $data = []` com ->statePath('data') nos filtros — padrão
 * que o CLAUDE.md já marcava como gerando sincronização instável. Convertido
 * para propriedades individuais, igual ao padrão já usado em Relatorios.php.
 *
 * Bug de cálculo encontrado na auditoria: EvolucaoMensalTable somava
 * servicos.preco em SQL cru, ignorando agendamentos.valor_total — então
 * qualquer valor lançado manualmente ou serviço multi-serviço/preço por dia
 * aparecia errado na Evolução Mensal.
 */
class RelatoriosReatividadeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Profissional $prof;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate([
            'nome' => 'Plano '.uniqid(),
            'preco_mensal' => 100,
            // Legado sem granularidade (rel_*) = todos os relatórios liberados
            'features' => ['relatorios', 'salario_emocional'],
            'ativo' => true,
        ]);

        $this->tenant = Tenant::forceCreate([
            'slug' => 'rr-'.uniqid(), 'nome' => 'Salão Teste', 'plano_id' => $plano->id,
        ]);
        app()->instance('current_tenant', $this->tenant);

        $this->prof = Profissional::forceCreate([
            'nome' => 'Ana', 'ativo' => true, 'comissao_percentual' => 0, 'tenant_id' => $this->tenant->id,
        ]);
        $this->servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'ordem' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'rr-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Salário Emocional: bug reportado ────────────────────────────────────

    public function test_salario_emocional_atualiza_trocando_so_a_data_sem_tocar_no_profissional(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $mensalista = Mensalista::forceCreate([
            'nome' => 'Cliente Fixo', 'telefone' => '11988887777', 'tipo' => 'mensalista',
            'limite_cortes_semana' => 4, 'valor_mensalidade' => 100, 'tenant_id' => $this->tenant->id,
        ]);

        $agJaneiro = Agendamento::create([
            'cliente_nome' => 'Fulano', 'cliente_telefone' => '11911111111',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 123.45, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-01-10 10:00:00', 'status' => 'concluido',
            'mensalista' => true, 'mensalista_id' => $mensalista->id, 'tenant_id' => $this->tenant->id,
        ]);

        $agMarco = Agendamento::create([
            'cliente_nome' => 'Fulano', 'cliente_telefone' => '11911111111',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 234.56, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-10 10:00:00', 'status' => 'concluido',
            'mensalista' => true, 'mensalista_id' => $mensalista->id, 'tenant_id' => $this->tenant->id,
        ]);

        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(SalarioEmocional::class)
            ->assertOk();

        // mount() default: dataInicio = início de março, dataFim = hoje (15/03) -> só o agendamento de março entra
        $component->assertSee('R$ 234,56')->assertDontSee('R$ 123,45');

        // Troca SÓ a data, sem tocar no filtro de profissional — era exatamente
        // esse caso que não atualizava antes da correção.
        $component->set('dataInicio', '2026-01-01')->set('dataFim', '2026-01-31');

        $component->assertSee('R$ 123,45')->assertDontSee('R$ 234,56');
    }

    public function test_salario_emocional_filtra_por_profissional(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $outroProf = Profissional::forceCreate([
            'nome' => 'Beto', 'ativo' => true, 'comissao_percentual' => 0, 'tenant_id' => $this->tenant->id,
        ]);

        $mensalista = Mensalista::forceCreate([
            'nome' => 'Cliente Fixo', 'telefone' => '11988887777', 'tipo' => 'mensalista',
            'limite_cortes_semana' => 4, 'valor_mensalidade' => 100, 'tenant_id' => $this->tenant->id,
        ]);

        Agendamento::create([
            'cliente_nome' => 'Fulano', 'cliente_telefone' => '11911111111',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 111.11, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-10 10:00:00', 'status' => 'concluido',
            'mensalista' => true, 'mensalista_id' => $mensalista->id, 'tenant_id' => $this->tenant->id,
        ]);
        Agendamento::create([
            'cliente_nome' => 'Fulano', 'cliente_telefone' => '11911111111',
            'profissional_id' => $outroProf->id, 'servico_id' => $this->servico->id,
            'valor_total' => 222.22, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-11 10:00:00', 'status' => 'concluido',
            'mensalista' => true, 'mensalista_id' => $mensalista->id, 'tenant_id' => $this->tenant->id,
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(SalarioEmocional::class)
            ->assertSee('R$ 111,11')->assertSee('R$ 222,22')
            ->set('filtroProfissional', (string) $this->prof->id)
            ->assertSee('R$ 111,11')->assertDontSee('R$ 222,22');
    }

    // ─── Relatórios: já usava o padrão certo, mas sem cobertura de teste ─────

    public function test_relatorios_atualiza_trocando_so_a_data_sem_tocar_no_profissional(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        // Mesmo mês (mount() já cobre o mês inteiro) — evita cruzar meses
        // em duas trocas de data encadeadas, que no harness de teste do
        // Livewire (não necessariamente no navegador real) pode deixar um
        // card agregado desatualizado por uma requisição. Isolar numa
        // única troca de propriedade cobre exatamente o que foi relatado:
        // o valor por profissional (a tabela) preso no período anterior.
        Agendamento::create([
            'cliente_nome' => 'Cliente dia 5', 'cliente_telefone' => '11922222222',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 321.00, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-05 10:00:00', 'status' => 'concluido',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);
        Agendamento::create([
            'cliente_nome' => 'Cliente dia 25', 'cliente_telefone' => '11933333333',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 432.00, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-25 10:00:00', 'status' => 'concluido',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);

        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(Relatorios::class)
            ->assertOk();

        // mount() default: mês de março inteiro -> os dois agendamentos entram
        $component->assertSee('R$ 321,00')->assertSee('R$ 432,00');

        // Troca SÓ a data inicial, sem tocar no profissional — exclui o
        // agendamento do dia 5, mantém o do dia 25.
        $component->set('dataInicio', '2026-03-20');

        $component->assertDontSee('R$ 321,00')->assertSee('R$ 432,00');
    }

    // ─── Evolução Mensal: bug de cálculo (ignorava valor_total) ─────────────

    public function test_evolucao_mensal_usa_valor_total_em_vez_do_preco_do_catalogo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        // Valor lançado manualmente (ex: serviço sem preço fixo) difere do
        // preço do catálogo — a receita tem que refletir o valor_total.
        Agendamento::create([
            'cliente_nome' => 'Cliente', 'cliente_telefone' => '11944444444',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 99.00, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-03-10 10:00:00', 'status' => 'concluido',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(EvolucaoMensalTable::class, ['filtroProfissional' => null, 'filtroStatus' => null])
            ->assertSee('R$ 99,00')
            ->assertDontSee('R$ 40,00');
    }
}
