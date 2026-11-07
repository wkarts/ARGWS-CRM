/* Original category list and inline modal, scoped to the captured company transport. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root && root.jQuery && root.document.body.classList.contains('native-company-admin')) api.mount(root, root.jQuery);
})(typeof window === 'undefined' ? null : window, function () {
  'use strict';
  function categoryId(value) {
    var id = String(value), number = Number(id);
    var radix = 4294967296;
    if (!/^[1-9]\d*$/.test(id) || !Number.isSafeInteger(number) || number <= 53 * radix || number >= 54 * radix) throw Error('Reabra a categoria da empresa ativa.');
    return id;
  }
  function fields(value) {
    if (typeof value === 'string') value = JSON.parse(value);
    if (!value || typeof value !== 'object' || typeof value.name !== 'string' || typeof value.description !== 'string' || !/^[a-f0-9]{64}$/.test(value.revision) || ![0, 1].includes(value.active)) throw Error('A categoria mudou. Reabra a página.');
    return { id: categoryId(value.id), name: value.name, description: value.description, active: value.active, revision: value.revision };
  }
  function requestKey(root) {
    if (!root.crypto || typeof root.crypto.getRandomValues !== 'function') throw Error('Reabra a página em uma conexão segura.');
    var bytes = new Uint8Array(32); root.crypto.getRandomValues(bytes);
    return Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
  }
  function response(value) {
    if (typeof value === 'string') value = JSON.parse(value);
    if (!value || value.success !== true || typeof value.name !== 'string' || ![0, 1].includes(value.active)) throw Error(value && value.message || 'Não foi possível confirmar a categoria.');
    categoryId(value.id); return value;
  }
  function selectCreated(document, value) {
    var select = document.querySelector('#category'); if (!select || value.active !== 1) return;
    var id = categoryId(value.id), option = Array.from(select.options).find(function (item) { return item.value === id; });
    if (!option) { option = document.createElement('option'); option.value = id; select.insertBefore(option, select.options[1] || null); }
    option.textContent = value.name; select.value = id; return select;
  }
  function mount(root, $) {
    if (root.NativeCompanyExpenseCategories) return;
    var doc = root.document, table = null, busy = false;
    function current() { if (root.NativeCompanyContextGuard && !root.NativeCompanyContextGuard.isCurrent()) throw Error('A empresa ativa mudou. Reabra a página.'); }
    function error(problem, form) { var message = problem && problem.responseJSON && problem.responseJSON.message || problem && problem.message || 'Não foi possível confirmar a categoria. Reabra a página antes de tentar novamente.'; $(form ? '[data-category-form-error]' : '#native-expense-category-error').text(message).removeClass('hide'); }
    function open(value) {
      current(); if (busy) return;
      var form = doc.querySelector('#expense-category-form'); if (!form) throw Error('Reabra as categorias da empresa.');
      var data = value === null ? { id: '', name: '', description: '', active: 1, revision: '' } : fields(value);
      ['id', 'name', 'description', 'revision'].forEach(function (name) { form.elements[name].value = data[name]; });
      form.elements.request_key.value = requestKey(root);
      if (form.elements.active.type === 'checkbox') form.elements.active.checked = data.active === 1; else form.elements.active.value = String(data.active);
      $('#expense-category-modal .edit-title').toggleClass('hide', data.id === ''); $('#expense-category-modal .add-title').toggleClass('hide', data.id !== '');
      $('[data-category-form-error]').text('').addClass('hide'); $('#expense-category-modal').modal('show');
    }
    $(doc).on('click.nativeExpenseCategories', '[data-native-expense-category]', function (event) {
      event.preventDefault(); try { open(this.getAttribute('data-native-expense-category') === 'new' ? null : this.getAttribute('data-category')); } catch (problem) { error(problem, false); }
    });
    $(doc).on('submit.nativeExpenseCategories', '#expense-category-form', function (event) {
      event.preventDefault(); if (busy) return; var form = this;
      try {
        current(); var data = $(form).serializeArray().filter(function (item) { return item.name !== 'active'; });
        data.push({ name: 'active', value: form.elements.active.type === 'checkbox' ? (form.elements.active.checked ? '1' : '0') : form.elements.active.value });
        busy = true; $(form).find('button[type="submit"]').prop('disabled', true);
        root.NativeCompanyTransport.request('expenses/category?source=company', 'POST', $.param(data), { dataType: 'json' }).done(function (reply) {
          try {
            current(); var result = response(reply);
            if (doc.querySelector('[data-native-category-modal]').dataset.inline === '1') { var select = selectCreated(doc, result); if (select && $.fn.selectpicker) $(select).selectpicker('refresh'); }
            if (table) table.ajax.reload(null, false); $('#expense-category-modal').modal('hide');
            if (typeof root.alert_float === 'function') root.alert_float('success', result.message || 'Categoria salva.');
          } catch (problem) { error(problem, true); }
        }).fail(function (problem) { error(problem, true); }).always(function () { busy = false; $(form).find('button[type="submit"]').prop('disabled', false); });
      } catch (problem) { busy = false; $(form).find('button[type="submit"]').prop('disabled', false); error(problem, true); }
    });
    $(function () {
      if (!doc.querySelector('[data-native-expense-categories]')) return;
      var element = $('.table-expenses-categories'); element.on('xhr.dt', function (event, settings, data, xhr) { if (!data || data.success === false) { error(xhr || data, false); return true; } });
      table = root.initDataTable(element, root.admin_url + 'expenses/categories?source=company', [3], [3], { native_expenses_csrf: '#native-expenses-csrf' }, [[1, 'asc']]);
    });
    root.NativeCompanyExpenseCategories = Object.freeze({ open: open });
  }
  return { categoryId: categoryId, fields: fields, requestKey: requestKey, response: response, selectCreated: selectCreated, mount: mount };
});
