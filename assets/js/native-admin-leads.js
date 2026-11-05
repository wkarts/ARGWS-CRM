/* Original Lead modal/list/Kanban. All data and mutations use the existing company boundary. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root && root.jQuery && root.document.body.classList.contains('native-company-admin')) api.mount(root, root.jQuery);
})(typeof window === 'undefined' ? null : window, function () {
  'use strict';
  function canonical(value) {
    var id = String(value);
    if (!/^[1-9]\d*$/.test(id) || !Number.isSafeInteger(Number(id)) || Number(id) <= 25769803776 || Number(id) > 27917287423) throw Error('Identificador de lead inválido.');
    return id;
  }
  function key(crypto) { var bytes = new Uint8Array(32); crypto.getRandomValues(bytes); return Array.from(bytes, function (n) { return n.toString(16).padStart(2, '0'); }).join(''); }
  function decoded(value) { if (typeof value === 'string') value = JSON.parse(value); if (!value || typeof value !== 'object' || value.success === false) throw Error(value && value.message || 'Não foi possível confirmar a operação.'); return value; }
  // Shared by the actual modal and board: discard superseded/old-company reads;
  // never create a second write while the same submitted form is pending.
  function coordinator(transport, current) {
    var reads = Object.create(null), writes = new WeakMap();
    return {
      read: function (channel, options, done, fail) {
        var previous = reads[channel], entry = { xhr: null }; reads[channel] = entry;
        if (previous && previous.xhr && typeof previous.xhr.abort === 'function') previous.xhr.abort();
        try {
          if (!current()) throw Error('A empresa ativa mudou. Reabra a página.');
          entry.xhr = transport(options);
          entry.xhr.done(function (data) { if (reads[channel] !== entry || !current()) return; try { done(decoded(data)); } catch (error) { fail(error); } });
          entry.xhr.fail(function (error, status) { if (reads[channel] === entry && current() && status !== 'abort') fail(error); });
        } catch (error) { if (reads[channel] === entry) fail(error); }
        return entry.xhr;
      },
      write: function (owner, options, done, fail, pending) {
        if (writes.has(owner)) return null;
        writes.set(owner, true); pending(true); var xhr;
        function complete() { writes.delete(owner); pending(false); }
        try {
          if (!current()) throw Error('A empresa ativa mudou. Reabra a página.');
          xhr = transport(options);
          xhr.done(function (data) { if (!current()) return; try { done(decoded(data)); } catch (error) { fail(error); } });
          xhr.fail(function (error, status) { if (current() && status !== 'abort') fail(error); });
          xhr.always(complete);
        } catch (error) { complete(); fail(error); }
        return xhr;
      }
    };
  }
  function mount(root, $) {
    if (root.NativeCompanyLeads) return;
    var document = root.document, table = null, boardGeneration = 0, sort = 'desc', searchTimer;
    var requests = coordinator(function (o) { return root.NativeCompanyTransport.request(o.path, o.method || 'GET', o.data, { dataType: 'json', processData: o.processData !== false, contentType: o.contentType === false ? false : 'application/x-www-form-urlencoded; charset=UTF-8' }); }, function () { return !root.NativeCompanyContextGuard || root.NativeCompanyContextGuard.isCurrent(); });
    function message(error) { return error && error.responseJSON && error.responseJSON.message || error && error.message || 'Não foi possível confirmar a operação. Reabra o lead.'; }
    function pageError(error) { $('#native-lead-page-error').text(message(error)).removeClass('hide'); if (!document.querySelector('#native-lead-page-error') && root.alert_float) root.alert_float('danger', message(error)); }
    function modal() {
      var host = $('#lead-modal');
      if (!host.length) { $('body').append('<div class="modal fade lead-modal" id="lead-modal" tabindex="-1" role="dialog" aria-label="Lead"><div class="modal-dialog modal-lg"><div class="modal-content data"></div></div></div>'); host = $('#lead-modal'); }
      return host;
    }
    function display(reply, tab) {
      if (typeof reply.leadView !== 'string') throw Error('Resposta do lead incompleta.');
      var host = modal(); host.find('.modal-content').html(reply.leadView);
      if ($.fn.selectpicker) host.find('.selectpicker').selectpicker();
      if (tab && host.find('a[href="' + tab + '"]').length) host.find('a[href="' + tab + '"]').tab('show');
      host.modal('show');
    }
    function open(id, edit, status) {
      var path = 'leads/lead' + (id === undefined || id === null || id === '' ? '' : '/' + canonical(id));
      var query = {}; if (edit) query.edit = '1'; if (status) query.status = String(status);
      return requests.read('modal', { path: path, data: query }, function (reply) { display(reply); }, pageError);
    }
    function csrf() { var field = document.querySelector('#native-leads-csrf') || document.querySelector('#lead-modal [name="native_leads_csrf"]'); if (!field || !/^[a-f0-9]{64}$/.test(field.value)) throw Error('Reabra a página antes de salvar.'); return field.value; }
    function sortables() {
      var lists = $('[data-native-lead-board] .leads-status'); if (!$.fn.sortable) return;
      lists.each(function () {
        var list = $(this); if (list.data('ui-sortable')) { list.sortable('refresh'); return; }
        list.sortable({ connectWith: '[data-native-lead-board] .leads-status[data-status-active="1"]', items: '> .lead-kan-ban:not(.not-sortable)', helper: 'clone', placeholder: 'ui-state-highlight', forcePlaceholderSize: true,
          update: function (event, ui) {
            // The current writer stores stage transitions, not an intra-stage card rank.
            if (!ui.sender && ui.item.parent()[0] === this && ui.item.attr('data-lead-stage') === $(this).attr('data-lead-status-id')) $(this).sortable('cancel');
          },
          receive: function (event, ui) {
            var node = ui.item[0], item = ui.item, status = String($(this).attr('data-lead-status-id'));
            if ($(this).attr('data-status-active') !== '1') { $(ui.sender).sortable('cancel'); return; }
            var data;
            try { data = { native_lead_form: 'original_lead_v1', action: 'move', lead_id: canonical(item.attr('data-lead-id')), revision: item.attr('data-lead-revision'), status: status, native_leads_csrf: csrf(), request_key: key(root.crypto) }; }
            catch (error) { $(ui.sender).sortable('cancel'); pageError(error); return; }
            requests.write(node, { path: 'leads/update_lead_status', method: 'POST', data: data }, function () { loadBoard(); }, function (error) { pageError(error); loadBoard(); }, function (busy) { item.toggleClass('not-sortable', busy); });
          }
        });
      });
    }
    function loadBoard() {
      var host = $('[data-native-lead-board]'); if (!host.length) return; var generation = ++boardGeneration;
      requests.read('board', { path: 'leads/kanban', data: { search: $('#native-lead-search').val() || '', sort: sort } }, function (reply) {
        if (generation !== boardGeneration || typeof reply.html !== 'string') return;
        host.html(reply.html); if (typeof root.fix_kanban_height === 'function') root.fix_kanban_height(315, 360); sortables();
      }, pageError);
    }
    function refresh() { if (table) table.ajax.reload(null, false); loadBoard(); }
    $(document).on('click.nativeLeads', '[data-native-lead]', function (event) { event.preventDefault(); try { open($(this).attr('data-native-lead'), $(this).attr('data-lead-edit') === '1'); } catch (error) { pageError(error); } });
    $(document).on('click.nativeLeads', '[data-new-lead-status]', function (event) { event.preventDefault(); open('', false, $(this).attr('data-new-lead-status')); });
    $(document).on('click.nativeLeads', '[data-native-lead-edit],[data-native-lead-cancel]', function (event) {
      event.preventDefault(); var edit = this.hasAttribute('data-native-lead-edit'), host = modal(); host.find('[data-lead-profile-view]').prop('hidden', edit); host.find('[data-lead-profile-edit]').toggleClass('hide', !edit); host.find('.lead-wrapper').toggleClass('open-edit', edit);
    });
    $(document).on('submit.nativeLeads', '[data-native-lead-form]', function (event) {
      event.preventDefault(); var form = this, button = form.querySelector('[data-lead-confirm]'); if (button && !root.confirm(button.getAttribute('data-lead-confirm'))) return;
      var tab = modal().find('.nav-tabs li.active a').attr('href'), target = $(form).find('[data-native-lead-error]'); if (!target.length) target = modal().find('[data-native-lead-error]'); target.addClass('hide');
      requests.write(form, { path: form.action, method: 'POST', data: new root.FormData(form), processData: false, contentType: false }, function (reply) { if (document.contains(form)) display(reply, tab); refresh(); }, function (error) { target.text(message(error)).removeClass('hide'); }, function (busy) { $(form).find('button[type="submit"]').prop('disabled', busy); });
    });
    $(document).on('click.nativeLeads', '[data-lead-board-sort]', function (event) { event.preventDefault(); sort = $(this).attr('data-lead-board-sort'); loadBoard(); });
    $(document).on('input.nativeLeads', '#native-lead-search', function () { root.clearTimeout(searchTimer); searchTimer = root.setTimeout(loadBoard, 250); });
    $(root).on('resize.nativeLeads', function () { if (document.querySelector('[data-native-lead-board]') && typeof root.fix_kanban_height === 'function') root.fix_kanban_height(315, 360); });
    $(document).on('click.nativeLeads', '[data-lead-load-status]', function (event) {
      event.preventDefault(); var button = $(this), status = button.attr('data-lead-load-status'), generation = boardGeneration; if (button.hasClass('disabled')) return; button.addClass('disabled');
      requests.read('board-more-' + status, { path: 'leads/kanban', data: { status: status, offset: button.attr('data-offset'), search: $('#native-lead-search').val() || '', sort: sort } }, function (reply) {
        if (generation !== boardGeneration) return; if (typeof reply.html !== 'string') throw Error('Resposta do Kanban incompleta.'); $(reply.html).insertBefore(button.closest('.kanban-load-more')); button.removeClass('disabled').attr('data-offset', reply.next === null ? '0' : String(reply.next)); button.closest('.kanban-load-more').toggleClass('hide', reply.next === null); sortables();
      }, function (error) { if (generation === boardGeneration) { button.removeClass('disabled'); pageError(error); } });
    });
    $(function () {
      var page = document.querySelector('[data-native-leads]'); if (!page) return;
      var element = $('table.table-leads');
      if (element.length) {
        element.on('xhr.dt', function (event, settings, data, xhr) { if (!data) { pageError(xhr); return true; } if (Array.isArray(data.summary)) data.summary.forEach(function (row) { $('[data-lead-summary="' + row.id + '"]').text(row.total); }); });
        table = root.initDataTable(element, root.admin_url + 'leads/table', [0, 6, 7, 11], [0, 6, 7, 11], { native_leads_csrf: '#native-leads-csrf', state: '#native-lead-state' }, [[12, 'desc'], [1, 'asc']]);
        $('#native-lead-state').on('change.nativeLeads', function () { table.ajax.reload(); });
      }
      if (page.dataset.mode === 'kanban') loadBoard();
      if (page.dataset.openLead) open(page.dataset.openLead);
    });
    root.init_lead = open; root.leads_kanban = loadBoard;
    root.NativeCompanyLeads = Object.freeze({ open: open, refresh: refresh });
  }
  return { canonical: canonical, key: key, decoded: decoded, coordinator: coordinator, mount: mount };
});
