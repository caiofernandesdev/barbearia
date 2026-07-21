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
    const suportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

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

            texto.textContent = '✓ Avisos ativados';
            setTimeout(() => { caixa().style.display = 'none'; }, 2500);
        } catch (e) {
            texto.textContent = 'Não foi possível ativar';
            console.error('[push]', e);
        }
    };

    const iniciar = async () => {
        if (!suportado) return;

        try {
            await navigator.serviceWorker.register(@json(asset('sw.js')), { scope: '/' });
        } catch (e) {
            console.error('[push] service worker', e);
            return;
        }

        // Já autorizado e já inscrito? Nada a oferecer.
        if (Notification.permission === 'granted') {
            const reg = await navigator.serviceWorker.ready;
            if (await reg.pushManager.getSubscription()) return;
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
