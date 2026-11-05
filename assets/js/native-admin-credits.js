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
  function mount(form) {
    if (!form || form.dataset.creditMounted) return;
    form.dataset.creditMounted = '1';
    var host = form.querySelector('#credit-items'), add = form.querySelector('#credit-add-item'), error = form.querySelector('#credit-form-error'), next = 1;
    if (!host || !add) return;
    // The shared original sales form also observes this layout. Its float calculator
    // must not overwrite the exact ledger preview; keep the existing opt-out flag.
    form.ownerDocument.body.classList.add('no-calculate-total');
    var template = host.firstElementChild.cloneNode(true);
    function input(node, ending) { return node.querySelector('[name$="[' + ending + ']"]'); }
    function values() {
      return { items: Array.from(host.children).map(function (row) { return { qty: input(row, 'qty').value, rate: row.querySelector('[name="credit[items][' + row.dataset.index + '][rate]"]').value, taxes: Array.from(row.querySelectorAll('.credit-tax')).map(function (tax) { return { name: input(tax, 'name').value, rate: input(tax, 'rate').value }; }) }; }), discountMode: form.querySelector('#credit-discount-mode').value, discountValue: form.querySelector('#credit-discount').value, discountType: form.querySelector('#credit-discount-type').value, adjustment: form.querySelector('#credit-adjustment').value };
    }
    function update(showError) {
      try {
        var totals = calculate(values());
        form.querySelectorAll('[data-credit-total]').forEach(function (node) { node.textContent = totals[node.dataset.creditTotal]; });
        Array.from(host.children).forEach(function (row, index) { row.querySelector('[data-credit-line-amount]').textContent = totals.lines[index]; });
        error.classList.add('hide'); return true;
      } catch (invalid) {
        form.querySelectorAll('[data-credit-total],[data-credit-line-amount]').forEach(function (node) { node.textContent = '—'; });
        if (showError) { error.textContent = invalid.message; error.classList.remove('hide'); }
        return false;
      }
    }
    function buttons() { host.querySelectorAll('[data-credit-remove]').forEach(function (button) { button.disabled = host.children.length === 1; }); add.disabled = host.children.length >= 100; }
    add.addEventListener('click', function () {
      if (host.children.length >= 100) return;
      var row = template.cloneNode(true), index = next++;
      row.dataset.index = String(index); row.querySelectorAll('[id]').forEach(function (node) { node.removeAttribute('id'); });
      row.querySelectorAll('[name]').forEach(function (node) { node.name = node.name.replace(/credit\[items\]\[\d+\]/, 'credit[items][' + index + ']'); node.value = node.name.endsWith('[qty]') ? '1.00' : node.name.includes('[taxes]') && node.name.endsWith('[rate]') ? '0.00' : ''; });
      row.querySelector('.credit-taxes').replaceChildren(); host.appendChild(row); buttons(); update(false);
    });
    host.addEventListener('click', function (event) {
      var button = event.target.closest('button'); if (!button || !host.contains(button)) return;
      var row = button.closest('.credit-item');
      if (button.hasAttribute('data-credit-remove')) { if (host.children.length > 1) row.remove(); buttons(); update(false); return; }
      if (button.hasAttribute('data-credit-tax-remove')) { button.closest('.credit-tax').remove(); row.querySelector('[data-credit-tax]').disabled = false; update(false); return; }
      if (!button.hasAttribute('data-credit-tax')) return;
      var taxes = row.querySelector('.credit-taxes'); if (taxes.children.length >= 10) return;
      var index = 0; taxes.querySelectorAll('[data-tax-index]').forEach(function (node) { index = Math.max(index, Number(node.dataset.taxIndex) + 1); });
      var tax = template.querySelector('.credit-tax').cloneNode(true); tax.dataset.taxIndex = String(index);
      tax.querySelectorAll('[name]').forEach(function (node) { node.name = node.name.replace(/credit\[items\]\[\d+\]\[taxes\]\[\d+\]/, 'credit[items][' + row.dataset.index + '][taxes][' + index + ']'); node.value = node.name.endsWith('[rate]') ? '0.00' : ''; });
      tax.querySelector('[data-credit-tax-remove]').setAttribute('data-credit-tax-remove', String(index)); taxes.appendChild(tax); button.disabled = taxes.children.length >= 10; update(false);
    });
    form.addEventListener('input', function () { update(false); }); form.addEventListener('change', function () { update(false); });
    form.addEventListener('submit', function (event) { if (!update(true)) { event.preventDefault(); error.focus(); } });
    buttons(); update(false);
  }
  return { calculate: calculate, mount: mount };
});
