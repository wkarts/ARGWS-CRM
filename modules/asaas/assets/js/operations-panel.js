(function (root) {
  'use strict';
  var sequence = 0;
  function initialize(panel, address) {
    if (panel._asaasOperations) return;
    var base;
    try { base = new URL(address, root.location.href); } catch (_) { return; }
    if (base.origin !== root.location.origin) return;
    var state = {base: base, owners: [], busy: false, loaded: false};
    panel._asaasOperations = state;
    var status = document.createElement('p'), body = document.createElement('div');
    status.setAttribute('role', 'status'); status.textContent = 'Consulte webhooks, eventos, transações e pendências sem sair das configurações.';
    var navigation = document.createElement('div'); navigation.className = 'mbot15';
    [['Webhooks e eventos', base.pathname], ['Transações e conciliação', base.pathname + '/payment_origins']].forEach(function (item) {
      var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-default mright5'; button.textContent = item[0];
      button.addEventListener('click', function () { load(item[1]); }); navigation.appendChild(button);
    });
    panel.appendChild(navigation); panel.appendChild(status); panel.appendChild(body);
    function admitted(address, mutation) {
      var url = new URL(address, base.href);
      var suffix = url.pathname.slice(base.pathname.length);
      var allowed = mutation ? ['/register_integration', '/rotate_integration_token', '/reveal_integration_token', '/disable_integration', '/retry_reviewed_event', '/payment_origins/reconcile'] : ['', '/', '/payment_origins'];
      if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname) || allowed.indexOf(suffix) === -1) throw new Error('unsupported_route');
      url.searchParams.set('panel', '1'); return url;
    }
    function conceal() {
      body.querySelectorAll('#generated-webhook-token').forEach(function (input) { input.type = 'password'; });
      body.querySelectorAll('.js-reveal-webhook-token').forEach(function (button) { button.textContent = 'Revelar'; button.setAttribute('aria-pressed', 'false'); });
    }
    function render(html) {
      var parsed = new DOMParser().parseFromString(html, 'text/html');
      var fragment = parsed.querySelector('[data-asaas-operation-content]');
      if (!fragment) throw new Error('session_or_response_invalid');
      fragment.querySelectorAll('script,iframe,object,embed').forEach(function (element) { element.remove(); });
      var nextOwners = [];
      fragment.querySelectorAll('form').forEach(function (source) {
        var target = admitted(source.getAttribute('action'), true), owner = document.createElement('form');
        owner.id = 'asaas-operation-form-' + (++sequence); owner.method = 'post'; owner.action = target.href; owner.hidden = true;
        // Native CSRF inputs remain associated with this independent form, never settings-form.
        source.querySelectorAll('input,select,textarea,button').forEach(function (control) { control.setAttribute('form', owner.id); });
        owner.addEventListener('submit', function (event) {
          event.preventDefault(); if (!owner.reportValidity()) return;
          load(owner.action, new FormData(owner));
        });
        var group = parsed.createElement('div'); while (source.firstChild) group.appendChild(source.firstChild);
        source.replaceWith(group); nextOwners.push(owner);
      });
      state.owners.forEach(function (owner) { owner.remove(); });
      state.owners = nextOwners; nextOwners.forEach(function (owner) { document.body.appendChild(owner); });
      body.replaceChildren(document.importNode(fragment, true)); state.loaded = true; conceal();
    }
    async function load(address, data) {
      if (state.busy) return;
      var url;
      try { url = admitted(address || base.href, !!data); } catch (_) { status.textContent = 'Destino indisponível neste painel.'; return; }
      state.busy = true; panel.setAttribute('aria-busy', 'true'); status.textContent = data ? 'Aguarde a confirmação da ação. Não repita o envio.' : 'Carregando registros locais…';
      conceal();
      try {
        var response = await root.fetch(url.href, {method: data ? 'POST' : 'GET', body: data, credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok || new URL(response.url || url.href).origin !== base.origin) throw new Error('request_failed');
        render(await response.text()); status.textContent = data ? 'Resposta recebida. Confira o resultado abaixo antes de repetir qualquer ação.' : 'Registros locais carregados. Consultar esta guia não emite cobranças nem altera tokens.';
      } catch (_) { status.textContent = data ? 'Não foi possível confirmar a ação. Não reenvie automaticamente; confira os registros e a sessão antes de tentar novamente.' : 'Não foi possível carregar. Confira sua sessão e permissão de administrador e tente novamente.'; }
      finally { state.busy = false; panel.removeAttribute('aria-busy'); }
    }
    body.addEventListener('click', function (event) {
      var link = event.target.closest('a');
      if (link) {
        var destination = new URL(link.href, base.href);
        if (destination.origin === base.origin && destination.pathname === base.pathname.replace(/\/asaas$/, '/settings') && destination.searchParams.get('group') === 'payment_gateways') {
          event.preventDefault(); panel.dispatchEvent(new CustomEvent('asaas-settings-return', {bubbles: true})); return;
        }
        try { admitted(link.href, false); } catch (_) { return; }
        event.preventDefault(); load(link.href); return;
      }
      var reveal = event.target.closest('.js-reveal-webhook-token');
      if (reveal) {
        var input = body.querySelector('#generated-webhook-token'); if (!input) return;
        var showing = input.type === 'password'; input.type = showing ? 'text' : 'password'; reveal.textContent = showing ? 'Ocultar' : 'Revelar'; reveal.setAttribute('aria-pressed', showing ? 'true' : 'false');
      }
      var copy = event.target.closest('.js-copy-webhook-url');
      if (copy && !copy.disabled) {
        var field = document.getElementById(copy.getAttribute('data-target')), message = copy.closest('.form-group').querySelector('.js-copy-status');
        if (!field || !message || !body.contains(field)) return;
        if (root.navigator.clipboard && root.isSecureContext) root.navigator.clipboard.writeText(field.value).then(function () { message.textContent = 'Valor copiado.'; }, function () { message.textContent = 'Não foi possível copiar; selecione o valor manualmente.'; });
        else message.textContent = 'Selecione e copie o valor manualmente.';
      }
    });
    state.load = load; state.conceal = conceal;
    document.addEventListener('visibilitychange', function () { if (document.hidden) conceal(); });
    root.addEventListener('pagehide', function () { conceal(); var input = body.querySelector('#generated-webhook-token'); if (input) input.value = ''; });
  }
  function open(panel, address) { var state = panel._asaasOperations; if (state && (address || !state.loaded)) state.load(address); }
  function conceal(panel) { var state = panel && panel._asaasOperations; if (state) state.conceal(); }
  root.AsaasOperationsPanel = {initialize: initialize, open: open, conceal: conceal};
})(window);
