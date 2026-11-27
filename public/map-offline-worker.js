const scope = self.registration.scope;
const cacheName = 'map-offline-v1';
const workspace = new URL('offline', scope).href;
const assets = ['agriculture.css', 'agriculture.js', 'map-logo.png', 'sulop-municipal-hall.jpg']
    .map(path => new URL(path, scope).href);
let workspaceGeneration = 0;

self.addEventListener('install', event => {
    event.waitUntil(caches.open(cacheName).then(cache => cache.addAll(assets)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(Promise.all([
        self.clients.claim(),
        caches.keys().then(names => Promise.all(names
            .filter(name => name.startsWith('map-offline-') && name !== cacheName)
            .map(name => caches.delete(name)))),
    ]));
});

async function clearWorkspace() {
    workspaceGeneration++;
    const cache = await caches.open(cacheName);
    await cache.delete(workspace);
}

async function rememberWorkspace(response, generation) {
    if (!response.ok || !response.headers.has('X-MAP-Offline-Workspace')) return false;
    const cache = await caches.open(cacheName);
    if (generation !== workspaceGeneration) return false;
    await cache.put(workspace, response.clone());
    return true;
}

self.addEventListener('message', event => {
    if (event.data?.type !== 'prepare-workspace') return;
    const generation = ++workspaceGeneration;
    event.waitUntil(fetch(workspace, {cache: 'no-store'})
        .then(response => response.headers.get('X-MAP-Offline-Workspace') === event.data.user
            && rememberWorkspace(response, generation))
        .then(ready => event.ports[0]?.postMessage({ready}))
        .catch(() => event.ports[0]?.postMessage({ready: false})));
});

self.addEventListener('fetch', event => {
    const {request} = event;
    if (!request.url.startsWith(scope)) return;
    const url = new URL(request.url);
    const route = url.pathname.slice(new URL(scope).pathname.length);
    if (request.method === 'POST' && route === 'logout') {
        event.respondWith(clearWorkspace().catch(() => {}).then(() => fetch(request)));
        return;
    }
    if (request.method !== 'GET') return;

    if (assets.includes(request.url)) {
        event.respondWith((async () => {
            const cache = await caches.open(cacheName);
            try {
                const response = await fetch(request);
                if (response.ok) await cache.put(request, response.clone());
                return response;
            } catch {
                return await cache.match(request) ?? Response.error();
            }
        })());
        return;
    }

    if (request.mode !== 'navigate') return;
    if (route === 'login' || route === 'register') {
        event.respondWith(fetch(request).then(async response => {
            if (response.ok && !response.redirected) await clearWorkspace().catch(() => {});
            return response;
        }));
        return;
    }
    if (!['', 'offline', 'access', 'reports', 'farmers/directory'].includes(route) && !route.startsWith('sector/')) return;
    const generation = workspaceGeneration;
    event.respondWith((async () => {
        try {
            const response = await fetch(request, {signal: AbortSignal.timeout(10000)});
            if (response.status < 500) {
                if (route === 'offline') await rememberWorkspace(response, generation).catch(() => {});
                return response;
            }
            const cached = await caches.match(workspace, {cacheName});
            return cached ?? response;
        } catch {
            const cached = await caches.match(workspace, {cacheName});
            return cached ?? new Response('The offline workspace has not been prepared on this device. Reopen MAP when you have a connection.', {
                status: 503,
                headers: {'Content-Type': 'text/plain; charset=utf-8'},
            });
        }
    })());
});
