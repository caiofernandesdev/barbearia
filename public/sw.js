/* Service worker do Atendix — recebe as notificações push do painel.
   Fica na raiz de propósito: assim o escopo cobre /admin. */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let dados = { title: 'Atendix', body: '', url: '/admin' };

    try {
        if (event.data) {
            dados = Object.assign(dados, event.data.json());
        }
    } catch (e) {
        // Payload não-JSON: mostra como texto puro em vez de engolir o aviso
        if (event.data) dados.body = event.data.text();
    }

    event.waitUntil(
        self.registration.showNotification(dados.title, {
            body: dados.body,
            icon: '/images/logo-icone.png',
            badge: '/images/logo-icone.png',
            tag: dados.tag || 'atendix-aviso',
            data: { url: dados.url || '/admin' },
        })
    );
});

/* Clicar no aviso foca a aba já aberta em vez de abrir outra */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destino = (event.notification.data && event.notification.data.url) || '/admin';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((abas) => {
            for (const aba of abas) {
                if (aba.url.includes('/admin') && 'focus' in aba) {
                    aba.navigate(destino);
                    return aba.focus();
                }
            }
            return self.clients.openWindow(destino);
        })
    );
});
