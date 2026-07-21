<?php

namespace Tests\Feature;

use App\Models\Plano;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A landing exibe nome e preço vindos dos planos do super admin.
 * A ligação é pelo slug, então renomear não quebra a sincronia e um
 * plano desativado some do site em vez de exibir preço fantasma.
 */
class LandingPrecosTest extends TestCase
{
    use RefreshDatabase;

    private function plano(string $nome, float $preco, ?string $slug = null): Plano
    {
        return Plano::forceCreate([
            'nome' => $nome, 'slug' => $slug ?? strtolower($nome),
            'preco_mensal' => $preco, 'features' => [], 'ativo' => true,
        ]);
    }

    private function tresPlanos(): void
    {
        $this->plano('Starter', 79.90);
        $this->plano('Pro', 159.90);
        $this->plano('Enterprise', 239.90);
    }

    public function test_landing_mostra_o_preco_cadastrado_no_plano(): void
    {
        $this->tresPlanos();

        $this->get('/')
            ->assertOk()
            ->assertSee('R$ 79,90')
            ->assertSee('R$ 159,90')
            ->assertSee('R$ 239,90');
    }

    public function test_mudar_o_preco_no_plano_reflete_na_landing(): void
    {
        $this->tresPlanos();
        $this->get('/')->assertSee('R$ 159,90');

        // É o que o dono faz no super admin
        Plano::where('slug', 'pro')->first()->update(['preco_mensal' => 188.00]);

        $this->get('/')
            ->assertSee('R$ 188')
            ->assertDontSee('R$ 159,90');
    }

    public function test_renomear_o_plano_nao_quebra_a_sincronia(): void
    {
        $this->tresPlanos();
        // Slug continua 'starter'; só o rótulo muda
        Plano::where('slug', 'starter')->first()->update(['nome' => 'Essencial']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Essencial')
            ->assertSee('R$ 79,90');
    }

    public function test_plano_desativado_some_do_site(): void
    {
        $this->tresPlanos();
        Plano::where('slug', 'enterprise')->first()->update(['ativo' => false]);

        $this->get('/')
            ->assertOk()
            // Não pode sobrar preço de plano que não se vende mais
            ->assertDontSee('R$ 239,90')
            // E os outros continuam corretos, sem deslocar
            ->assertSee('R$ 79,90')
            ->assertSee('R$ 159,90');
    }

    public function test_plano_extra_nao_invade_os_cards(): void
    {
        $this->tresPlanos();
        $this->plano('Premium', 499.00);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('R$ 499')
            ->assertSee('R$ 79,90');
    }

    public function test_landing_mostra_os_limites_do_plano(): void
    {
        Plano::forceCreate([
            'nome' => 'Básico', 'slug' => 'starter', 'preco_mensal' => 79.90,
            'features' => [], 'ativo' => true,
            'max_profissionais' => 1, 'max_usuarios' => 2,
        ]);

        $this->get('/')
            ->assertOk()
            // Singular no 1, plural no resto
            ->assertSee('1 profissional · 2 logins');
    }

    public function test_limite_zero_vira_ilimitado(): void
    {
        Plano::forceCreate([
            'nome' => 'Enterprise', 'slug' => 'enterprise', 'preco_mensal' => 239.90,
            'features' => [], 'ativo' => true,
            'max_profissionais' => 0, 'max_usuarios' => 0,
        ]);

        $this->get('/')->assertOk()->assertSee('Profissionais ilimitados · logins ilimitados');
    }

    /**
     * A vitrine não pode negar o que todo plano entrega. WhatsApp e
     * relatórios estão nos três planos — não podem aparecer como ausentes
     * em card nenhum, que era o erro da copy antiga.
     */
    public function test_copy_nao_nega_modulo_que_todo_plano_tem(): void
    {
        $this->tresPlanos();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('✕ WhatsApp', $html);
        $this->assertStringNotContainsString('✕ Relatórios', $html);
    }

    /**
     * Repescagem existe no Pro e no Enterprise, mas não no Básico: só o
     * primeiro card pode marcá-la como ausente.
     */
    public function test_repescagem_so_aparece_como_ausente_no_primeiro_card(): void
    {
        $this->tresPlanos();

        $html = $this->get('/')->assertOk()->getContent();
        $cards = explode('MAIS POPULAR', $html);

        $this->assertStringContainsString('✕ Repescagem', $cards[0], 'Básico não tem repescagem');
        $this->assertStringNotContainsString('✕ Repescagem', $cards[1], 'Pro e Enterprise têm');
    }

    public function test_preco_redondo_nao_mostra_centavos(): void
    {
        $this->plano('Pro', 200.00);

        $this->get('/')->assertSee('R$ 200')->assertDontSee('R$ 200,00');
    }

    public function test_landing_nao_quebra_sem_planos(): void
    {
        $this->get('/')->assertOk()->assertSee('Planos');
    }

    public function test_plano_novo_ganha_slug_sozinho(): void
    {
        $p = Plano::create(['nome' => 'Plano Turbo', 'preco_mensal' => 10, 'features' => []]);

        $this->assertSame('plano-turbo', $p->slug);
    }

    // ─── Migrations ───────────────────────────────────────────────────────────

    public function test_migration_atualiza_precos_sem_renomear(): void
    {
        DB::table('planos')->delete();
        $this->plano('Básico', 97.00, 'starter');
        $this->plano('Pro', 197.00);

        $this->migration('2026_07_21_000001_atualiza_precos_dos_planos')->up();

        // Preço alinhado, nome preservado — quem batiza o plano é o dono
        $this->assertDatabaseHas('planos', ['nome' => 'Básico', 'preco_mensal' => 79.90]);
        $this->assertDatabaseHas('planos', ['nome' => 'Pro', 'preco_mensal' => 159.90]);
    }

    public function test_migration_preserva_preco_ja_customizado(): void
    {
        DB::table('planos')->delete();
        // Dono já tinha ajustado o Pro pelo super admin
        $this->plano('Pro', 250.00);

        $this->migration('2026_07_21_000001_atualiza_precos_dos_planos')->up();

        $this->assertDatabaseHas('planos', ['nome' => 'Pro', 'preco_mensal' => 250.00]);
    }

    public function test_backfill_do_slug_mapeia_basico_para_starter(): void
    {
        DB::table('planos')->delete();
        // Volta a base ao estado anterior à migration
        Schema::table('planos', fn (Blueprint $t) => $t->dropColumn('slug'));
        DB::table('planos')->insert([
            ['nome' => 'Básico', 'preco_mensal' => 97.00, 'ativo' => true],
            ['nome' => 'Enterprise', 'preco_mensal' => 397.00, 'ativo' => true],
        ]);

        $this->migration('2026_07_21_000002_add_slug_to_planos')->up();

        // "Básico" tem que virar 'starter' — é o card que a landing procura
        $this->assertSame('starter', DB::table('planos')->where('nome', 'Básico')->value('slug'));
        $this->assertSame('enterprise', DB::table('planos')->where('nome', 'Enterprise')->value('slug'));
    }

    private function migration(string $arquivo): object
    {
        return require database_path("migrations/{$arquivo}.php");
    }
}
