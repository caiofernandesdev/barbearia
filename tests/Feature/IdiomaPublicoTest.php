<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoBarbearia;
use App\Models\Tenant;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Idioma do estabelecimento: a página pública de agendamento é renderizada no
 * idioma configurado (pt_BR/en/es), aplicado pelo middleware do tenant.
 */
class IdiomaPublicoTest extends TestCase
{
    use RefreshDatabase;

    private function tenantComIdioma(string $idioma): Tenant
    {
        app()->forgetInstance('current_tenant');

        $tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão', 'ativo' => true]);
        app()->instance('current_tenant', $tenant);
        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão Teste', 'horario_abertura' => '08:00',
            'horario_encerramento' => '19:00', 'intervalo_minutos' => 60,
            'mensalista_limite_cortes_semana' => 1, 'idioma' => $idioma,
            'tenant_id' => $tenant->id,
        ]);
        app()->forgetInstance('current_tenant'); // força o middleware a resolver do zero

        return $tenant;
    }

    public function test_pagina_em_portugues_por_padrao(): void
    {
        $tenant = $this->tenantComIdioma('pt_BR');

        $this->get("/{$tenant->slug}")
            ->assertOk()
            ->assertSee('bem-vindo', false)
            ->assertDontSee('booking.greeting');
    }

    public function test_pagina_em_ingles(): void
    {
        $tenant = $this->tenantComIdioma('en');

        $this->get("/{$tenant->slug}")
            ->assertOk()
            ->assertSee('Welcome to', false)
            ->assertSee('Book an Appointment', false);
    }

    public function test_pagina_em_espanhol(): void
    {
        $tenant = $this->tenantComIdioma('es');

        $this->get("/{$tenant->slug}")
            ->assertOk()
            ->assertSee('Bienvenido', false);
    }

    public function test_pagina_em_italiano(): void
    {
        $tenant = $this->tenantComIdioma('it');

        $this->get("/{$tenant->slug}")
            ->assertOk()
            ->assertSee('Benvenuto', false)
            ->assertSee('Prenota un Appuntamento', false);
    }

    public function test_todos_os_idiomas_tem_as_mesmas_chaves(): void
    {
        $base = array_keys(trans('booking', [], 'pt_BR'));

        foreach (Locale::SUPORTADOS as $locale) {
            $chaves = array_keys(trans('booking', [], $locale));
            $this->assertEqualsCanonicalizing(
                $base, $chaves, "Chaves de tradução divergem em [{$locale}]."
            );
        }
    }

    public function test_painel_tem_as_mesmas_chaves_em_todos_os_idiomas(): void
    {
        $achatar = function (array $arr, string $prefixo = '') use (&$achatar): array {
            $chaves = [];
            foreach ($arr as $k => $v) {
                $chaves = is_array($v)
                    ? array_merge($chaves, $achatar($v, $prefixo.$k.'.'))
                    : array_merge($chaves, [$prefixo.$k]);
            }

            return $chaves;
        };

        $base = $achatar(trans('painel', [], 'pt_BR'));

        foreach (Locale::SUPORTADOS as $locale) {
            $this->assertEqualsCanonicalizing(
                $base, $achatar(trans('painel', [], $locale)),
                "Chaves do painel divergem em [{$locale}]."
            );
        }
    }

    public function test_painel_traduz_config_em_ingles(): void
    {
        // Resolve de verdade (não cai no fallback devolvendo a chave)
        $this->assertSame('Establishment Name', trans('painel.config.nome_label', [], 'en'));
        $this->assertSame('Settings', trans('painel.nav.configuracoes', [], 'en'));
        $this->assertSame('Impostazioni', trans('painel.nav.configuracoes', [], 'it'));
    }

    public function test_chave_de_traducao_nao_vaza_no_html(): void
    {
        $tenant = $this->tenantComIdioma('en');

        // Se o T ficasse vazio, as strings "booking.xxx" apareceriam cruas.
        $this->get("/{$tenant->slug}")
            ->assertOk()
            ->assertDontSee('booking.greeting')
            ->assertDontSee('booking.send');
    }

    public function test_helper_locale_ignora_idioma_invalido(): void
    {
        $tenant = $this->tenantComIdioma('pt_BR');
        ConfiguracaoBarbearia::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->update(['idioma' => 'zz']);
        app()->instance('current_tenant', $tenant->fresh());
        ConfiguracaoBarbearia::getInstance()->refresh();

        Locale::aplicarDoTenant();

        $this->assertSame(Locale::PADRAO, app()->getLocale());
    }
}
