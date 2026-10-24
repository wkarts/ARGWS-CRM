(() => {
  'use strict';
  const script = document.currentScript;
  const root = new URL(script.dataset.pwaRoot, location.href);
  if (!window.isSecureContext || root.origin !== location.origin || !('serviceWorker' in navigator)) return;
  let installPrompt;
  let panel;
  function notice(text, action, label) {
    if (!panel) {
      panel = document.createElement('aside');
      panel.setAttribute('aria-label', 'Aplicativo');
      panel.style.cssText = 'position:fixed;bottom:12px;right:12px;z-index:10000;max-width:300px;padding:12px;background:white;color:#334155;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 2px 8px #0002;font:14px sans-serif';
      document.body.appendChild(panel);
    }
    panel.replaceChildren();
    const message = document.createElement('p'); message.textContent = text; panel.appendChild(message);
    if (action) {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
      button.addEventListener('click', action); panel.appendChild(button);
    }
    const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Fechar';
    close.style.marginLeft = '12px'; close.addEventListener('click', () => {panel.remove(); panel = null;}); panel.appendChild(close);
  }
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault(); installPrompt = event;
    notice('Instale o CRM para abrir como aplicativo. É necessária conexão à internet.', async () => {
      const prompt = installPrompt; installPrompt = null;
      if (prompt) { await prompt.prompt(); await prompt.userChoice; }
      if (panel) { panel.remove(); panel = null; }
    }, 'Instalar aplicativo');
  });
  window.addEventListener('appinstalled', () => { installPrompt = null; if (panel) {panel.remove(); panel = null;} });
  navigator.serviceWorker.register(new URL('pwa.php?resource=worker', root).href, {
    scope: root.pathname, updateViaCache: 'none'
  }).then(registration => {
    const update = () => notice('Nova versão disponível. Salve seu trabalho e feche todas as janelas do aplicativo e abas do CRM; ao reabrir, a atualização será aplicada.');
    if (registration.waiting) update();
    registration.addEventListener('updatefound', () => {
      const worker = registration.installing;
      if (worker) worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && navigator.serviceWorker.controller) update();
      });
    });
  }).catch(() => { /* Unsupported deployment must not interrupt the CRM. */ });
})();
