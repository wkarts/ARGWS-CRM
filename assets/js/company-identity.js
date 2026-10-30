/* Company lookup is a deliberate preview; it never submits or saves the form. */
(function () {
  'use strict';
  document.querySelectorAll('[data-company-identity]').forEach(function (editor) {
    if (editor.dataset.ready) return;
    editor.dataset.ready = '1';
    var form = editor.closest('form'), status = editor.querySelector('[data-lookup-status]');
    var preview = editor.querySelector('[data-lookup-preview]'), fields = editor.querySelector('[data-lookup-fields]');
    var pending = false, rows = [], queryField, queryValue;
    // Progressive enhancement: every field stays available if JavaScript is disabled.
    var panels = Array.from(editor.querySelectorAll('[data-identity-panel]'));
    var tabs = Array.from(editor.querySelectorAll('.company-identity-sections a'));
    var navigation = editor.querySelector('.company-identity-sections');
    navigation.setAttribute('role', 'tablist');
    panels.forEach(function (panel, index) {
      var tab = tabs[index]; tab.id = panel.id + '-tab';
      tab.setAttribute('role', 'tab'); tab.setAttribute('aria-controls', panel.id);
      tab.parentNode.setAttribute('role', 'presentation');
      panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id);
    });
    function showPanel(index, address) {
      panels.forEach(function (panel, i) { panel.hidden = i !== index; });
      tabs.forEach(function (tab, i) {
        tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
        tab.setAttribute('aria-current', i === index ? 'step' : 'false');
        tab.tabIndex = i === index ? 0 : -1;
        tab.parentNode.classList.toggle('active', i === index);
      });
      // Scroll this navigation only, never jump the surrounding modal/page.
      var item = tabs[index].getBoundingClientRect(), box = navigation.getBoundingClientRect();
      if (item.left < box.left) navigation.scrollLeft -= box.left - item.left;
      else if (item.right > box.right) navigation.scrollLeft += item.right - box.right;
      if (address && location.hash !== tabs[index].hash) history.pushState(null, '', tabs[index].hash);
    }
    function fromAddress() {
      var index = tabs.findIndex(function (tab) { return tab.hash === location.hash; });
      showPanel(index < 0 ? 0 : index, false);
    }
    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function (event) { event.preventDefault(); showPanel(index, true); });
      tab.addEventListener('keydown', function (event) {
        var next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
        if (next >= 0) { event.preventDefault(); showPanel(next, true); tabs[next].focus(); }
        else if (event.key === ' ') { event.preventDefault(); showPanel(index, true); }
      });
    });
    form.addEventListener('invalid', function (event) { var index = panels.findIndex(function (panel) { return panel.contains(event.target); }); if (index !== -1) showPanel(index, false); }, true);
    window.addEventListener('hashchange', fromAddress);
    window.addEventListener('popstate', fromAddress);
    fromAddress();

    function target(key) { return Array.from(editor.querySelectorAll('[data-company-field]')).find(function (el) { return el.dataset.companyField === key; }); }
    function discard() { preview.hidden = true; fields.replaceChildren(); rows = []; }
    editor.querySelectorAll('[data-company-lookup]').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (pending) return;
        discard();
        queryField = target(button.dataset.companyLookup === 'cnpj' ? 'tax_id' : 'invoice_company_postal_code');
        queryValue = queryField.value;
        var snapshots = {};
        editor.querySelectorAll('[data-company-field]').forEach(function (el) { snapshots[el.dataset.companyField] = el.value; });
        var data = new URLSearchParams();
        form.querySelectorAll('input[type=hidden]').forEach(function (input) { if (input.name) data.set(input.name, input.value); });
        data.set('_company_context', editor.dataset.companyContext);
        data.set('lookup_mode', editor.dataset.lookupMode);
        data.set('lookup_csrf', editor.dataset.lookupToken);
        data.set('issuer_id', editor.dataset.issuerId);
        data.set('kind', button.dataset.companyLookup);
        data.set('value', queryValue);
        pending = true; button.disabled = true; status.textContent = 'Consultando. Seus dados serão preservados.';
        var controller = new AbortController(), timer = setTimeout(function () { controller.abort(); }, 12000);
        try {
          var response = await fetch(editor.dataset.lookupUrl, { method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal, headers: { 'Accept': 'application/json' } });
          var result = await response.json();
          if (result.csrf && result.csrf.name && result.csrf.hash) document.querySelectorAll('input[type=hidden]').forEach(function (input) { if (input.name === result.csrf.name) input.value = result.csrf.hash; });
          if (!response.ok) throw new Error(result.error || 'Consulta indisponível. Preencha manualmente ou tente novamente.');
          if (queryField.value !== queryValue) throw new Error('O documento ou CEP mudou durante a consulta. Consulte novamente.');
          Object.keys(result.fields || {}).forEach(function (key) {
            var original = target(key); if (!original || original.disabled || typeof result.fields[key] !== 'string') return;
            var row = document.createElement('div'), label = document.createElement('label'), check = document.createElement('input'), input = document.createElement('input');
            check.type = 'checkbox'; check.checked = !original.value.trim();
            label.append(check, document.createTextNode(original.labels[0].textContent));
            input.type = 'text'; input.className = 'form-control'; input.maxLength = original.maxLength; input.value = result.fields[key];
            input.setAttribute('aria-label', 'Valor consultado: ' + original.labels[0].textContent);
            row.append(label, input); fields.append(row);
            rows.push({ original: original, check: check, input: input, before: snapshots[key] });
          });
          preview.hidden = rows.length === 0;
          status.textContent = rows.length ? 'Consulta recebida de ' + result.provider + '. Revise antes de aplicar.' : 'Nenhum campo disponível. Continue o preenchimento manual.';
        } catch (error) {
          status.textContent = error.name === 'AbortError' ? 'A consulta demorou demais. Seus dados foram preservados; tente depois.' : (error instanceof SyntaxError || error instanceof TypeError ? 'Consulta indisponível. Recarregue se a sessão expirou; seus campos não foram alterados.' : error.message);
        } finally { clearTimeout(timer); pending = false; button.disabled = false; }
      });
    });
    editor.querySelector('[data-lookup-cancel]').addEventListener('click', function () { discard(); status.textContent = 'Consulta descartada. Seus campos foram preservados.'; });
    editor.querySelector('[data-lookup-apply]').addEventListener('click', function () {
      if (queryField.value !== queryValue) { discard(); status.textContent = 'O documento ou CEP mudou. Consulte novamente.'; return; }
      var changed = rows.some(function (row) { return row.check.checked && row.original.value !== row.before; });
      if (changed) { status.textContent = 'Você alterou campos depois da consulta. Descarte e consulte novamente para preservar suas alterações.'; return; }
      var count = 0;
      rows.forEach(function (row) { if (row.check.checked) { row.original.value = row.input.value; row.original.dispatchEvent(new Event('change', { bubbles: true })); count++; } });
      discard(); status.textContent = count + ' campo(s) aplicado(s) ao formulário. Revise e salve para confirmar.';
    });
  });
}());
