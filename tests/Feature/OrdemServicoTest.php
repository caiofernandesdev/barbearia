<?php

namespace Tests\Feature;

use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ordem de exibição dos serviços: serviço novo vai para o fim (nunca 0),
 * e o backfill empurra os zerados para depois dos numerados à mão.
 */
class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current_tenant');
        $this->tenant = Tenant::forceCreate(['slug' => 'ord-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
    }

    private function servico(string $nome, ?int $ordem = null): Servico
    {
        return Servico::create(array_filter([
            'nome' => $nome, 'preco' => 40, 'duracao_minutos' => 30,
            'ordem' => $ordem,
        ], fn ($v) => $v !== null));
    }

    public function test_servico_novo_vai_para_o_fim(): void
    {
        $this->servico('A', 1);
        $this->servico('B', 2);
        // Sem ordem definida → recebe max + 1 = 3, não 0
        $novo = $this->servico('C');

        $this->assertSame(3, $novo->ordem);
    }

    public function test_primeiro_servico_comeca_em_um(): void
    {
        $novo = $this->servico('Primeiro');
        $this->assertSame(1, $novo->ordem);
    }

    public function test_ordem_explicita_e_respeitada(): void
    {
        $s = $this->servico('Com ordem', 5);
        $this->assertSame(5, $s->ordem);
    }

    public function test_lista_ordena_numerados_antes_dos_novos(): void
    {
        $this->servico('Segundo', 2);
        $this->servico('Primeiro', 1);
        $this->servico('SemOrdem'); // vira 3

        $ordem = Servico::orderBy('ordem')->pluck('nome')->all();
        $this->assertSame(['Primeiro', 'Segundo', 'SemOrdem'], $ordem);
    }

    public function test_backfill_empurra_zerados_para_o_fim(): void
    {
        // Simula o estado antigo: alguns numerados, resto em 0
        DB::table('servicos')->insert([
            ['tenant_id' => $this->tenant->id, 'nome' => 'Num1', 'preco' => 10, 'duracao_minutos' => 30, 'ordem' => 1, 'ativo' => 1, 'destaque' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenant->id, 'nome' => 'Num2', 'preco' => 10, 'duracao_minutos' => 30, 'ordem' => 2, 'ativo' => 1, 'destaque' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenant->id, 'nome' => 'ZeroA', 'preco' => 10, 'duracao_minutos' => 30, 'ordem' => 0, 'ativo' => 1, 'destaque' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenant->id, 'nome' => 'ZeroB', 'preco' => 10, 'duracao_minutos' => 30, 'ordem' => 0, 'ativo' => 1, 'destaque' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        (require database_path('migrations/2026_07_23_000001_backfill_ordem_servicos.php'))->up();

        $ordem = Servico::orderBy('ordem')->pluck('nome')->all();
        // Os numerados continuam na frente; os zerados vão para o fim
        $this->assertSame(['Num1', 'Num2', 'ZeroA', 'ZeroB'], $ordem);
        $this->assertSame(0, Servico::where('ordem', 0)->count(), 'não pode sobrar nenhum em 0');
    }
}
