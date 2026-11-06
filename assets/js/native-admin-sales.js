/* Original sales split-list and pipeline navigation, bound to the captured company transport. */
(function (root, $) {
  'use strict';
  var serial = 0, previewRequest = null, pipelineRequest = null, pipelineTimer = null;
  function current() { return !root.NativeCompanyContextGuard || root.NativeCompanyContextGuard.isCurrent(); }
  function kind() {
    var value = $('[data-native-sales-kind]').attr('data-native-sales-kind');
    if (value !== 'estimates' && value !== 'proposals') throw new Error('native_sales_kind_required');
    return value;
  }
  function id(value) {
    value = String(value == null ? '' : value);
    if (!/^[1-9][0-9]{0,15}$/.test(value) || Number(value) > Number.MAX_SAFE_INTEGER) throw new Error('native_sales_identity_required');
    return value;
  }
  function open(value, expected) {
    if (!current() || kind() !== expected) return;
    var type = expected === 'estimates' ? 'estimate' : 'proposal';
    var input = expected === 'estimates' ? 'estimateid' : 'proposal_id';
    if (value == null || value === '') value = root.location.hash.substring(1) || $('input[name="' + input + '"]').val();
    if (value == null || value === '') return;
    value = id(value);var sequence = ++serial, target = $('#' + type);
    if (previewRequest) previewRequest.abort();
    target.empty();
    if ($('#small-table').length && !$('body').hasClass('small-table')) root.toggle_small_view('.table-' + expected, '#' + type);
    target.removeClass('hide').attr('aria-busy', 'true');
    $('input[name="' + input + '"]').val(value);
    if (root.history && root.history.replaceState) root.history.replaceState(null, '', '#' + value);
    previewRequest = $.ajax({url: admin_url + expected + '/get_' + type + '_data_ajax/' + value, method: 'GET', dataType: 'html'})
      .done(function (html) {
        if (!current() || sequence !== serial) return;
        target.html(html);
        if (typeof root.init_tabs_scrollable === 'function') root.init_tabs_scrollable();
        if (typeof root.init_selectpicker === 'function') root.init_selectpicker();
      }).always(function () { if (sequence === serial) target.attr('aria-busy', 'false'); });
  }
  root.init_estimate = function (value) { open(value, 'estimates'); };
  root.init_proposal = function (value) { open(value, 'proposals'); };
  root.small_table_full_view = function () {
    $('#small-table').toggleClass('hide');$('.small-table-right-col').toggleClass('col-md-12 col-md-7');$(root).trigger('resize');
  };
  function pipeline(expected) {
    if (!current() || kind() !== expected) return;
    if (pipelineTimer) root.clearTimeout(pipelineTimer);
    pipelineTimer = root.setTimeout(function () {
      if (pipelineRequest) pipelineRequest.abort();
      var sequence = ++serial;
      pipelineRequest = $.ajax({url: admin_url + expected + '/get_pipeline', method: 'GET', dataType: 'html', data: {
        search: $('input[name="search"]').val() || '', sort_by: $('input[name="sort_type"]').val() || 'pipeline_order', sort: $('input[name="sort"]').val() || 'ASC'
      }}).done(function (html) { if (current() && sequence === serial) $('#kan-ban').html(html); });
    }, 100);
  }
  root.estimate_pipeline = function () { pipeline('estimates'); };
  root.proposals_pipeline = function () { pipeline('proposals'); };
  function sort(column, expected) {
    if (!['datecreated', 'date', 'pipeline_order', 'expirydate', 'open_till'].includes(column)) throw new Error('native_sales_sort_invalid');
    var direction = $('input[name="sort_type"]').val() === column && $('input[name="sort"]').val() === 'ASC' ? 'DESC' : 'ASC';
    $('input[name="sort_type"]').val(column);$('input[name="sort"]').val(direction);pipeline(expected);
  }
  root.estimates_pipeline_sort = function (column) { sort(column, 'estimates'); };
  root.proposal_pipeline_sort = function (column) { sort(column, 'proposals'); };
  root.kanban_load_more = function (status, element, route) {
    var expected = kind(), button = $(element), page = Number(button.attr('data-page'));
    if (route !== expected + '/pipeline_load_more' || !Number.isInteger(page) || page < 1 || !/^[1-6]$/.test(String(status))) throw new Error('native_sales_page_invalid');
    if (button.hasClass('disabled') || button.attr('aria-busy') === 'true') return;
    button.attr('aria-busy', 'true');
    $.ajax({url: admin_url + route, method: 'GET', dataType: 'html', data: {status: status, page: page + 1,
      search: $('input[name="search"]').val() || '', sort_by: $('input[name="sort_type"]').val() || 'pipeline_order', sort: $('input[name="sort"]').val() || 'ASC'
    }}).done(function (html) {
      if (!current() || !document.documentElement.contains(element)) return;
      button.closest('li').before(html);button.attr('data-page', page + 1);
      if (page + 1 >= Number(button.closest('.kan-ban-col').attr('data-total-pages'))) button.addClass('disabled');
    }).always(function () { button.attr('aria-busy', 'false'); });
  };
  root.init_estimates_total = function () {
    if (!current() || !$('#estimates_total').length) return;
    $.ajax({url: admin_url + 'estimates/get_estimates_total', method: 'POST', dataType: 'html', data: {
      currency: $('select[name="total_currency"]').val() || '', years: $('select[name="estimates_total_years"]').val() || [],
      customer_id: '', project_id: ''
    }}).done(function (html) { if (current()) $('#estimates_total').html(html); });
  };
  $(function () {
    $(document).on('click.nativeSales', '[data-native-sales-id]', function (event) { event.preventDefault();open($(this).attr('data-native-sales-id'), kind()); });
    $(document).on('shown.bs.tab.nativeSales', '[data-native-sales-notes]', function () { root.get_sales_notes(id($(this).attr('data-native-sales-notes')), kind()); });
    $(document).on('submit.nativeSales', '[data-native-sales-note-form]', function (event) {
      event.preventDefault();var form = $(this), source = form.closest('[data-native-sales-preview]').attr('data-native-sales-preview');
      $.post(form.attr('action'), form.serialize()).done(function () { if (current()) { form.find('textarea').val('');root.get_sales_notes(id(source), kind()); } });
    });
  });
}(window, window.jQuery));

