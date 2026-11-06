/* Original expense directory, preview and totals over the captured company transport. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root && root.jQuery && root.document.body.classList.contains('native-company-admin')) api.mount(root, root.jQuery);
})(typeof window === 'undefined' ? null : window, function () {
  'use strict';
  function canonical(value) {
    var id = String(value), number = Number(id);
    var radix = 4294967296;
    if (!/^[1-9]\d*$/.test(id) || !Number.isSafeInteger(number) || !(number < radix || number > 7 * radix && number < 8 * radix)) throw Error('Identificador de despesa inválido.');
    return id;
  }
  function decoded(value) {
    if (typeof value === 'string') value = JSON.parse(value);
    if (!value || typeof value !== 'object' || value.success === false || typeof value.html !== 'string') throw Error(value && value.message || 'Não foi possível carregar as despesas.');
    return value;
  }
  function coordinator(transport, current) {
    var pending = Object.create(null);
    return function (channel, request, done, fail) {
      var before = pending[channel], entry = { xhr: null }; pending[channel] = entry;
      if (before && before.xhr && typeof before.xhr.abort === 'function') before.xhr.abort();
      try {
        if (!current()) throw Error('A empresa ativa mudou. Reabra a página.');
        entry.xhr = transport(request);
        entry.xhr.done(function (reply) { if (pending[channel] !== entry || !current()) return; try { done(decoded(reply)); } catch (error) { fail(error); } });
        entry.xhr.fail(function (error, status) { if (pending[channel] === entry && current() && status !== 'abort') fail(error); });
      } catch (error) { if (pending[channel] === entry) fail(error); }
      return entry.xhr;
    };
  }
  // The original print helper interpolates heading text as HTML. Keep the same
  // print content while creating the user-controlled headings as text nodes.
  function printPreview(root) {
    var panel = root.document.querySelector('#tab_expense'), headings = root.document.querySelector('#expenseHeadings');
    if (!panel || !headings) return;
    var win = root.open('', '_blank'); if (!win) throw Error('Permita a janela de impressão para esta página.');
    var doc = win.document; doc.open(); doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Despesa</title></head><body></body></html>'); doc.close();
    root.document.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) { doc.head.appendChild(doc.importNode(link, true)); });
    var title = doc.createElement('h1'); title.textContent = headings.textContent.trim(); doc.body.appendChild(title);
    var copy = doc.importNode(panel, true); copy.querySelectorAll('#expenseReceipt,form,button,[data-toggle="modal"]').forEach(function (node) { node.remove(); }); doc.body.appendChild(copy); win.focus(); win.print();
  }
  function relatedTasks(root, value) {
    var id = canonical(value);
    if (root.NativeCompanyContextGuard && !root.NativeCompanyContextGuard.isCurrent()) throw Error('A empresa ativa mudou. Reabra a página.');
    if (typeof root.init_rel_tasks_table !== 'function') throw Error('Reabra a página para carregar as tarefas.');
    return root.init_rel_tasks_table(id, 'expense');
  }
  function bulkPayload(selection, fields, key, csrf) {
    if (!Array.isArray(selection) || selection.length < 1 || selection.length > 200 || !/^[a-f0-9]{64}$/.test(key) || !/^[a-f0-9]{64}$/.test(csrf)) throw Error('Selecione as despesas e reabra as ações em lote.');
    var revisions = Object.create(null), ids = selection.map(function (row) {
      var id = canonical(row.id); if (Number(id) < 4294967296 || Object.hasOwn(revisions, id) || !/^[a-f0-9]{64}$/.test(row.revision)) throw Error('A seleção de despesas mudou. Recarregue a tabela.');
      revisions[id] = row.revision; return id;
    });
    var data = { native_expense_bulk: 'original_expense_bulk_v1', native_expenses_csrf: csrf, request_key: key, ids: ids, revisions: revisions, mass_delete: fields.cancel ? '1' : '0' };
    ['amount', 'date', 'category', 'paymentmode'].forEach(function (name) { if (typeof fields[name] !== 'string') throw Error('Confira os campos do lote.'); data[name] = fields.cancel ? '' : fields[name]; });
    if (!fields.cancel && !['amount', 'date', 'category', 'paymentmode'].some(function (name) { return data[name] !== ''; })) throw Error('Informe ao menos um campo para atualizar.');
    return data;
  }
  function newKey(root) {
    if (!root.crypto || typeof root.crypto.getRandomValues !== 'function') throw Error('Reabra a página em uma conexão segura.');
    var bytes = new Uint8Array(32); root.crypto.getRandomValues(bytes); return Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
  }
  function mount(root, $) {
    if (root.NativeCompanyExpensesIndex) return;
    var document = root.document, table = null, selected = '', compact = false, full = false, bulkSelection = [], bulkBusy = false;
    var request = coordinator(function (o) { return root.NativeCompanyTransport.request(o.path, o.method, o.data, { dataType: 'json' }); }, function () { return !root.NativeCompanyContextGuard || root.NativeCompanyContextGuard.isCurrent(); });
    function error(problem) { var text = problem && problem.responseJSON && problem.responseJSON.message || problem && problem.message || 'Não foi possível carregar as despesas. Reabra a página.'; $('#native-expense-page-error').text(text).removeClass('hide'); }
    function token() { var field = document.querySelector('#native-expenses-csrf'); if (!field || !/^[a-f0-9]{64}$/.test(field.value)) throw Error('Reabra a página antes de continuar.'); return field.value; }
    function widgets() { ['init_btn_with_tooltips', 'init_selectpicker', 'init_datepicker', 'init_form_reminder', 'init_tabs_scrollable'].forEach(function (name) { if (typeof root[name] === 'function') root[name](); }); }
    function layout(value) {
      compact = value; if (!compact) full = false;
      $('body').toggleClass('small-table', compact); $('#small-table').toggleClass('col-md-12', !compact).toggleClass('col-md-5', compact).toggleClass('hide', full);
      $('.small-table-right-col').toggleClass('hide', !compact).toggleClass('col-md-12', full).toggleClass('col-md-7', !full);
      $('.toggle-small-view i').toggleClass('fa-angle-double-right', compact).toggleClass('fa-angle-double-left', !compact);
      if (table) { table.columns([4, 5, 6, 7, 8, 9]).visible(!compact, false); table.columns.adjust(); } $(root).trigger('resize');
    }
    function open(value) {
      try {
        var id = canonical(value === undefined ? root.location.hash.substring(1) || $('input[name="expenseid"]').val() : value);
        return request('preview', { path: 'expenses/get_expense_data_ajax/' + id, method: 'GET' }, function (reply) {
          if (String(reply.expenseid) !== id) throw Error('O detalhe da despesa mudou. Reabra a página.');
          selected = id; $('#expense').html(reply.html).removeClass('hide'); $('input[name="expenseid"]').val(id); layout(true); widgets(); if (root.location.hash.substring(1) !== id) root.location.hash = id;
        }, error);
      } catch (problem) { error(problem); }
    }
    function totals() {
      if (!document.querySelector('[data-native-expenses]')) return;
      try {
        var years = $('select[name="expenses_total_years"]').val() || [], currency = $('select[name="expenses_total_currency"]').val() || '';
        return request('totals', { path: 'expenses/get_expenses_total', method: 'POST', data: { native_expenses_csrf: token(), currency: currency, years: years, state: $('#native-expense-state').val() || 'recorded' } }, function (reply) {
          $('#expenses_total').html(reply.html); if ($.fn.selectpicker) $('#expenses_total .selectpicker').selectpicker();
        }, error);
      } catch (problem) { error(problem); }
    }
    function checkedRows() { return Array.from(document.querySelectorAll('[data-native-expense-selection]:checked')).map(function (input) { return { id: input.value, revision: input.getAttribute('data-revision') }; }); }
    function selectionChanged() { var count = checkedRows().length; $('[data-native-expense-bulk-open]').toggleClass('hide', count === 0); }
    function bulkError(problem) { var message = problem && problem.responseJSON && problem.responseJSON.message || problem && problem.message || 'Não foi possível confirmar o lote. Reabra as despesas.'; $('[data-expense-bulk-error]').text(message).removeClass('hide'); }
    $(document).on('click.nativeExpenses', '[data-native-expense]', function (event) { event.preventDefault(); open($(this).attr('data-native-expense')); });
    $(document).on('click.nativeExpenses', '[data-expense-toggle]', function (event) { event.preventDefault(); if (selected) { if (full) { full = false; layout(true); } else layout(!compact); } });
    $(document).on('click.nativeExpenses', '[data-expense-full-view]', function (event) { event.preventDefault(); if (selected && compact) { full = !full; layout(true); } });
    $(root).on('hashchange.nativeExpenses', function () { var id = root.location.hash.substring(1); if (document.querySelector('[data-native-expenses]') && id && id !== selected) open(id); });
    $(document).on('click.nativeExpenses', '[data-expense-print]', function (event) { event.preventDefault(); try { printPreview(root); } catch (problem) { error(problem); } });
    $(document).on('click.nativeExpenses', '[data-expense-reminders]', function () { var id = canonical($(this).attr('data-expense-reminders')); root.initDataTable('.table-reminders', root.admin_url + 'misc/get_reminders/' + id + '/expense', undefined, undefined, undefined, [[1, 'asc']]); });
    $(document).on('click.nativeExpenses', '[data-expense-tasks]', function () { try { relatedTasks(root, $(this).attr('data-expense-tasks')); } catch (problem) { error(problem); } });
    $(document).on('change.nativeExpenses', '[data-native-expense-selection]', selectionChanged);
    $(document).on('change.nativeExpenses', '[data-expense-select-all]', function () { $('[data-native-expense-selection]').prop('checked', this.checked); selectionChanged(); });
    $(document).on('change.nativeExpenses', '#expense-bulk-cancel', function () { $('#expenses_bulk_actions #bulk_change').toggleClass('hide', this.checked); });
    $(document).on('click.nativeExpenses', '[data-native-expense-bulk-open]', function (event) {
      event.preventDefault(); if (bulkBusy) return;
      try { if (root.NativeCompanyContextGuard && !root.NativeCompanyContextGuard.isCurrent()) throw Error('A empresa ativa mudou. Reabra a página.'); bulkSelection = checkedRows(); if (!bulkSelection.length) throw Error('Selecione as despesas.');
        var form = document.querySelector('#native-expense-bulk-form'); form.reset(); form.elements.request_key.value = newKey(root); $('#expenses_bulk_actions #bulk_change').removeClass('hide'); $('[data-expense-bulk-error]').text('').addClass('hide'); $('[data-expense-bulk-count]').text(bulkSelection.length + ' despesas selecionadas'); if ($.fn.selectpicker) $(form).find('.selectpicker').selectpicker('refresh'); $('#expenses_bulk_actions').modal('show');
      } catch (problem) { error(problem); }
    });
    $(document).on('submit.nativeExpenses', '#native-expense-bulk-form', function (event) {
      event.preventDefault(); if (bulkBusy) return; var form = this;
      try {
        if (root.NativeCompanyContextGuard && !root.NativeCompanyContextGuard.isCurrent()) throw Error('A empresa ativa mudou. Reabra a página.');
        var fields = { cancel: !!(form.elements.mass_delete && form.elements.mass_delete.checked) }; ['amount', 'date', 'category', 'paymentmode'].forEach(function (name) { fields[name] = form.elements[name] ? form.elements[name].value : ''; });
        var payload = bulkPayload(bulkSelection, fields, form.elements.request_key.value, token()); if (fields.cancel && !root.confirm('Cancelar as despesas selecionadas e preservar seu histórico?')) return;
        bulkBusy = true; $(form).find('button[type="submit"]').prop('disabled', true);
        root.NativeCompanyTransport.request('expenses/bulk_action?source=company', 'POST', payload, { dataType: 'json' }).done(function (reply) {
          try { if (root.NativeCompanyContextGuard && !root.NativeCompanyContextGuard.isCurrent()) throw Error('A empresa ativa mudou. Reabra a página.'); if (typeof reply === 'string') reply = JSON.parse(reply); if (!reply || reply.success !== true || reply.processed !== bulkSelection.length || reply.operation !== (fields.cancel ? 'cancel' : 'update')) throw Error('Não foi possível confirmar o lote. Reabra as despesas.');
            $('#expenses_bulk_actions').modal('hide'); $('[data-expense-select-all]').prop('checked', false); table.ajax.reload(null, false); totals(); if (selected) open(selected); if (typeof root.alert_float === 'function') root.alert_float('success', reply.message);
          } catch (problem) { bulkError(problem); }
        }).fail(bulkError).always(function () { bulkBusy = false; $(form).find('button[type="submit"]').prop('disabled', false); });
      } catch (problem) { bulkBusy = false; $(form).find('button[type="submit"]').prop('disabled', false); bulkError(problem); }
    });
    $(document).on('submit.nativeExpenses', '[data-expense-confirm]', function (event) { if (!root.confirm(this.getAttribute('data-expense-confirm'))) event.preventDefault(); });
    $(document).on('change.nativeExpenses', 'select[name="expenses_total_years"],select[name="expenses_total_currency"]', totals);
    $(function () {
      var page = document.querySelector('[data-native-expenses]'); if (!page) return;
      var element = $('table.table-expenses');
      element.on('xhr.dt', function (event, settings, data, xhr) { if (!data || data.success === false) { error(xhr || data); return true; } });
      element.on('draw.dt', function () { $('[data-expense-select-all]').prop('checked', false); selectionChanged(); });
      table = root.initDataTable(element, root.admin_url + 'expenses/table', [0], [0], { native_expenses_csrf: '#native-expenses-csrf', state: '#native-expense-state' }, [[6, 'desc']]);
      table.column(1).visible(false, false); table.columns.adjust();
      $('#native-expense-state').on('change.nativeExpenses', function () { table.ajax.reload(); totals(); });
      totals(); if (page.dataset.openExpense || root.location.hash) open(root.location.hash.substring(1) || page.dataset.openExpense);
    });
    root.init_expense = open; root.init_expenses_total = totals;
    root.NativeCompanyExpensesIndex = Object.freeze({ open: open, totals: totals });
  }
  return { canonical: canonical, decoded: decoded, coordinator: coordinator, printPreview: printPreview, relatedTasks: relatedTasks, bulkPayload: bulkPayload, mount: mount };
});
