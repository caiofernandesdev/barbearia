<?php

namespace Tests\Feature;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rate-limiting anti-ban do envio de WhatsApp: espaçamento entre mensagens e
 * teto diário por número. Http::fake intercepta a chamada à Evolution.
 */
class WhatsAppRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        app()->forgetInstance('current_tenant');

        return Tenant::forceCreate([
            'slug' => 'rl-'.uniqid(), 'nome' => 'X', 'whatsapp_ativo' => true,
            'whatsapp_config' => ['base_url' => 'http://evo.local', 'api_key' => 'k', 'instance' => 'i'],
        ]);
    }

    public function test_envia_quando_intervalo_respeitado_e_conta_no_dia(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->tenant();

        (new EnviarWhatsAppJob('11987654321', 'oi', $tenant->id))->handle();

        Http::assertSentCount(1);
        $this->assertSame(1, (int) Cache::get("wa-count:{$tenant->id}:".now()->toDateString()));
    }

    public function test_envio_muito_proximo_do_anterior_nao_dispara(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->tenant();

        // Marca que acabou de enviar agora → o próximo deve ser adiado (release), sem HTTP
        Cache::put("wa-ritmo:{$tenant->id}", microtime(true), now()->addMinutes(10));

        (new EnviarWhatsAppJob('11987654321', 'oi', $tenant->id))->handle();

        Http::assertNothingSent();
    }

    public function test_limite_diario_bloqueia_o_envio(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $tenant = $this->tenant();

        Cache::put("wa-count:{$tenant->id}:".now()->toDateString(), 250, now()->endOfDay());

        (new EnviarWhatsAppJob('11987654321', 'oi', $tenant->id))->handle();

        Http::assertNothingSent();
    }
}
