'use strict';
// Deliberately NO CacheStorage, IndexedDB, background sync or request replay.
// Updates wait until all old clients close; never reload a financial form.
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  const request = event.request;
  const target = new URL(request.url);
  const scope = new URL(self.registration.scope);
  if (request.method !== 'GET' || target.origin !== scope.origin ||
      !target.pathname.startsWith(scope.pathname)) return;
  // Only top-level navigation needs a generic offline screen. APIs/assets/forms
  // otherwise retain native browser network behavior; nothing is persisted.
  if (request.mode !== 'navigate') return;
  const offline = () => new Response(
    '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sem conexão</title><body><main><h1>Sem conexão</h1><p>Conecte-se à internet e recarregue esta página. Dados e operações financeiras não estão disponíveis offline.</p></main></body></html>',
    {status: 503, headers: {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'Content-Security-Policy': "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"}}
  );
  // Chromium can leave a service-worker navigation fetch fulfilled from its
  // in-memory loader while network emulation is offline. Honor the browser's
  // explicit offline state before attempting any network request; the catch
  // below still covers a real transport failure when onLine is stale.
  const network = self.navigator && self.navigator.onLine === false
    ? Promise.reject(new Error('offline'))
    : fetch(request, {cache: 'no-store'});
  event.respondWith(network.catch(offline));
});