/* Original create/edit forms use the existing calculators and transaction controls. */
(function (root, $) {
  'use strict';
  var relationRequest = null, relationSerial = 0, submission = null;
  function current() { return !root.NativeCompanyContextGuard || root.NativeCompanyContextGuard.isCurrent(); }
  function notice(form, text) {
    var alert = form.find('[data-native-sales-error]');
    if (!alert.length) alert = $('<div class="alert alert-warning" role="alert" data-native-sales-error></div>').prependTo(form.find('.col-md-12').first());
    alert.text(text).removeClass('hide');
  }
  root.native_sales_original_submit = function (element) {
    var form = $(element);
    if (!current() || submission) { form.find('.transaction-submit').prop('disabled', false); return false; }
    if (typeof form.valid === 'function' && !form.valid()) { form.find('.transaction-submit').prop('disabled', false); return false; }
    var values = form.serializeArray();
    form.find(':input:disabled[name]').not('.transaction-submit').each(function () {
      if (this.type === 'checkbox' && !this.checked) return;
      var val = $(this).val(), name = this.name;
      if (Array.isArray(val)) val.forEach(function (part) { values.push({name: name, value: part}); });
      else if (val != null) values.push({name: this.name, value: val});
    });
    form.find('[data-native-sales-error]').addClass('hide');
    submission = $.ajax({url: form.attr('action'), method: 'POST', dataType: 'json', data: $.param(values)});
    submission.done(function (result) {
      if (!current()) return;
      if (!result || result.success !== true || typeof result.url !== 'string') { notice(form, 'Não foi possível confirmar a gravação.'); return; }
      var target = new URL(result.url, root.location.href), base = new URL(root.admin_url || admin_url, root.location.href);
      if (target.origin !== base.origin || !target.pathname.startsWith(base.pathname)) { notice(form, 'Destino de navegação inválido.'); return; }
      root.location.assign(target.href);
    }).fail(function (xhr) {
      if (current()) notice(form, xhr.responseJSON && typeof xhr.responseJSON.message === 'string' ? xhr.responseJSON.message : 'Não foi possível confirmar a gravação. Os campos continuam disponíveis para revisão.');
    }).always(function () {
      submission = null;form.find('.transaction-submit').prop('disabled', false);
      form.find('input[name="save_and_send"], input[name="save_and_send_later"]').remove();
    });
    return false;
  };
  root.native_sales_original_init = function (kind) {
    var type = kind === 'estimates' ? 'estimate' : kind === 'proposals' ? 'proposal' : null;
    if (!type) throw new Error('native_sales_kind_required');
    var form = $('#' + type + '-form'), context = root.native_sales_editor_context();
    if (!context || context.native_editor !== type) throw new Error('native_sales_editor_required');
    var rules = kind === 'estimates' ? {clientid:'required',date:'required',number:'required',currency:'required'}
      : {subject:'required',proposal_to:'required',rel_type:'required',rel_id:'required',date:'required',email:{required:true,email:true},currency:'required'};
    if (context.native_sales_id) { rules._native_sales_reason = 'required';rules._native_sales_confirmed = 'required'; }
    root.appValidateForm(form, rules);root.init_currency();
    if (typeof root.custom_fields_hyperlink === 'function') root.custom_fields_hyperlink();
    root.init_ajax_search('items', '#item_select.ajax-search', context, admin_url + 'items/search');
    var allowed = kind === 'estimates' ? ['1','2','5'] : ['1','4','5','6'];
    form.find('select[name="status"] option').each(function () { if (allowed.indexOf(this.value) < 0) $(this).prop('disabled', true).attr('title','Use a ação de aceite ou recusa no documento.'); });
    form.find('select[name="status"]').selectpicker('refresh');
    if (kind === 'estimates') { root.init_ajax_project_search_by_customer_id(); return; }
    var relation = form.find('#rel_id'), typeSelect = form.find('#rel_type');
    typeSelect.selectpicker('val','customer');form.find('#rel_id_wrapper').removeClass('hide');form.find('.rel_id_label').text(typeSelect.find('option:selected').text());
    root.init_ajax_search('customer', relation, {rel_id:function(){return relation.val();}});
    function projectSearch() { root.init_ajax_search('project', form.find('#project_id'), {customer_id:function(){return relation.val();}}); }
    projectSearch();
    relation.on('change.nativeSalesOriginal', function () {
      var value = relation.val(), sequence = ++relationSerial;
      if (relationRequest) relationRequest.abort();
      ['proposal_to','address','email','phone','city','state','zip'].forEach(function (name) { form.find('[name="'+name+'"]').val(''); });
      form.find('[name="country"]').selectpicker('val','');
      form.find('#project_id').empty().selectpicker('refresh');form.find('.projects-wrapper').addClass('hide');
      if (!value || !current()) return;
      relationRequest = $.getJSON(admin_url + 'proposals/get_relation_data_values/' + encodeURIComponent(value) + '/customer', root.native_sales_editor_context());
      relationRequest.done(function (data) {
        if (!current() || sequence !== relationSerial || String(relation.val()) !== String(value)) return;
        var fields = {proposal_to:'to',address:'address',email:'email',phone:'phone',city:'city',state:'state',zip:'zip'};
        Object.keys(fields).forEach(function (name) { form.find('[name="'+name+'"]').val(data[fields[name]] || ''); });
        form.find('[name="country"]').selectpicker('val', data.country || '');
        var currency = form.find('[name="currency"]');currency.selectpicker('val',data.currency || currency.data('base'));root.init_currency();
        if (data.customer_has_projects) { form.find('.projects-wrapper').removeClass('hide');projectSearch(); }
      }).fail(function (xhr, status) { if (status !== 'abort' && current() && sequence === relationSerial) notice(form,'Não foi possível carregar este cliente na empresa ativa.'); });
    });
    if (!context.native_sales_id && relation.val()) relation.trigger('change');
  };
}(window, window.jQuery));
