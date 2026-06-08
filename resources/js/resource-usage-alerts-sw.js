self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(
        payload.title || 'Pelican Resource Alert',
        {
            body: payload.body || '',
            icon: payload.icon || '/favicon.ico',
            badge: payload.badge || '/favicon.ico',
            tag: payload.tag || 'pelican-resource-alert',
            renotify: true,
            data: {
                url: payload.url || '/',
            },
        }
    ));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if ('focus' in client && client.url === targetUrl) {
                return client.focus();
            }
        }

        return clients.openWindow ? clients.openWindow(targetUrl) : undefined;
    }));
});
