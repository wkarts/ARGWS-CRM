/* Official invoice list and preview behavior for a captured company context. */
(function (root, $) {
  'use strict';

  function canonicalId(value) {
    value = String(value == null ? '' : value);
    if (!/^[1-9][0-9]{0,15}$/.test(value) || Number(value) > Number.MAX_SAFE_INTEGER) {
      throw new Error('native_invoice_identity_required');
    }
    return value;
  }

  function requestKey() {
    if (!root.crypto || typeof root.crypto.getRandomValues !== 'function') {
      throw new Error('native_invoice_entropy_unavailable');
    }
    var bytes = new Uint8Array(32);
    root.crypto.getRandomValues(bytes);
    return Array.from(bytes, function (value) {
      return value.toString(16).padStart(2, '0');
    }).join('');
  }

  root.init_btn_with_tooltips = function () {
    if (!is_mobile() || navigator.userAgent.match(/iPad/i)) return false;
    $('._buttons').find('.btn-with-tooltip, .btn-with-tooltip-group').each(function () {
      var node = $(this), title = node.attr('title') || node.attr('data-title');
      if (!title) return;
      if (node.hasClass('btn-with-tooltip-group')) node.find('.btn').eq(0).append(' ' + title);
      else node.append(' ' + title);
      node.removeClass('btn-with-tooltip btn-with-tooltip-group');
    });
  };

  root.init_tabs_scrollable = function () {
    if (isRTL !== 'true') {
      if ($(root).width() <= 768) $('body').find('.toggle_view').remove();
      $('.horizontal-scrollable-tabs').horizontalTabs();
      return;
    }
    $('.arrow-left, .arrow-right').hide();
    $('.horizontal-scrollable-tabs').removeClass('horizontal-scrollable-tabs');
    $('.nav-tabs-horizontal').removeClass('nav-tabs-horizontal');
  };

  root.toggle_small_view = function (table, mainData) {
    if (!is_mobile() && $('#small-table').hasClass('hide') && $('.small-table-right-col').hasClass('col-md-12')) {
      $('#small-table').toggleClass('hide');
      $('.small-table-right-col').toggleClass('col-md-12 col-md-7');
      $(root).trigger('resize');
      return;
    }
    $('body').toggleClass('small-table');
    var wrapper = $('#small-table');
    if (!wrapper.length) return;
    var visible = false;
    if (wrapper.hasClass('col-md-5')) {
      wrapper.removeClass('col-md-5').addClass('col-md-12');
      visible = true;
      $('.toggle-small-view i').removeClass('fa fa-angle-double-right').addClass('fa fa-angle-double-left');
    } else {
      wrapper.addClass('col-md-5').removeClass('col-md-12');
      $('.toggle-small-view i').removeClass('fa fa-angle-double-left').addClass('fa fa-angle-double-right');
    }
    var api = $(table).DataTable();
    api.columns(root.hidden_columns || []).visible(visible, false);
    api.columns.adjust();
    $(mainData).toggleClass('hide');
    $(root).trigger('resize');
  };

  root.load_small_table_item = function (id, selector, inputName, url, table) {
    var stored = $('input[name="' + inputName + '"]').val();
    if (stored !== '' && !root.location.hash) {
      id = stored;
      $('input[name="' + inputName + '"]').val('');
    } else if (root.location.hash && !id) {
      id = root.location.hash.substring(1);
    }
    if (id == null || id === '') return;
    id = canonicalId(id);
    destroy_dynamic_scripts_in_element($(selector));
    if (!$('body').hasClass('small-table')) root.toggle_small_view(table, selector);
    $('input[name="' + inputName + '"]').val(id);
    if (typeof history.pushState !== 'undefined') root.location.hash = id;
    $(selector).load(admin_url + url + '/' + encodeURIComponent(id));
    var offset = $(selector).offset();
    if (offset) $('html, body').animate({scrollTop: offset.top + (is_mobile() ? 150 : 0)}, 600);
  };

  root.init_invoice = function (id) {
    root.load_small_table_item(id, '#invoice', 'invoiceid', 'invoices/get_invoice_data_ajax', '.table-invoices');
  };

  root.record_payment = function (id) {
    id = canonicalId(id);
    $('#invoice').load(admin_url + 'invoices/record_invoice_payment_ajax/' + encodeURIComponent(id));
  };

  root.small_table_full_view = function () {
    $('#small-table').toggleClass('hide');
    $('.small-table-right-col').toggleClass('col-md-12 col-md-7');
    $(root).trigger('resize');
  };

  root.native_invoice_lifecycle = function (element) {
    if (!root.NativeCompanyTransport) throw new Error('native_invoice_transport_required');
    var link = $(element), revision = link.attr('data-native-invoice-lifecycle-revision');
    if (!/^[a-f0-9]{64}$/.test(revision || '')) throw new Error('native_invoice_revision_required');
    if (link.attr('aria-busy') === 'true') return false;
    link.attr('aria-busy', 'true');
    root.NativeCompanyTransport.request(link.attr('href'), 'POST', {revision: revision}, {dataType: 'json'})
      .done(function (response) {
        if (!response || response.success !== true || typeof response.redirect !== 'string') throw new Error('native_invoice_response_invalid');
        root.NativeCompanyTransport.navigate(response.redirect);
      })
      .always(function () { link.attr('aria-busy', 'false'); });
    return false;
  };

  root.init_invoices_total = function () {
    var wrapper = $('#invoices_total');
    if (!wrapper.length) return;
    var selected = $('select[name="invoices_total_years"]').val() || [], years = [];
    $.each(selected, function (_, year) { if (year !== '') years.push(year); });
    var data = {
      currency: $('select[name="total_currency"]').val() || '',
      years: years,
      init_total: true,
      display_type: wrapper.attr('data-type')
    };
    root.NativeCompanyTransport.request('invoices/get_invoices_total', 'POST', data)
      .done(function (response) { wrapper.html(response); });
  };

  function refreshInvoice(id) {
    $('#sales_attach_file').modal('hide');
    root.init_invoice(canonicalId(id));
  }

  root.salesExtenalFileUpload = function (files, externalType) {
    if (!Array.isArray(files) || files.length < 1 || ['gdrive', 'dropbox'].indexOf(externalType) === -1) {
      throw new Error('native_invoice_external_file_invalid');
    }
    var id = canonicalId($('input[name="_attachment_sale_id"]').val());
    var chain = $.Deferred().resolve().promise();
    files.forEach(function (file) {
      chain = chain.then(function () {
        return root.NativeCompanyTransport.request('misc/add_sales_external_attachment', 'POST', {
          rel_id: id,
          type: 'invoice',
          files: [file],
          external: externalType,
          request_key: requestKey()
        }, {dataType: 'json'}).then(function (response) {
          if (!response || response.success !== true || String(response.rel_id) !== id) {
            return $.Deferred().reject(new Error('native_invoice_file_receipt_invalid')).promise();
          }
          return response;
        });
      });
    });
    return chain.done(function () { refreshInvoice(id); });
  };

  // Existing Google picker callback name uses this spelling in the official modal.
  root.salesGoogleDriveSave = function (files) { return root.salesExtenalFileUpload(files, 'gdrive'); };

  function initUpload() {
    var form = document.getElementById('sales-upload');
    if (!form || !root.Dropzone || form.dropzone) return;
    new root.Dropzone(form, appCreateDropzoneOptions({
      paramName: 'file',
      sending: function (file, xhr, body) {
        var id = canonicalId($('input[name="_attachment_sale_id"]').val());
        if (!file.nativeInvoiceRequestKey) file.nativeInvoiceRequestKey = requestKey();
        body.append('rel_id', id);
        body.append('type', 'invoice');
        body.append('request_key', file.nativeInvoiceRequestKey);
        body.append('_company_context', root.NativeCompanyHeaderConfig.context);
        body.append('_native_company_csrf', root.NativeCompanyHeaderConfig.csrf);
      },
      success: function (file, response) {
        if (typeof response === 'string') response = JSON.parse(response);
        var id = canonicalId($('input[name="_attachment_sale_id"]').val());
        if (!response || response.success !== true || String(response.rel_id) !== id || !/^[a-f0-9]{32}$/.test(response.key || '')) {
          throw new Error('native_invoice_file_receipt_invalid');
        }
        refreshInvoice(id);
      }
    }));
  }

  function initTable() {
    var table = $('table.table-invoices');
    if (!table.length) return;
    var params = {}, filters = $('._hidden_inputs._filters input');
    $.each(filters, function () { params[$(this).attr('name')] = '[name="' + $(this).attr('name') + '"]'; });
    root.table_invoices = table;
    initDataTable(table, admin_url + 'invoices/table', 'undefined', 'undefined', params, [[3, 'desc'], [0, 'desc']]);
  }

  $(function () {
    initTable();
    root.init_invoices_total();
    initUpload();
    $('#sales_attach_file').on('hidden.bs.modal', function () {
      $('#sales_uploaded_files_preview, .dz-file-preview').empty();
    });
    if (root.Dropbox && $('#dropbox-chooser-sales').length) {
      document.getElementById('dropbox-chooser-sales').appendChild(root.Dropbox.createChooseButton({
        success: function (files) { root.salesExtenalFileUpload(files, 'dropbox'); },
        linkType: 'preview',
        extensions: String(app.options.allowed_files || '').split(',')
      }));
    } else {
      $('#dropbox-chooser-sales').hide();
    }
    if (!root.gapi || !root.google) $('.gpicker[data-on-pick="salesGoogleDriveSave"]').hide();
    $('body').on('submit.nativeInvoiceNotes', '#sales-notes.invoice-notes-form', function (event) {
      event.preventDefault();
      var form = $(this), description = form.find('textarea[name="description"]').val();
      if (!description) return false;
      root.NativeCompanyTransport.request(form.attr('action'), 'POST', form.serialize())
        .done(function (id) {
          id = canonicalId(String(id).trim());
          form.find('textarea[name="description"]').val('');
          get_sales_notes(id, 'invoices');
        });
      return false;
    });
  });
}(window, window.jQuery));
