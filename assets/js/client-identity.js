(function () {
  'use strict';
  document.querySelectorAll('[data-client-identity]').forEach(function (root) {
    if (root.dataset.ready) return; root.dataset.ready = '1';
    var form = root.closest('form'), status = root.querySelector('[data-client-status]'), preview = root.querySelector('[data-client-preview]');
    var list = root.querySelector('[data-client-preview-fields]'), busy = false, rows = [], query, originalQuery;
    function target(key) {
      var found = Array.from(root.querySelectorAll('[data-client-field]')).find(function (el) { return el.dataset.clientField === key; });
      if (!found && root.dataset.clientMode === 'legacy' && ['company', 'vat', 'address', 'city', 'state', 'zip', 'phonenumber'].includes(key)) found = form.querySelector('#' + key);
      return found;
    }
    function person() {
      var chosen = target('person_type').value, vat = target('vat'), value = vat ? vat.value.replace(/[.\/\-\s]/g, '') : '';
      return chosen || (value.length === 11 ? 'pf' : value.length === 14 ? 'pj' : '');
    }
    function updatePerson() {
      var type = person();
      root.querySelectorAll('[data-person-field]').forEach(function (group) {
        var hide = type === 'pj' && group.dataset.personField === 'rg' || type === 'pf' && group.dataset.personField !== 'rg';
        group.hidden = hide; group.querySelector('input').disabled = hide;
      });
      root.querySelector('[data-client-lookup=cnpj]').disabled = busy || type === 'pf';
    }
    target('person_type').addEventListener('change', updatePerson);
    if (target('vat')) target('vat').addEventListener('input', updatePerson);
    updatePerson();
    function discard() { preview.hidden = true; list.replaceChildren(); rows = []; }
    root.querySelector('[data-client-cancel]').addEventListener('click', function () { discard(); status.textContent = 'Consulta descartada. Nenhum campo foi alterado.'; });
    root.querySelectorAll('[data-client-lookup]').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (busy) return; discard();
        query = target(button.dataset.clientLookup === 'cnpj' ? 'vat' : 'zip');
        if (!query) { status.textContent = 'Campo de documento ou CEP indisponível neste formulário. Preencha manualmente.'; return; }
        originalQuery = query.value; var before = {}, typeBefore = person();
        ['company', 'trade_name', 'address', 'address_number', 'address_complement', 'address_district', 'city', 'state', 'zip', 'country_code', 'phonenumber'].forEach(function (key) { var el = target(key); if (el) before[key] = el.value; });
        var data = new URLSearchParams();
        form.querySelectorAll('input[type=hidden]').forEach(function (input) { if (input.name && !input.name.includes('[')) data.set(input.name, input.value); });
        data.set('_company_context', root.dataset.companyContext); data.set('lookup_csrf', root.dataset.lookupToken); data.set('client_id', root.dataset.clientId); data.set('kind', button.dataset.clientLookup); data.set('value', originalQuery);
        busy = true; button.disabled = true; status.textContent = 'Consultando. Seus dados serão preservados.';
        var abort = new AbortController(), timer = setTimeout(function () { abort.abort(); }, 12000);
        try {
          var response = await fetch(root.dataset.lookupUrl, { method: 'POST', body: data, credentials: 'same-origin', signal: abort.signal, headers: { Accept: 'application/json' } });
          var result = await response.json();
          if (result.csrf) document.querySelectorAll('input[type=hidden]').forEach(function (input) { if (input.name === result.csrf.name) input.value = result.csrf.hash; });
          if (!response.ok) throw new Error(result.error || 'Consulta indisponível.');
          if (query.value !== originalQuery || person() !== typeBefore) throw new Error('O documento, CEP ou tipo de pessoa mudou. Consulte novamente.');
          Object.keys(result.fields || {}).forEach(function (key) {
            var field = target(key); if (!field || field.disabled || typeof result.fields[key] !== 'string') return;
            var row = document.createElement('div'), label = document.createElement('label'), check = document.createElement('input'), value = document.createElement('input');
            var title = field.labels && field.labels[0] ? field.labels[0].textContent : key;
            check.type = 'checkbox'; check.checked = !field.value.trim(); label.append(check, document.createTextNode(title));
            value.className = 'form-control'; value.value = result.fields[key]; value.maxLength = field.maxLength > 0 ? field.maxLength : 2000; value.setAttribute('aria-label', 'Valor consultado: ' + title);
            row.append(label, value); list.append(row); rows.push({ field: field, check: check, value: value, before: before[key], type: typeBefore });
          });
          preview.hidden = !rows.length; status.textContent = rows.length ? 'Resultado de ' + result.provider + '. Revise antes de aplicar.' : 'Nenhum campo disponível. Continue manualmente.';
        } catch (error) { status.textContent = error instanceof SyntaxError || error instanceof TypeError ? 'Consulta indisponível. Recarregue se a sessão expirou.' : error.name === 'AbortError' ? 'Consulta demorou demais. Seus dados foram preservados.' : error.message; }
        finally { clearTimeout(timer); busy = false; button.disabled = false; updatePerson(); }
      });
    });
    root.querySelector('[data-client-apply]').addEventListener('click', function () {
      if (query.value !== originalQuery || rows.some(function (row) { return row.type !== person() || row.check.checked && (row.field.value !== row.before || row.field.disabled); })) { status.textContent = 'O cadastro mudou depois da consulta. Descarte e consulte novamente para preservar suas alterações.'; return; }
      rows.forEach(function (row) { if (row.check.checked) { row.field.value = row.value.value; row.field.dispatchEvent(new Event('change', { bubbles: true })); } });
      discard(); status.textContent = 'Campos aplicados ao formulário. Revise e salve para confirmar.';
    });
  });
}());
