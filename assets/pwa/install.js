(() => {
  'use strict';
  const script = document.currentScript;
  if (!script || !script.dataset.pwaRoot || !window.isSecureContext || !('serviceWorker' in navigator)) return;
  let root;
  try { root = new URL(script.dataset.pwaRoot, location.href); } catch (_) { return; }
  if (root.origin !== location.origin || window.__crmPwaRegistration) return;

  // Installation, eligibility, dismissal and standalone state belong to the
  // browser. Do not intercept beforeinstallprompt or create per-page invitations.
  // Registering the same script/scope across documents is idempotent; the promise
  // also prevents duplicate registration when a layout includes this asset twice.
  const revision = script.dataset.pwaRevision;
  const workerUrl = new URL('pwa.php?resource=worker', root);
  if (revision && /^[a-f0-9]{64}$/.test(revision)) workerUrl.searchParams.set('v', revision);
  window.__crmPwaRegistration = navigator.serviceWorker.register(
    workerUrl.href,
    {scope: root.pathname, updateViaCache: 'none'}
  ).catch(() => { /* Unsupported deployment must not interrupt the CRM. */ });
  // Updates use the worker lifecycle. Never reload an unsaved CRM/financial form.
})();
