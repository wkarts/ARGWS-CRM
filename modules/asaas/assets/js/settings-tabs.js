(function (root) {
  'use strict';
  var sections = [
    ['authentication', 'Autenticação', ['active', 'api_key', 'api_key_sandbox', 'sandbox']],
    ['security', 'Segurança', []],
    ['payments', 'Pagamentos', ['currencies', 'description', 'interest_value', 'fine_value', 'discount_type', 'discount_value', 'discount_days', 'installmentCount', 'diasdevencimento', 'billet_only', 'card_only', 'pix_only']],
    ['webhook', 'Webhook', ['sync_enabled', 'sync_days', 'sync_limit']],
    ['resources', 'Recursos', []],
    ['diagnostics', 'Diagnóstico', ['debug', 'file_log_enabled']]
  ];
  function sectionFor(key) {
    for (var i = 0; i < sections.length; i++) if (sections[i][2].indexOf(key) !== -1) return sections[i][0];
    return 'resources';
  }
  function environmentValue(values) {
    return values.length === 1 && (values[0] === '0' || values[0] === '1') ? values[0] : '';
  }
  function initialize(doc) {
    var container = doc.getElementById('online_payments_asaas_tab');
    if (!container || container.getAttribute('data-asaas-tabs') === 'ready') return;
    container.setAttribute('data-asaas-tabs', 'ready');
    var environmentMetadata = container.querySelector('[data-asaas-environment-valid]');
    var trustedEnvironment = environmentMetadata && environmentMetadata.getAttribute('data-asaas-environment-valid') === '1' ? environmentValue([environmentMetadata.getAttribute('data-asaas-environment-value')]) : '';
    var groups = Array.prototype.slice.call(container.querySelectorAll('.form-group'));
    var nav = doc.createElement('div'); nav.className = 'nav nav-tabs mtop15 mbot15'; nav.setAttribute('role', 'tablist'); nav.setAttribute('aria-label', 'Configurações Asaas');
    var panels = {}, buttons = {};
    function select(id) {
      Object.keys(panels).forEach(function (key) {
        panels[key].hidden = key !== id;
        buttons[key].setAttribute('aria-selected', key === id ? 'true' : 'false');
        buttons[key].setAttribute('tabindex', key === id ? '0' : '-1');
        buttons[key].className = key === id ? 'btn btn-primary' : 'btn btn-default';
      });
    }
    sections.forEach(function (section, index) {
      var id = section[0], button = doc.createElement('button'), panel = doc.createElement('section');
      button.type = 'button'; button.textContent = section[1]; button.id = 'asaas-settings-tab-' + id;
      button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', 'asaas-settings-panel-' + id);
      panel.id = 'asaas-settings-panel-' + id; panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', button.id);
      panels[id] = panel; buttons[id] = button; nav.appendChild(button);
      button.addEventListener('click', function () { select(id); });
      button.addEventListener('keydown', function (event) {
        var next = event.key === 'ArrowRight' ? (index + 1) % sections.length : event.key === 'ArrowLeft' ? (index + sections.length - 1) % sections.length : event.key === 'Home' ? 0 : event.key === 'End' ? sections.length - 1 : -1;
        if (next >= 0) { event.preventDefault(); select(sections[next][0]); buttons[sections[next][0]].focus(); }
      });
    });
    container.appendChild(nav); sections.forEach(function (section) { container.appendChild(panels[section[0]]); });
    groups.forEach(function (group) {
      var field = group.querySelector('[name^="settings[paymentmethod_asaas_"]');
      if (!field) return;
      var match = field.name.match(/^settings\[paymentmethod_asaas_(.+)\]$/); if (!match) return;
      panels[sectionFor(match[1])].appendChild(group);
      if (match[1] === 'sandbox') {
        var selectElement = doc.createElement('select'); selectElement.name = field.name; selectElement.id = 'asaas-operation-environment'; selectElement.className = 'form-control'; selectElement.required = true;
        [['', 'Selecione o ambiente; configuração inválida exige revisão'], ['1', 'Homologação [sandbox]'], ['0', 'Produção']].forEach(function (option) { var item = doc.createElement('option'); item.value = option[0]; item.textContent = option[1]; selectElement.appendChild(item); });
        selectElement.value = trustedEnvironment;
        var label = doc.createElement('label'); label.htmlFor = selectElement.id; label.textContent = 'Ambiente de novas operações';
        while (group.firstChild) group.removeChild(group.firstChild);
        group.appendChild(label); group.appendChild(selectElement);
        var help = doc.createElement('p'); help.className = 'help-block'; help.textContent = 'Seleção única. Cobranças emitidas preservam sua conta e ambiente de origem. Diagnóstico não seleciona ambiente.'; group.appendChild(help);
      }
      if (match[1] === 'installmentCount') {
        field.readOnly = true;
        var note = doc.createElement('p'); note.className = 'help-block'; note.textContent = 'Parcelamento indisponível: falta conciliação por parcela. O limite legado é preservado, mas o checkout permite apenas cartão à vista.'; group.appendChild(note);
      }
    });
    function note(id, message) { var paragraph = doc.createElement('p'); paragraph.textContent = message; panels[id].insertBefore(paragraph, panels[id].firstChild); }
    note('security', 'Tokens de webhook são gerados no CRM, armazenados somente como hash e copiados na geração. A chave da API não deve ser usada como token. Segredos legados não são exibidos nem rotacionados automaticamente.');
    note('webhook', 'Recebimento, revisão de eventos e status do cadastro remoto são administrados por conta e ambiente. Cadastro local não comprova cadastro remoto.');
    note('diagnostics', 'Diagnóstico e gravação em arquivo são independentes do ambiente. Não use logs para armazenar tokens, chaves ou dados de cartão.');
    note('payments', 'Cartão de crédito usa a página segura do Asaas à vista. Parcelamento e assinatura com cartão salvo permanecem indisponíveis. Cobrança já emitida exige revisão antes de trocar a forma de pagamento.');
    var configuredLink = container.querySelector('[data-asaas-webhook-admin-url]');
    if (configuredLink) ['security', 'webhook'].forEach(function (id) { var link = doc.createElement('a'); link.href = configuredLink.getAttribute('href'); link.textContent = 'Gerenciar webhooks e tokens'; link.className = 'btn btn-default mbot15'; panels[id].appendChild(link); });
    select('authentication');
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = {sectionFor: sectionFor, environmentValue: environmentValue, initialize: initialize};
  if (root.document) { if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', function () { initialize(root.document); }); else initialize(root.document); }
})(typeof window !== 'undefined' ? window : globalThis);
