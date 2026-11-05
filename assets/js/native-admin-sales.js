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
