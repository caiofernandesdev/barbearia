@php
    $vapid = config('webpush.vapid.public_key');
@endphp

@if ($vapid)
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#f59e0b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Atendix">

{{-- Botão flutuante: só aparece quando dá pra ativar os avisos --}}
<div id="push-atendix" style="display:none; position:fixed; bottom:16px; right:16px; z-index:40;">
    <button type="button" onclick="ativarPushAtendix()"
        style="display:flex; align-items:center; gap:8px; padding:11px 16px; border-radius:999px; border:0;
               background:#f59e0b; color:#111827; font-weight:600; font-size:14px; cursor:pointer;
               box-shadow:0 4px 14px rgba(0,0,0,.25);">
        <span>🔔</span><span id="push-atendix-texto">Ativar avisos neste aparelho</span>
    </button>
</div>

{{-- iPhone no Safari: o iOS só libera push com o site instalado na tela
     inicial. Sem esta instrução o usuário fica sem botão e sem entender. --}}
<div id="push-atendix-ios" style="display:none; position:fixed; bottom:16px; right:16px; left:16px; z-index:40; max-width:360px; margin-left:auto;">
    <div style="position:relative; padding:14px 16px; border-radius:14px; background:#1f2937; color:#f9fafb;
                box-shadow:0 4px 18px rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.12); font-size:13.5px; line-height:1.5;">
        <button type="button" onclick="document.getElementById('push-atendix-ios').style.display='none'"
            aria-label="Fechar"
            style="position:absolute; top:8px; right:10px; background:none; border:0; color:#9ca3af; font-size:18px; cursor:pointer; line-height:1;">×</button>
        <div style="font-weight:700; margin-bottom:4px;">🔔 Receber avisos neste iPhone</div>
        <div style="color:#d1d5db;">
            Toque em <strong>Compartilhar</strong> <span style="opacity:.8;">(o quadrado com a seta)</span>
            e depois em <strong>Adicionar à Tela de Início</strong>. Abra o Atendix por esse ícone
            e o botão de ativar aparece aqui.
        </div>
    </div>
</div>

<script>
(() => {
    const CHAVE = @json($vapid);
    const URL_INSCREVER = @json(route('admin.push.inscrever'));
    const CSRF = @json(csrf_token());

    // O navegador espera a chave em Uint8Array, não em base64url
    const paraBytes = (base64) => {
        const pad = '='.repeat((4 - (base64.length % 4)) % 4);
        const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
    };

    const caixa = () => document.getElementById('push-atendix');
    const caixaIOS = () => document.getElementById('push-atendix-ios');
    const suportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const ehIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    // No iOS o push só existe com o site aberto pelo ícone da tela inicial
    const instalado = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    window.ativarPushAtendix = async () => {
        const texto = document.getElementById('push-atendix-texto');
        try {
            const permissao = await Notification.requestPermission();
            if (permissao !== 'granted') {
                texto.textContent = 'Avisos bloqueados no navegador';
                return;
            }

            const reg = await navigator.serviceWorker.ready;
            const inscricao = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: paraBytes(CHAVE),
            });

            const r = await fetch(URL_INSCREVER, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(inscricao.toJSON()),
            });

            if (!r.ok) throw new Error('falha ao registrar');

            texto.textContent = '✓ Ativado — tocar para testar';
            virarBotaoDeTeste();
        } catch (e) {
            texto.textContent = 'Não foi possível ativar';
            console.error('[push]', e);
        }
    };

    // Depois de ativado o botão vira teste: o profissional confirma sozinho
    // que o aviso chega, sem depender de um agendamento real
    const virarBotaoDeTeste = () => {
        const texto = document.getElementById('push-atendix-texto');
        window.ativarPushAtendix = async () => {
            texto.textContent = 'Enviando…';
            try {
                const r = await fetch(@json(route('admin.push.teste')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                });
                const j = await r.json().catch(() => ({}));
                texto.textContent = r.ok
                    ? 'Enviado — veja a notificação'
                    : (j.motivo || 'Falha ao enviar');
            } catch (e) {
                texto.textContent = 'Falha ao enviar';
                console.error('[push]', e);
            }
            setTimeout(() => { texto.textContent = 'Testar avisos'; }, 4000);
        };
    };

    const iniciar = async () => {
        // iPhone fora da tela inicial: não dá pra ativar, mas dá pra ensinar
        if (ehIOS && !instalado) {
            caixaIOS().style.display = 'block';
            return;
        }

        if (!suportado) return;

        try {
            await navigator.serviceWorker.register(@json(asset('sw.js')), { scope: '/' });
        } catch (e) {
            console.error('[push] service worker', e);
            return;
        }

        // Já autorizado e inscrito: oferece o teste em vez de esconder
        if (Notification.permission === 'granted') {
            const reg = await navigator.serviceWorker.ready;
            if (await reg.pushManager.getSubscription()) {
                document.getElementById('push-atendix-texto').textContent = 'Testar avisos';
                virarBotaoDeTeste();
                caixa().style.display = 'block';
                return;
            }
        }

        // Negado explicitamente: insistir com botão só incomoda
        if (Notification.permission === 'denied') return;

        caixa().style.display = 'block';
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
</script>
@endif
