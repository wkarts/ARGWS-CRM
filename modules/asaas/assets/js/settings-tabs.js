(function (root) {
  'use strict';
  var sections = [
    ['authentication', 'Autenticação', ['active', 'api_key', 'api_key_sandbox', 'sandbox', 'label', 'default_selected']],
    ['security', 'Segurança', []],
    ['payments', 'Pagamentos', ['currencies', 'description', 'interest_value', 'fine_value', 'discount_type', 'discount_value', 'discount_days', 'installmentCount', 'diasdevencimento', 'billet_only', 'card_only', 'pix_only']],
    ['webhook', 'Webhook', ['sync_enabled', 'sync_days', 'sync_limit']],
    ['resources', 'Recursos', []],
    ['diagnostics', 'Diagnóstico', ['debug', 'file_log_enabled']]
  ];
  function settingParts(key) {
    var profile = key.match(/^profile_(production|sandbox)_(.+)$/);
    return profile ? {environment: profile[1], key: profile[2]} : {environment: null, key: key};
  }
  function sectionFor(key) {
    key = settingParts(key).key;
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
    var profileNotes = {};
    Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-profile-note]'), function (note) {
      profileNotes[note.getAttribute('data-asaas-profile-note')] = note;
    });
    var wrapper = doc.createElement('div'); wrapper.className = 'horizontal-scrollable-tabs panel-full-width-tabs mtop15 mbot15';
    ['left', 'right'].forEach(function (direction) {
      var arrow = doc.createElement('div'), icon = doc.createElement('i');
      arrow.className = 'scroller arrow-' + direction;
      icon.className = 'fa fa-angle-' + direction; arrow.appendChild(icon); wrapper.appendChild(arrow);
    });
    var horizontal = doc.createElement('div'); horizontal.className = 'horizontal-tabs'; wrapper.appendChild(horizontal);
    var nav = doc.createElement('ul'); nav.className = 'nav nav-tabs nav-tabs-horizontal'; nav.setAttribute('role', 'tablist'); nav.setAttribute('aria-label', 'Configurações Asaas'); horizontal.appendChild(nav);
    var content = doc.createElement('div'); content.className = 'tab-content';
    var panels = {}, tabs = {}, profilePanels = {};
    function concealSecrets() {
      Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-secret-input]'), function (input) { input.type = 'password'; });
      Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-reveal]'), function (button) { button.textContent = 'Revelar'; button.setAttribute('aria-pressed', 'false'); });
    }
    function synchronize(id) {
      Object.keys(panels).forEach(function (key) {
        panels[key].hidden = key !== id;
        panels[key].classList.toggle('active', key === id);
        tabs[key].parentNode.classList.toggle('active', key === id);
        tabs[key].setAttribute('aria-selected', key === id ? 'true' : 'false');
        tabs[key].setAttribute('aria-expanded', key === id ? 'true' : 'false');
        tabs[key].setAttribute('tabindex', key === id ? '0' : '-1');
      });
    }
    function select(id) {
      concealSecrets();
      if (root.jQuery && root.jQuery.fn.tab) root.jQuery(tabs[id]).tab('show');
      synchronize(id);
    }
    sections.forEach(function (section, index) {
      var id = section[0], item = doc.createElement('li'), tab = doc.createElement('a'), panel = doc.createElement('div');
      item.setAttribute('role', 'presentation');
      tab.textContent = section[1]; tab.id = 'asaas-settings-tab-' + id; tab.href = '#asaas-settings-panel-' + id;
      tab.setAttribute('role', 'tab'); tab.setAttribute('data-toggle', 'tab'); tab.setAttribute('data-settings-parent-tab', container.id); tab.setAttribute('aria-controls', 'asaas-settings-panel-' + id);
      panel.id = 'asaas-settings-panel-' + id; panel.className = 'tab-pane'; panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id);
      panels[id] = panel; tabs[id] = tab; item.appendChild(tab); nav.appendChild(item); content.appendChild(panel);
      tab.addEventListener('click', function (event) { event.preventDefault(); select(id); });
      tab.addEventListener('keydown', function (event) {
        var next = event.key === 'ArrowRight' ? (index + 1) % sections.length : event.key === 'ArrowLeft' ? (index + sections.length - 1) % sections.length : event.key === 'Home' ? 0 : event.key === 'End' ? sections.length - 1 : -1;
        if (next >= 0) { event.preventDefault(); select(sections[next][0]); tabs[sections[next][0]].focus(); }
        else if (event.key === ' ' || event.key === 'Enter') { event.preventDefault(); select(id); }
      });
    });
    container.appendChild(wrapper); container.appendChild(content);
    if (root.jQuery) {
      root.jQuery(nav).on('shown.bs.tab', 'a[data-toggle="tab"]', function () {
        synchronize(this.id.slice('asaas-settings-tab-'.length));
      });
      // The native horizontal-tabs plugin measures the gateway while initially hidden.
      root.jQuery('a[data-toggle="tab"][href="#' + container.id + '"]').on('shown.bs.tab.asaasSettings', function () {
        root.jQuery(root).trigger('resize');
      }).on('hide.bs.tab.asaasSettings', concealSecrets);
    }
    var invalidRevealPending = false;
    container.addEventListener('invalid', function (event) {
      var invalidPanel = event.target.closest('.tab-pane');
      var id = Object.keys(panels).find(function (key) { return panels[key] === invalidPanel; });
      if (!id || invalidRevealPending) return;
      // Native validation reports every invalid control, then focuses the first one.
      invalidRevealPending = true;
      root.setTimeout(function () { invalidRevealPending = false; }, 0);
      var parentTab = doc.querySelector('a[data-toggle="tab"][href="#' + container.id + '"]');
      if (parentTab && root.jQuery && root.jQuery.fn.tab) root.jQuery(parentTab).tab('show');
      // Run during capture, before native validation tries to focus the hidden field.
      select(id);
    }, true);
    container.addEventListener('click', function (event) {
      var reveal = event.target.closest('[data-asaas-reveal]');
      if (reveal && container.contains(reveal)) {
        var secret = doc.getElementById(reveal.getAttribute('data-asaas-reveal'));
        if (!secret || !container.contains(secret)) return;
        var showing = secret.type === 'password'; secret.type = showing ? 'text' : 'password';
        reveal.textContent = showing ? 'Ocultar' : 'Revelar'; reveal.setAttribute('aria-pressed', showing ? 'true' : 'false');
      }
      var copy = event.target.closest('[data-asaas-copy]');
      if (!copy || !container.contains(copy) || copy.disabled) return;
      var input = doc.getElementById(copy.getAttribute('data-asaas-copy'));
      var status = copy.closest('.form-group').querySelector('[data-asaas-copy-status]');
      if (!input || !container.contains(input) || !status) return;
      function fallback() { input.focus(); input.select(); status.textContent = input.type === 'password' ? 'Use Revelar para selecionar e copiar a chave.' : 'Selecione e copie o endereço.'; }
      if (root.navigator && root.navigator.clipboard && root.isSecureContext) {
        root.navigator.clipboard.writeText(input.value).then(function () { status.textContent = 'Valor copiado.'; }, fallback);
      } else fallback();
    });
    doc.addEventListener('visibilitychange', function () { if (doc.hidden) concealSecrets(); });
    if (root.addEventListener) root.addEventListener('pagehide', concealSecrets);
    groups.forEach(function (group) {
      var field = group.querySelector('[name^="settings[paymentmethod_asaas_"]');
      if (!field) return;
      var match = field.name.match(/^settings\[paymentmethod_asaas_(.+)\]$/); if (!match) return;
      var setting = settingParts(match[1]), section = sectionFor(match[1]), target = panels[section];
      if (setting.environment) {
        var profileId = section + '-' + setting.environment;
        if (!profilePanels[profileId]) {
          var fieldset = doc.createElement('fieldset'), legend = doc.createElement('legend');
          fieldset.className = 'well'; fieldset.setAttribute('data-asaas-profile', setting.environment);
          legend.textContent = setting.environment === 'production' ? 'Produção' : 'Homologação'; fieldset.appendChild(legend);
          profilePanels[profileId] = fieldset; target.appendChild(fieldset);
        }
        target = profilePanels[profileId];
      }
      target.appendChild(group);
      if (profileNotes[match[1]]) group.appendChild(profileNotes[match[1]]);
      if (!setting.environment && setting.key === 'sandbox') {
        var selectElement = doc.createElement('select'); selectElement.name = field.name; selectElement.id = 'asaas-operation-environment'; selectElement.className = 'form-control'; selectElement.required = true;
        [['', 'Selecione o ambiente; configuração inválida exige revisão'], ['1', 'Homologação'], ['0', 'Produção']].forEach(function (option) { var item = doc.createElement('option'); item.value = option[0]; item.textContent = option[1]; selectElement.appendChild(item); });
        selectElement.value = trustedEnvironment;
        var label = doc.createElement('label'); label.htmlFor = selectElement.id; label.textContent = 'Ambiente de novas operações';
        while (group.firstChild) group.removeChild(group.firstChild);
        group.appendChild(label); group.appendChild(selectElement);
        var help = doc.createElement('p'); help.className = 'help-block'; help.textContent = 'Seleção única. Cobranças emitidas preservam sua conta e ambiente de origem. Diagnóstico não seleciona ambiente.'; group.appendChild(help);
      }
      if (setting.key === 'installmentCount') {
        field.readOnly = true;
        if (!profileNotes[match[1]]) { var note = doc.createElement('p'); note.className = 'help-block'; note.textContent = 'Parcelamento indisponível: falta conciliação por parcela. O limite legado é preservado, mas o checkout permite apenas cartão à vista.'; group.appendChild(note); }
      }
      if (['sync_enabled', 'sync_days', 'sync_limit'].indexOf(setting.key) !== -1) {
        Array.prototype.forEach.call(group.querySelectorAll('input,select,textarea'), function (control) { control.disabled = true; });
        if (!profileNotes[match[1]]) { var unavailable = doc.createElement('p'); unavailable.className = 'help-block'; unavailable.textContent = 'Valor preservado. Este controle legado não altera a rotina atual de reconciliação.'; group.appendChild(unavailable); }
      }
      if ((setting.key === 'api_key' || setting.key === 'api_key_sandbox') && environmentMetadata && environmentMetadata.getAttribute('data-asaas-settings-admin') === '1') {
        field.type = 'password'; field.autocomplete = 'off'; field.setAttribute('data-asaas-secret-input', '');
        if (!field.id) field.id = 'asaas-settings-' + setting.key;
        var reveal = doc.createElement('button'); reveal.type = 'button'; reveal.className = 'btn btn-default mtop5'; reveal.textContent = 'Revelar'; reveal.setAttribute('data-asaas-reveal', field.id); reveal.setAttribute('aria-controls', field.id); reveal.setAttribute('aria-pressed', 'false');
        var copy = doc.createElement('button'); copy.type = 'button'; copy.className = 'btn btn-default mtop5'; copy.textContent = 'Copiar chave'; copy.setAttribute('data-asaas-copy', field.id);
        var status = doc.createElement('span'); status.className = 'help-block'; status.setAttribute('role', 'status'); status.setAttribute('data-asaas-copy-status', '');
        group.appendChild(reveal); group.appendChild(doc.createTextNode(' ')); group.appendChild(copy); group.appendChild(status);
      }
    });
    Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-settings-section]'), function (fragment) {
      var section = fragment.getAttribute('data-asaas-settings-section');
      if (panels[section]) panels[section].appendChild(fragment);
    });
    function note(id, message) { var paragraph = doc.createElement('p'); paragraph.textContent = message; panels[id].insertBefore(paragraph, panels[id].firstChild); }
    note('security', 'Tokens de webhook são gerados no CRM e administrados por conta e ambiente. Administradores podem revelar e copiar os tokens recuperáveis no gerenciador protegido. Tokens legados sem cópia cifrada exigem regeneração explícita. A chave da API não deve ser usada como token.');
    note('webhook', 'Recebimento, revisão de eventos e status do cadastro remoto são administrados por conta e ambiente. Cadastro local não comprova cadastro remoto.');
    note('diagnostics', 'Diagnóstico e gravação em arquivo têm parâmetros separados para Produção e Homologação. Não use logs para armazenar tokens, chaves ou dados de cartão.');
    note('payments', 'Cartão de crédito usa a página segura do Asaas à vista. Parcelamento e assinatura com cartão salvo permanecem indisponíveis. Cobrança já emitida exige revisão antes de trocar a forma de pagamento.');
    if (environmentMetadata && environmentMetadata.getAttribute('data-asaas-profile-ready') === '0') {
      note('authentication', 'Atualize o módulo Asaas para concluir a migração dos perfis. As configurações permanecem bloqueadas até a atualização nativa ser confirmada.');
      Array.prototype.forEach.call(container.querySelectorAll('[name^="settings[paymentmethod_asaas_"]'), function (field) { field.disabled = true; });
    }
    var configuredLink = container.querySelector('[data-asaas-webhook-admin-url]');
    if (configuredLink) ['security', 'webhook'].forEach(function (id) { var link = doc.createElement('a'); link.href = configuredLink.getAttribute('href'); link.textContent = 'Gerenciar webhooks e tokens'; link.className = 'btn btn-default mbot15'; panels[id].appendChild(link); });
    select('authentication');
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = {sectionFor: sectionFor, settingParts: settingParts, environmentValue: environmentValue, initialize: initialize};
  if (root.document) { if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', function () { initialize(root.document); }); else initialize(root.document); }
})(typeof window !== 'undefined' ? window : globalThis);
