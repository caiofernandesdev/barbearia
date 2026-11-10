<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A página de confirmação mostra nome e telefone do cliente e tem o botão de
 * cancelar. Ela só abre pelo link assinado gerado ao agendar: trocar o número
 * na URL não pode revelar (nem permitir cancelar) o agendamento de outra pessoa.
 */
class SegurancaAgendamentoPublicoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Agendamento $meu;

    private Agendamento $deOutro;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 's-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        $prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $servico = Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'tenant_id' => $this->tenant->id]);

        $base = [
            'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'data_hora' => now()->addDay()->setTime(10, 0), 'status' => 'confirmado',
            'tenant_id' => $this->tenant->id,
        ];
        $this->meu = Agendamento::forceCreate($base + ['cliente_nome' => 'Eu Mesmo', 'cliente_telefone' => '14911111111']);
        $this->deOutro = Agendamento::forceCreate(array_merge($base, [
            'cliente_nome' => 'Fulano Privado', 'cliente_telefone' => '14922222222',
            'data_hora' => now()->addDay()->setTime(11, 0),
        ]));
    }

    private function linkAssinado(int $id): string
    {
        return URL::signedRoute('agendamento.confirmado', [
            'tenant' => $this->tenant->slug, 'agendamentoId' => $id,
        ], absolute: false);
    }

    public function test_link_assinado_abre_a_confirmacao(): void
    {
        $this->get($this->linkAssinado($this->meu->id))
            ->assertOk()
            ->assertSee('Eu Mesmo');
    }

    public function test_sem_assinatura_nao_abre(): void
    {
        $this->get("/{$this->tenant->slug}/confirmado/{$this->deOutro->id}")
            ->assertForbidden()
            ->assertDontSee('Fulano Privado')
            ->assertDontSee('14922222222');
    }

    public function test_trocar_o_numero_no_link_assinado_nao_abre_o_de_outra_pessoa(): void
    {
        $link = $this->linkAssinado($this->meu->id);
        $adulterado = str_replace("/confirmado/{$this->meu->id}", "/confirmado/{$this->deOutro->id}", $link);

        $this->get($adulterado)
            ->assertForbidden()
            ->assertDontSee('Fulano Privado');
    }
}
