/* Original credit-note split view and supported accounting editor; financial writes stay server-owned. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (!root) return;
  root.NativeCreditEditor = api;
  root.init_credit_note = function (id) {
    return root.load_small_table_item(id, '#credit_note', 'credit_note_id', 'credit_notes/get_credit_note_data_ajax', '.table-credit-notes');
  };
})(typeof window === 'undefined' ? null : window, function () {
  'use strict';
  var MAX = 999999999999999n;
  function minor(value, signed) {
    if (typeof value !== 'string' || !(signed ? /^-?(0|[1-9]\d{0,12})(\.\d{1,2})?$/ : /^(0|[1-9]\d{0,12})(\.\d{1,2})?$/).test(value)) throw new Error('Informe valores decimais com ponto e até duas casas.');
    var negative = value.charAt(0) === '-', parts = (negative ? value.slice(1) : value).split('.');
    var result = BigInt(parts[0]) * 100n + BigInt((parts[1] || '').padEnd(2, '0'));
    if (result > MAX) throw new Error('Valor acima do limite permitido.');
    return negative ? -result : result;
  }
  function decimal(value) {
    var negative = value < 0n, absolute = negative ? -value : value;
    return (negative ? '-' : '') + (absolute / 100n).toString() + '.' + (absolute % 100n).toString().padStart(2, '0');
  }
  function calculate(data) {
    if (!Array.isArray(data.items) || !data.items.length || data.items.length > 100) throw new Error('Informe entre um e cem itens.');
    var subtotal = 0n;
    var lines = data.items.map(function (item) {
      var qty = minor(item.qty), rate = minor(item.rate);
      if (qty < 1n || qty > 99999900n || rate < 1n || qty * rate > MAX * 100n) throw new Error('Confira quantidade e preço unitário.');
      var amount = (qty * rate + 50n) / 100n;
      subtotal += amount;
      if (amount < 1n || subtotal > MAX) throw new Error('Subtotal acima do limite permitido.');
      var seen = Object.create(null);
      var taxes = (item.taxes || []).map(function (tax) {
        var basis = minor(tax.rate), name = tax.name.trim();
        var identity = name.replace(/[A-Z]/g, function (letter) { return letter.toLowerCase(); });
        if (basis > 10000n || (basis > 0n && (!name || seen[identity]))) throw new Error('Confira nome e alíquota dos tributos.');
        if (basis > 0n) seen[identity] = true;
        return basis;
      });
      if (taxes.length > 10) throw new Error('Informe até dez tributos por item.');
      return { amount: amount, taxes: taxes };
    });
    var value = minor(data.discountValue), percent = data.discountMode === 'percent';
    if (!['fixed', 'percent'].includes(data.discountMode) || !['before_tax', 'after_tax'].includes(data.discountType) || percent && value > 10000n) throw new Error('Confira o desconto.');
    var discount = percent ? (subtotal * value + 5000n) / 10000n : value;
    if (discount > subtotal) throw new Error('O desconto excede o subtotal.');
    var allocated = lines.map(function () { return 0n; });
    if (data.discountType === 'before_tax') {
      var rank = lines.map(function (line, index) { allocated[index] = line.amount * discount / subtotal; return { index: index, remainder: line.amount * discount % subtotal }; });
      rank.sort(function (a, b) { return a.remainder === b.remainder ? a.index - b.index : a.remainder > b.remainder ? -1 : 1; });
      var missing = discount - allocated.reduce(function (sum, n) { return sum + n; }, 0n);
      for (var n = 0; missing > 0n; n++, missing--) allocated[rank[n].index]++;
    }
    var tax = 0n;
    lines.forEach(function (line, index) { line.taxes.forEach(function (basis) { tax += ((line.amount - allocated[index]) * basis + 5000n) / 10000n; if (tax > MAX) throw new Error('Tributos acima do limite permitido.'); }); });
    var adjustment = minor(data.adjustment, true), total = subtotal - discount + tax + adjustment;
    if (total < 1n || total > MAX) throw new Error('Confira o total da nota de crédito.');
    return { subtotal: decimal(subtotal), discount: decimal(discount), tax: decimal(tax), adjustment: decimal(adjustment), total: decimal(total), lines: lines.map(function (line) { return decimal(line.amount); }) };
  }
  /** One current read per editor purpose; discarded reads never repaint or keep submission blocked. */
  function readCoordinator(transport, current) {
    var slots = new Map();
    function cancel(slot) {
      var previous = slots.get(slot); slots.delete(slot);
      if (previous && previous.xhr && typeof previous.xhr.abort === 'function') previous.xhr.abort();
    }
    return {
      cancel: cancel,
      busy: function () { return slots.size > 0; },
      read: function (slot, path, data) {
        if (!transport || !current()) return Promise.reject(new Error('Reabra a página para confirmar a empresa ativa.'));
        cancel(slot); var request = {}; slots.set(slot, request);
        try { request.xhr = transport.request(path, 'GET', data, { dataType: 'json' }); }
        catch (invalid) { slots.delete(slot); return Promise.reject(invalid); }
        return Promise.resolve(request.xhr).then(function (response) { return current() && slots.get(slot) === request ? response : undefined; }, function (invalid) {
          if (!current() || slots.get(slot) !== request) return undefined;
          throw invalid;
        }).finally(function () { if (slots.get(slot) === request) slots.delete(slot); });
      }
    };
  }
  function mount(form) {
    if (!form || form.dataset.creditMounted) return;
    form.dataset.creditMounted = '1';
    var view = form.ownerDocument.defaultView, transport = view && view.NativeCompanyTransport;
    var host = form.querySelector('#credit-items'), add = form.querySelector('#credit-add-item'), error = form.querySelector('#credit-form-error'), next = 1;
    if (!host || !add) return;
    form.ownerDocument.body.classList.add('no-calculate-total');
    var template = host.firstElementChild.cloneNode(true), clientSerial = 0, catalogSerial = 0, searchSerial = 0, dragRow = null;
    function current() { return !view || !view.NativeCompanyContextGuard || view.NativeCompanyContextGuard.isCurrent(); }
    var reads = readCoordinator(transport, current);
    function fail(message) { error.textContent = message; error.classList.remove('hide'); }
    function input(node, ending) { return node.querySelector('[name$="[' + ending + ']"]'); }
    function values() {
      return { items: Array.from(host.children).map(function (row) { return { qty: input(row, 'qty').value, rate: row.querySelector('[name="credit[items][' + row.dataset.index + '][rate]"]').value, taxes: Array.from(row.querySelectorAll('.credit-tax')).map(function (tax) { return { name: input(tax, 'name').value, rate: input(tax, 'rate').value }; }) }; }), discountMode: form.querySelector('#credit-discount-mode').value, discountValue: form.querySelector('#credit-discount').value, discountType: form.querySelector('#credit-discount-type').value, adjustment: form.querySelector('#credit-adjustment').value };
    }
    function update(showError) {
      try {
        var totals = calculate(values());
        form.querySelectorAll('[data-credit-total]').forEach(function (node) { node.textContent = totals[node.dataset.creditTotal]; });
        Array.from(host.children).forEach(function (row, index) { row.querySelector('[data-credit-line-amount]').textContent = totals.lines[index]; });
        if (showError) error.classList.add('hide'); return true;
      } catch (invalid) {
        form.querySelectorAll('[data-credit-total],[data-credit-line-amount]').forEach(function (node) { node.textContent = '—'; });
        if (showError) fail(invalid.message); return false;
      }
    }
    function buttons() { host.querySelectorAll('[data-credit-remove]').forEach(function (button) { button.disabled = host.children.length === 1; }); add.disabled = host.children.length >= 100; }
    function newRow(append) {
      if (host.children.length >= 100) throw new Error('Informe até cem itens.');
      var row = template.cloneNode(true), index = next++, ids = Object.create(null);
      row.dataset.index = String(index);
      row.querySelectorAll('[id]').forEach(function (node) { ids[node.id] = node.id + '-line-' + index; node.id = ids[node.id]; });
      row.querySelectorAll('label[for]').forEach(function (node) { if (ids[node.htmlFor]) node.htmlFor = ids[node.htmlFor]; });
      row.querySelectorAll('[name]').forEach(function (node) {
        node.name = node.name.replace(/credit\[items\]\[\d+\]/, 'credit[items][' + index + ']')
          .replace(/credit\[native_metadata\]\[items\]\[\d+\]/, 'credit[native_metadata][items][' + index + ']')
          .replace(/credit\[native_metadata\]\[item_fields\]\[values\]\[\d+\]/, 'credit[native_metadata][item_fields][values][' + index + ']');
      });
      row.querySelector('.credit-taxes').replaceChildren(); if (append !== false) host.appendChild(row);
      if (view && typeof view.init_datepicker === 'function') view.init_datepicker(row.querySelectorAll('.datepicker'), row.querySelectorAll('.datetimepicker'));
      buttons(); return row;
    }
    function addTax(row, value) {
      var taxes = row.querySelector('.credit-taxes'); if (taxes.children.length >= 10) throw new Error('Informe até dez tributos por item.');
      var index = 0; taxes.querySelectorAll('[data-tax-index]').forEach(function (node) { index = Math.max(index, Number(node.dataset.taxIndex) + 1); });
      var tax = template.querySelector('.credit-tax').cloneNode(true); tax.dataset.taxIndex = String(index);
      tax.querySelectorAll('[name]').forEach(function (node) { node.name = node.name.replace(/credit\[items\]\[\d+\]\[taxes\]\[\d+\]/, 'credit[items][' + row.dataset.index + '][taxes][' + index + ']'); node.value = node.name.endsWith('[rate]') ? value.rate : value.name; });
      tax.querySelector('[data-credit-tax-remove]').setAttribute('data-credit-tax-remove', String(index)); taxes.appendChild(tax); row.querySelector('[data-credit-tax]').disabled = taxes.children.length >= 10;
    }
    add.addEventListener('click', function () { try { newRow(); update(false); } catch (invalid) { fail(invalid.message); } });
    host.addEventListener('click', function (event) {
      var button = event.target.closest('button'); if (!button || !host.contains(button)) return;
      var row = button.closest('.credit-item');
      if (button.hasAttribute('data-credit-remove')) { if (host.children.length > 1) row.remove(); buttons(); update(false); return; }
      if (button.hasAttribute('data-credit-tax-remove')) { button.closest('.credit-tax').remove(); row.querySelector('[data-credit-tax]').disabled = false; update(false); return; }
      if (button.hasAttribute('data-credit-tax')) { try { addTax(row, {name: '', rate: '0.00'}); update(false); } catch (invalid) { fail(invalid.message); } }
    });
    host.addEventListener('dragstart', function (event) { if (!event.target.closest('[data-credit-drag]')) return; dragRow = event.target.closest('.credit-item'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', dragRow.dataset.index); });
    host.addEventListener('dragover', function (event) { if (dragRow && event.target.closest('.credit-item')) event.preventDefault(); });
    host.addEventListener('drop', function (event) { var target = event.target.closest('.credit-item'); if (!dragRow || !target || target === dragRow) return; event.preventDefault(); host.insertBefore(dragRow, target); dragRow = null; update(false); });
    host.addEventListener('dragend', function () { dragRow = null; });
    host.addEventListener('keydown', function (event) { if (!event.target.hasAttribute('data-credit-drag') || !['ArrowUp','ArrowDown'].includes(event.key)) return; var row = event.target.closest('.credit-item'), target = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling; if (!target) return; event.preventDefault(); host.insertBefore(row, event.key === 'ArrowUp' ? target : target.nextElementSibling); event.target.focus(); update(false); });
    function addressLabels() {
      form.querySelectorAll('[data-credit-address]').forEach(function (node) {
        var label = form.querySelector('[data-credit-address-label="' + node.dataset.creditAddress + '"]');
        if (label) label.textContent = node.tagName === 'SELECT' ? (node.value === '0' ? '--' : node.options[node.selectedIndex].text) : node.value || '--';
      });
    }
    function request(path, data, slot) { return reads.read(slot, path, data); }
    function requestError(invalid) { if (current()) fail(invalid instanceof Error ? invalid.message : (invalid && invalid.responseJSON && typeof invalid.responseJSON.message === 'string' ? invalid.responseJSON.message : 'Não foi possível confirmar os dados. Confira a empresa ativa e tente novamente.')); }
    var client = form.querySelector('#credit-client'), project = form.querySelector('#credit-project'), projectRevision = form.querySelector('#credit-project-revision'), currency = form.querySelector('#credit-currency'), baseCurrency = currency && currency.value;
    function loadClient(shippingOnly) {
      var serial = ++clientSerial, id = client.value;
      reads.cancel('client');
      if (!shippingOnly) {
        catalogSerial++; reads.cancel('catalog'); if (currency) currency.value = baseCurrency;
        form.querySelectorAll('[data-credit-address]').forEach(function (node) { node.value = node.tagName === 'SELECT' ? '0' : ''; });
        if (project) { project.replaceChildren(new view.Option('', '')); form.querySelector('#credit-project-wrapper').classList.add('hide'); }
        if (projectRevision) projectRevision.value = '';
        addressLabels();
      }
      if (!id) return Promise.resolve();
      return request('credit_notes/client_change_data/' + encodeURIComponent(id), {}, 'client').then(function (response) {
        if (response === undefined || !current() || serial !== clientSerial || client.value !== id) return;
        if (String(response.client_id) !== id || !Array.isArray(response.billing_shipping) || response.billing_shipping.length !== 1 || !Array.isArray(response.projects)) throw new Error('Os dados do cliente foram alterados. Reabra o formulário.');
        var address = response.billing_shipping[0];
        form.querySelectorAll('[data-credit-address]').forEach(function (node) { if (!shippingOnly || node.dataset.creditAddress.startsWith('shipping_')) node.value = String(address[node.dataset.creditAddress] == null ? (node.tagName === 'SELECT' ? 0 : '') : address[node.dataset.creditAddress]); });
        if (!shippingOnly) {
          if (response.client_currency && Array.from(currency.options).some(function (option) { return option.value === String(response.client_currency); })) currency.value = String(response.client_currency);
          if (project) { response.projects.forEach(function (row) { var option = new view.Option(row.name, row.id); option.dataset.revision = String(row.revision); project.appendChild(option); }); form.querySelector('#credit-project-wrapper').classList.toggle('hide', response.projects.length === 0); }
        }
        addressLabels();
      }).catch(function (invalid) { if (serial === clientSerial) requestError(invalid); });
    }
    if (client) client.addEventListener('change', function () { loadClient(false); });
    if (project) project.addEventListener('change', function () { projectRevision.value = project.options[project.selectedIndex].dataset.revision || ''; });
    var shippingProfile = form.querySelector('#credit-profile-shipping'); if (shippingProfile) shippingProfile.addEventListener('click', function () { loadClient(true); });
    if (currency) currency.addEventListener('change', function () { catalogSerial++; reads.cancel('catalog'); });
    var picker = form.querySelector('#credit-catalog-item'), search = form.querySelector('#credit-catalog-search'), timer;
    if (search) search.addEventListener('input', function () {
      var serial = ++searchSerial, query = search.value; clearTimeout(timer);
      timer = setTimeout(function () { request('credit_notes/items', {q: query}, 'search').then(function (rows) { if (rows === undefined || !current() || serial !== searchSerial || query !== search.value) return; if (!Array.isArray(rows)) throw new Error('Catálogo indisponível.'); picker.replaceChildren(new view.Option('', '')); rows.forEach(function (row) { picker.appendChild(new view.Option(row.name, row.id)); }); }).catch(function (invalid) { if (serial === searchSerial) requestError(invalid); }); }, 180);
    });
    function setCustomField(group, value) {
      var type = group.dataset.creditFieldType;
      if (type === 'checkbox') group.querySelectorAll('input[type="checkbox"]').forEach(function (node) { node.checked = Array.isArray(value) && value.includes(node.value); });
      else if (type === 'multiselect') Array.from(group.querySelector('select').options).forEach(function (option) { option.selected = Array.isArray(value) && value.includes(option.value); });
      else if (type === 'link') {
        var title = '', url = '';
        if (typeof value === 'string' && value) {
          if (/^(?:https?:\/\/|mailto:)/i.test(value)) { title = value; url = value; }
          else { var doc = new view.DOMParser().parseFromString(value, 'text/html'), anchor = doc.body.firstElementChild; if (!anchor || anchor.tagName !== 'A' || doc.body.children.length !== 1 || anchor.children.length || !/^(?:https?:\/\/|mailto:)/i.test(anchor.getAttribute('href') || '')) throw new Error('Confira o campo de link do item.'); title = anchor.textContent; url = anchor.getAttribute('href'); }
        }
        group.querySelector('[data-credit-link-title]').value = title; group.querySelector('[data-credit-link-url]').value = url; group.querySelector('[data-credit-link-value]').value = value || '';
      } else group.querySelector('[name]').value = value == null ? '' : value;
    }
    if (picker) picker.addEventListener('change', function () {
      var id = picker.value, selectedCurrency = currency.value, serial = ++catalogSerial; if (!id) return;
      request('credit_notes/item/' + encodeURIComponent(id), {currency: selectedCurrency}, 'catalog').then(function (item) {
        if (item === undefined || !current() || serial !== catalogSerial || currency.value !== selectedCurrency) return;
        if (!item.custom_fields || item.custom_fields.definitions_hash !== form.querySelector('#credit-item-fields-hash').value) throw new Error('Os campos dos itens foram alterados. Reabra o formulário.');
        var first = host.firstElementChild, replace = host.children.length === 1 && input(first, 'description').value === '' && first.querySelector('[name="credit[items][' + first.dataset.index + '][rate]"]').value === '';
        var row = newRow(false);
        input(row, 'description').value = item.description; input(row, 'long_description').value = item.long_description; input(row, 'unit').value = item.unit;
        row.querySelector('[name="credit[items][' + row.dataset.index + '][rate]"]').value = item.rate;
        row.querySelector('.credit-taxes').replaceChildren(); (item.taxes || []).forEach(function (tax) { addTax(row, tax); });
        row.querySelectorAll('[data-credit-field]').forEach(function (group) { if (Object.prototype.hasOwnProperty.call(item.custom_fields.values, group.dataset.creditField)) setCustomField(group, item.custom_fields.values[group.dataset.creditField]); });
        if (replace) host.replaceChild(row, first); else host.appendChild(row); buttons();
        picker.value = ''; update(true);
      }).catch(function (invalid) { if (serial === catalogSerial) requestError(invalid); });
    });
    form.addEventListener('click', function (event) { var button = event.target.closest('[data-credit-link-toggle]'); if (button) button.closest('[data-credit-field]').querySelector('[data-credit-link-editor]').classList.toggle('hide'); });
    function customFieldsValid() {
      form.querySelectorAll('[data-credit-field-type="link"]').forEach(function (group) {
        var url = group.querySelector('[data-credit-link-url]').value.trim(), title = group.querySelector('[data-credit-link-title]').value;
        if (!url) { if (title) throw new Error('Informe o endereço do link.'); group.querySelector('[data-credit-link-value]').value = ''; return; }
        if (!/^(?:https?:\/\/|mailto:)/i.test(url) || /[\x00-\x20\x7f]/.test(url)) throw new Error('Informe um link HTTP, HTTPS ou de e-mail válido.');
        var link = form.ownerDocument.createElement('a'); link.setAttribute('href', url); link.setAttribute('target', '_blank'); link.textContent = title || url; group.querySelector('[data-credit-link-value]').value = link.outerHTML;
      });
      form.querySelectorAll('[data-credit-required-field]').forEach(function (group) { var controls = Array.from(group.querySelectorAll('[name]')), present = controls.some(function (control) { return control.type === 'checkbox' ? control.checked : control.tagName === 'SELECT' && control.multiple ? Array.from(control.selectedOptions).some(function (option) { return option.value !== ''; }) : control.value.trim() !== ''; }); if (!present) throw new Error('Preencha os campos personalizados obrigatórios.'); });
      return true;
    }
    form.addEventListener('input', function () { update(false); addressLabels(); });
    form.addEventListener('change', function (event) { update(false); addressLabels(); if (event.target.name === 'credit[native_metadata][show_quantity_as]') form.querySelector('[data-credit-quantity-label]').textContent = event.target.dataset.text || ['','Quantidade','Horas','Qtd./Horas'][Number(event.target.value)]; });
    form.addEventListener('submit', function (event) {
      try { if (!current() || reads.busy()) throw new Error('Aguarde a confirmação dos dados da empresa e do cliente.'); customFieldsValid(); if (!update(true)) throw new Error(error.textContent); }
      catch (invalid) { event.preventDefault(); fail(invalid.message); error.focus(); }
    });
    buttons(); update(false); addressLabels();
  }
  return { calculate: calculate, readCoordinator: readCoordinator, mount: mount };
});
