(function (root) {
  'use strict';
  var sections = [
    ['general', 'Geral', ['currencies', 'description', 'interest_value', 'fine_value', 'discount_type', 'discount_value', 'discount_days', 'installmentCount', 'diasdevencimento', 'billet_only', 'card_only', 'pix_only']],
    ['authentication', 'Autenticação', ['active', 'api_key', 'api_key_sandbox', 'sandbox', 'label', 'default_selected', 'debug', 'file_log_enabled', 'sync_enabled', 'sync_days', 'sync_limit']],
    ['operations', 'Webhook e operações', []]
  ];
  function settingParts(key) {
    var profile = key.match(/^profile_(production|sandbox)_(.+)$/);
    return profile ? {environment: profile[1], key: profile[2]} : {environment: null, key: key};
  }
  function sectionFor(key) {
    key = settingParts(key).key;
    for (var i = 0; i < sections.length; i++) if (sections[i][2].indexOf(key) !== -1) return sections[i][0];
    return 'authentication';
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
    // This wrapper is dynamic: the native global ready callback may already have
    // scanned the page. Keep it outside that scan until our own queued ready
    // callback installs exactly one native scroll controller.
    var wrapper = doc.createElement('div'); wrapper.className = 'panel-full-width-tabs mtop15 mbot15';
    ['left', 'right'].forEach(function (direction) {
      var arrow = doc.createElement('div'), icon = doc.createElement('i');
      arrow.className = 'scroller arrow-' + direction;
      icon.className = 'fa fa-angle-' + direction; arrow.appendChild(icon); wrapper.appendChild(arrow);
    });
    var horizontal = doc.createElement('div'); horizontal.className = 'horizontal-tabs'; wrapper.appendChild(horizontal);
    var nav = doc.createElement('ul'); nav.className = 'nav nav-tabs nav-tabs-horizontal'; nav.setAttribute('role', 'tablist'); nav.setAttribute('aria-label', 'Configurações Asaas'); horizontal.appendChild(nav);
    var content = doc.createElement('div'); content.className = 'tab-content';
    var panels = {}, tabs = {}, profilePanels = {}, profileNavigation = {};
    function concealSecrets() {
      if (root.AsaasOperationsPanel) root.AsaasOperationsPanel.conceal(panels.operations);
      Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-secret-input]'), function (input) { input.type = 'password'; });
      Array.prototype.forEach.call(container.querySelectorAll('[data-asaas-reveal]'), function (button) { button.textContent = 'Revelar'; button.setAttribute('aria-pressed', 'false'); });
    }
    // Browsing a profile is presentation only: never write the operation selector.
    function selectProfile(section, environment) {
      concealSecrets();
      var navigation = profileNavigation[section];
      if (!navigation) return;
      Object.keys(navigation.tabs).forEach(function (key) {
        var selected = key === environment, tab = navigation.tabs[key];
        profilePanels[section + '-' + key].hidden = !selected;
        profilePanels[section + '-' + key].classList.toggle('active', selected);
        tab.parentNode.classList.toggle('active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.setAttribute('aria-expanded', selected ? 'true' : 'false');
        tab.setAttribute('tabindex', selected ? '0' : '-1');
      });
    }
    function profilePanel(section, environment) {
      if (section !== 'authentication') {
        var profileId = section + '-' + environment;
        if (!profilePanels[profileId]) {
          var fieldset = doc.createElement('fieldset'), legend = doc.createElement('legend');
          fieldset.className = 'well'; fieldset.setAttribute('data-asaas-profile', environment);
          legend.textContent = environment === 'production' ? 'Produção' : 'Homologação'; fieldset.appendChild(legend);
          profilePanels[profileId] = fieldset; panels[section].appendChild(fieldset);
        }
        return profilePanels[profileId];
      }
      if (!profileNavigation[section]) {
        var nav = doc.createElement('ul'), content = doc.createElement('div');
        nav.className = 'nav nav-tabs'; nav.setAttribute('role', 'tablist');
        nav.setAttribute('aria-label', 'Parâmetros por ambiente');
        nav.setAttribute('data-asaas-environment-tabs', section);
        content.className = 'tab-content';
        profileNavigation[section] = {tabs: {}};
        panels[section].appendChild(nav); panels[section].appendChild(content);
        ['production', 'sandbox'].forEach(function (key, index) {
          var item = doc.createElement('li'), tab = doc.createElement('a'), panel = doc.createElement('div');
          var id = 'asaas-profile-' + section + '-' + key;
          item.setAttribute('role', 'presentation');
          tab.id = id + '-tab'; tab.href = '#' + id; tab.textContent = key === 'production' ? 'Produção' : 'Homologação';
          tab.setAttribute('role', 'tab'); tab.setAttribute('data-toggle', 'tab');
          tab.setAttribute('data-settings-parent-tab', container.id); tab.setAttribute('aria-controls', id);
          panel.id = id; panel.className = 'tab-pane'; panel.setAttribute('role', 'tabpanel');
          panel.setAttribute('aria-labelledby', tab.id); panel.setAttribute('data-asaas-profile', key);
          profilePanels[section + '-' + key] = panel; profileNavigation[section].tabs[key] = tab;
          item.appendChild(tab); nav.appendChild(item); content.appendChild(panel);
          tab.addEventListener('click', function (event) { event.preventDefault(); selectProfile(section, key); });
          tab.addEventListener('keydown', function (event) {
            var next = event.key === 'Home' ? 0 : event.key === 'End' ? 1 : ['ArrowRight', 'ArrowLeft'].indexOf(event.key) >= 0 ? 1 - index : -1;
            if (next >= 0) { event.preventDefault(); var target = ['production', 'sandbox'][next]; selectProfile(section, target); profileNavigation[section].tabs[target].focus(); }
            else if (event.key === ' ' || event.key === 'Enter') { event.preventDefault(); selectProfile(section, key); }
          });
        });
        if (root.jQuery) root.jQuery(nav).on('shown.bs.tab', 'a[data-toggle="tab"]', function () {
          selectProfile(section, this === profileNavigation[section].tabs.production ? 'production' : 'sandbox');
        });
        selectProfile(section, trustedEnvironment === '1' ? 'sandbox' : 'production');
      }
      return profilePanels[section + '-' + environment];
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
    function select(id, address) {
      concealSecrets();
      if (root.jQuery && root.jQuery.fn.tab) root.jQuery(tabs[id]).tab('show');
      synchronize(id);
      if (id === 'operations' && root.AsaasOperationsPanel) root.AsaasOperationsPanel.open(panels.operations, address);
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
    if (root.jQuery && root.jQuery.fn.horizontalTabs) {
      root.jQuery(function () {
        if (wrapper.getAttribute('data-asaas-scroll-ready') === '1') return;
        wrapper.classList.add('horizontal-scrollable-tabs');
        // Native horizontalTabs measures LI outerWidth, not UL padding. Preserve
        // the native edge spacing on the edge items so padding-only overflow is
        // included in that measurement (without replacing the native controller).
        var navStyle = root.getComputedStyle(nav);
        nav.firstElementChild.style.paddingLeft = navStyle.paddingLeft;
        nav.lastElementChild.style.paddingRight = navStyle.paddingRight;
        nav.style.paddingLeft = '0px';
        nav.style.paddingRight = '0px';
        root.jQuery(wrapper).horizontalTabs();
        wrapper.setAttribute('data-asaas-scroll-ready', '1');
        root.jQuery(root).trigger('resize');
        if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(function () { root.jQuery(root).trigger('resize'); });
      });
    } else { wrapper.classList.add('horizontal-scrollable-tabs'); }

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
      var id = Object.keys(panels).find(function (key) { return panels[key].contains(event.target); });
      if (!id || invalidRevealPending) return;
      // Native validation reports every invalid control, then focuses the first one.
      invalidRevealPending = true;
      root.setTimeout(function () { invalidRevealPending = false; }, 0);
      var parentTab = doc.querySelector('a[data-toggle="tab"][href="#' + container.id + '"]');
      if (parentTab && root.jQuery && root.jQuery.fn.tab) root.jQuery(parentTab).tab('show');
      // Run during capture, before native validation tries to focus the hidden field.
      select(id);
      var invalidProfile = event.target.closest('[data-asaas-profile]');
      if (invalidProfile) selectProfile(id, invalidProfile.getAttribute('data-asaas-profile'));
    }, true);
    container.addEventListener('asaas-settings-return', function () { select('general'); });
    container.addEventListener('click', function (event) {
      var manage = event.target.closest('[data-asaas-open-operations]');
      if (manage) { event.preventDefault(); select('operations', manage.getAttribute('href')); return; }
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
      var fieldEnvironment = setting.environment || (setting.key === 'api_key' ? 'production' : setting.key === 'api_key_sandbox' ? 'sandbox' : null);
      if (fieldEnvironment) target = profilePanel(section, fieldEnvironment);
      target.appendChild(group);
      if (profileNotes[match[1]]) group.appendChild(profileNotes[match[1]]);
      if (!setting.environment && setting.key === 'sandbox') {
        var selectElement = doc.createElement('select'); selectElement.name = field.name; selectElement.id = 'asaas-operation-environment'; selectElement.className = 'form-control'; selectElement.required = true;
        [['', 'Selecione o ambiente; configuração inválida exige revisão'], ['1', 'Homologação'], ['0', 'Produção']].forEach(function (option) { var item = doc.createElement('option'); item.value = option[0]; item.textContent = option[1]; selectElement.appendChild(item); });
        selectElement.value = trustedEnvironment;
        var label = doc.createElement('label'); label.htmlFor = selectElement.id; label.textContent = 'Ambiente ativo';
        while (group.firstChild) group.removeChild(group.firstChild);
        group.appendChild(label); group.appendChild(selectElement);
        panels.authentication.insertBefore(group, panels.authentication.firstChild);
        var help = doc.createElement('p'); help.className = 'help-block'; help.textContent = 'Define o ambiente de novas operações após salvar. As subguias apenas exibem parâmetros; não alteram o ambiente ativo. Cobranças emitidas preservam sua conta e ambiente de origem.'; group.appendChild(help);
      }
      if (setting.environment && setting.key === 'active') {
        var activeLabel = group.querySelector('label');
        if (activeLabel) activeLabel.textContent = 'Habilitar pagamentos neste ambiente';
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
      if (section === 'webhook' || section === 'security') section = 'authentication';
      if (panels[section]) panels[section].appendChild(fragment);
      Array.prototype.forEach.call(fragment.querySelectorAll('[data-asaas-profile-fragment]'), function (item) {
        var environment = item.getAttribute('data-asaas-profile-fragment');
        if (environment === 'production' || environment === 'sandbox') profilePanel(section, environment).appendChild(item);
      });
    });
    function note(id, message) { var paragraph = doc.createElement('p'); paragraph.textContent = message; panels[id].insertBefore(paragraph, panels[id].firstChild); }
    note('general', 'Parâmetros de pagamento. Valores existentes de Produção e Homologação são preservados separadamente; editar um perfil não sobrescreve o outro. A integração usa BRL e cartão à vista.');
    note('authentication', 'Conexão, recursos e diagnóstico por ambiente. Abrir uma subguia não ativa o ambiente nem gera tokens. Tokens de webhook são separados da chave da API e exigem ação administrativa explícita.');
    if (environmentMetadata && environmentMetadata.getAttribute('data-asaas-profile-ready') === '0') {
      note('authentication', 'Atualize o módulo Asaas para concluir a migração dos perfis. As configurações permanecem bloqueadas até a atualização nativa ser confirmada.');
      Array.prototype.forEach.call(container.querySelectorAll('[name^="settings[paymentmethod_asaas_"]'), function (field) { field.disabled = true; });
    }
    var configuredLink = container.querySelector('[data-asaas-webhook-admin-url]');
    if (configuredLink && root.AsaasOperationsPanel) root.AsaasOperationsPanel.initialize(panels.operations, configuredLink.getAttribute('href'));
    select('general');
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = {sectionFor: sectionFor, settingParts: settingParts, environmentValue: environmentValue, initialize: initialize};
  if (root.document) { if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', function () { initialize(root.document); }); else initialize(root.document); }
})(typeof window !== 'undefined' ? window : globalThis);
