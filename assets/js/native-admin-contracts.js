/* Original contract information controls; writes retain the existing owned draft/receipt boundary. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (!root) return;
  root.NativeContractForm = api;
  function boot() { root.document.querySelectorAll('[data-native-contract-editor]').forEach(api.mount); }
  if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', boot); else boot();
})(typeof window === 'undefined' ? null : window, function () {
  'use strict';
  function projectReader(transport, current) {
    var latest = null;
    function cancel() { var old = latest; latest = null; if (old && old.xhr && typeof old.xhr.abort === 'function') old.xhr.abort(); }
    return {
      cancel: cancel,
      busy: function () { return latest !== null; },
      read: function (client, contract) {
        if (!transport || !current()) return Promise.reject(new Error('Reabra o formulário para confirmar a empresa ativa.'));
        cancel(); var request = {}; latest = request;
        try { request.xhr = transport.request('contracts/client_change_data', 'GET', {client_id: client, contract_id: contract || ''}, {dataType: 'json'}); }
        catch (error) { latest = null; return Promise.reject(error); }
        return Promise.resolve(request.xhr).then(function (data) { return current() && latest === request ? data : undefined; }, function (error) {
          if (!current() || latest !== request) return undefined; throw error;
        }).finally(function () { if (latest === request) latest = null; });
      }
    };
  }
  function mount(form) {
    if (form.dataset.contractMounted) return;
    form.dataset.contractMounted = '1';
    var view = form.ownerDocument.defaultView, $ = view.jQuery, transport = view.NativeCompanyTransport;
    var client = form.querySelector('#contract-client'), project = form.querySelector('#contract-project'), revision = form.querySelector('#contract-project-revision'), message = form.querySelector('#contract-form-error');
    function current() { return !view.NativeCompanyContextGuard || view.NativeCompanyContextGuard.isCurrent(); }
    var reader = projectReader(transport, current), failed = false;
    function fail(text) { message.textContent = text; message.classList.remove('hide'); }
    function refresh(element) { if ($ && $.fn.selectpicker) $(element).selectpicker('refresh'); }
    function clearProjects() {
      if (!project) return;
      project.replaceChildren(new view.Option('', '')); revision.value = ''; refresh(project);
    }
    if ($ && typeof view.init_ajax_search === 'function') view.init_ajax_search('customer', '#contract-client');
    if (typeof view.custom_fields_hyperlink === 'function') view.custom_fields_hyperlink();
    if (typeof view.init_datepicker === 'function') view.init_datepicker();
    if (typeof view.init_color_pickers === 'function') view.init_color_pickers();
    client.addEventListener('change', function () {
      reader.cancel(); clearProjects(); failed = false; message.classList.add('hide');
      if (!client.value || !project) return;
      var selected = client.value;
      project.disabled = true; refresh(project);
      reader.read(selected, form.dataset.contractId).then(function (data) {
        if (data === undefined || client.value !== selected) return;
        if (!data || String(data.client_id) !== selected || data.can_projects !== true || !Array.isArray(data.projects)) throw new Error('Não foi possível confirmar os projetos do cliente.');
        var seen = new Set();
        data.projects.forEach(function (row) {
          if (!row || typeof row.id !== 'string' || !/^\d+$/.test(row.id) || typeof row.name !== 'string' || !Number.isInteger(row.revision) || row.revision < 1 || seen.has(row.id)) throw new Error('Não foi possível confirmar os projetos do cliente.');
          seen.add(row.id); var option = new view.Option(row.name, row.id); option.dataset.revision = String(row.revision); project.add(option);
        });
      }).catch(function () {
        if (!current() || client.value !== selected) return;
        clearProjects(); failed = true; fail('Não foi possível confirmar os projetos do cliente. Selecione o cliente novamente ou reabra o formulário.');
      }).finally(function () { if (current() && client.value === selected && !reader.busy()) { project.disabled = false; refresh(project); } });
    });
    if (project) project.addEventListener('change', function () { revision.value = project.value ? project.selectedOptions[0].dataset.revision || '' : ''; });
    form.addEventListener('submit', function (event) {
      if (!current() || reader.busy() || failed) { event.preventDefault(); fail('Aguarde a confirmação do cliente e da empresa antes de salvar o contrato.'); }
    });
    var typeForm = form.ownerDocument.querySelector('#native-contract-type-form'), typeSelect = form.querySelector('[name="contract[contract_type]"]');
    if (typeForm && $) typeForm.addEventListener('submit', function (event) {
      event.preventDefault(); if (typeForm.dataset.saving || !current()) return;
      var error = typeForm.querySelector('[data-contract-type-error]'), button = typeForm.querySelector('[type="submit"]');
      error.classList.add('hide'); typeForm.dataset.saving = '1'; button.disabled = true;
      var request;
      try { request = transport.request('contracts/type', 'POST', $(typeForm).serialize(), {dataType: 'json'}); }
      catch (invalid) { request = Promise.reject(invalid); }
      Promise.resolve(request).then(function (data) {
        if (!current()) return;
        if (!data || data.success !== true || typeof data.id !== 'string' || typeof data.name !== 'string') throw new Error('Invalid type response');
        var option = Array.from(typeSelect.options).find(function (row) { return row.value === data.id; });
        if (!option) { option = new view.Option(data.name, data.id); typeSelect.add(option); }
        option.selected = true; refresh(typeSelect); $('#native-contract-type-modal').modal('hide');
        typeForm.querySelector('[name="name"]').value = '';
        var bytes = new Uint8Array(32); view.crypto.getRandomValues(bytes);
        typeForm.querySelector('[name="request_key"]').value = Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
      }).catch(function () { if (current()) { error.textContent = 'Não foi possível salvar o tipo de contrato. Confira o nome e tente novamente.'; error.classList.remove('hide'); } })
        .finally(function () { delete typeForm.dataset.saving; button.disabled = false; });
    });
  }
  return {projectReader: projectReader, mount: mount};
});
