// Shared original native sales form source. Loaded in both principal and scoped contexts.
// Endpoint admission and issuer authorization remain server-side; this file grants no capability.
var lastAddedItemKey = null;
var billingAndShippingFields = [
  'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
  'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'
];

function native_invoice_catalog_context() {
  var form = $("#invoice-form");
  var parent = form.find('[name="_native_invoice_id"]');
  if (!parent.length) return undefined;
  return {
    native_editor: "invoice",
    native_invoice_id: parent.val(),
    native_invoice_revision: form.find('[name="_native_invoice_revision"]').val(),
  };
}

function add_item_to_preview(id) {
  var context = native_invoice_catalog_context();
  requestGetJSON("invoice_items/get_item_by_id/" + id, context ? { data: context } : undefined).done(function (
    response
  ) {
    clear_item_preview_values();

    $('.main textarea[name="description"]').val(response.description);
    $('.main textarea[name="long_description"]').val(
      response.long_description.replace(/(<|&lt;)br\s*\/*(>|&gt;)/g, " ")
    );

    _set_item_preview_custom_fields_array(response.custom_fields);

    $('.main input[name="quantity"]').val(1);

    var taxSelectedArray = [];
    if (response.taxname && response.taxrate) {
      taxSelectedArray.push(response.taxname + "|" + response.taxrate);
    }
    if (response.taxname_2 && response.taxrate_2) {
      taxSelectedArray.push(response.taxname_2 + "|" + response.taxrate_2);
    }

    $(".main select.tax").selectpicker("val", taxSelectedArray);
    $('.main input[name="unit"]').val(response.unit);

    var $currency = $("body").find(
      '.accounting-template select[name="currency"]'
    );
    var baseCurency = $currency.attr("data-base");
    var selectedCurrency = $currency.find("option:selected").val();
    var $rateInputPreview = $('.main input[name="rate"]');

    if (baseCurency == selectedCurrency) {
      $rateInputPreview.val(response.rate);
    } else {
      var itemCurrencyRate = response["rate_currency_" + selectedCurrency];
      if (!itemCurrencyRate || parseFloat(itemCurrencyRate) === 0) {
        $rateInputPreview.val(response.rate);
      } else {
        $rateInputPreview.val(itemCurrencyRate);
      }
    }

    $(document).trigger({
      type: "item-added-to-preview",
      item: response,
      item_type: "item",
    });
  });
}

function _set_item_preview_custom_fields_array(custom_fields) {
  var cf_act_as_inputs = [
    "input",
    "number",
    "date_picker",
    "date_picker_time",
    "colorpicker",
  ];

  for (var i = 0; i < custom_fields.length; i++) {
    var cf = custom_fields[i];
    if ($.inArray(cf.type, cf_act_as_inputs) > -1) {
      var f = $('tr.main td[data-id="' + cf.id + '"] input');
      // trigger change eq. for colorpicker
      f.val(cf.value).trigger("change");
    } else if (cf.type == "textarea") {
      $('tr.main td[data-id="' + cf.id + '"] textarea').val(cf.value);
    } else if (cf.type == "select" || cf.type == "multiselect") {
      if (!empty(cf.value)) {
        var selected = cf.value.split(",");
        selected = selected.map(function (e) {
          return e.trim();
        });
        $('tr.main td[data-id="' + cf.id + '"] select').selectpicker(
          "val",
          selected
        );
      }
    } else if (cf.type == "checkbox") {
      if (!empty(cf.value)) {
        var selected = cf.value.split(",");
        selected = selected.map(function (e) {
          return e.trim();
        });
        $.each(selected, function (i, e) {
          $(
            'tr.main td[data-id="' +
              cf.id +
              '"] input[type="checkbox"][value="' +
              e +
              '"]'
          ).prop("checked", true);
        });
      }
    }
  }
}

function add_task_to_preview_as_item(id) {
  requestGetJSON("tasks/get_billable_task_data/" + id).done(function (
    response
  ) {
    response.taxname = $("select.main-tax").selectpicker("val");
    var previewArea = $(".main");
    previewArea.find('textarea[name="description"]').val(response.name);
    previewArea
      .find('textarea[name="long_description"]')
      .val(response.description);
    previewArea.find('input[name="quantity"]').val(response.total_hours);
    previewArea.find('input[name="rate"]').val(response.hourly_rate);
    previewArea.find('input[name="unit"]').val("");
    $('input[name="task_id"]').val(id);
    $(document).trigger({
      type: "item-added-to-preview",
      item: response,
      item_type: "task",
    });
  });
}

function clear_item_preview_values(default_taxes) {
  // Get the last taxes applied to be available for the next item
  var last_taxes_applied = $("table.items tbody")
    .find("tr:last-child")
    .find("select")
    .selectpicker("val");
  var previewArea = $(".main");

  previewArea.find("textarea").val(""); // includes cf
  previewArea
    .find('td.custom_field input[type="checkbox"]')
    .prop("checked", false); // cf
  previewArea.find("td.custom_field input:not(:checkbox):not(:hidden)").val(""); // cf // not hidden for chkbox hidden helpers
  previewArea.find("td.custom_field select").selectpicker("val", ""); // cf
  previewArea.find('input[name="quantity"]').val(1);
  previewArea.find("select.tax").selectpicker("val", last_taxes_applied);
  previewArea.find('input[name="rate"]').val("");
  previewArea.find('input[name="unit"]').val("");
  previewArea.find('#main-optional').prop("checked", false);
  previewArea.find('#main-optional').trigger('change')
  previewArea.find('#main-optional-choosen').prop("checked", true);

  $('input[name="task_id"]').val("");
  $('input[name="expense_id"]').val("");
}

function add_item_to_table(data, itemid, merge_invoice, bill_expense) {
  // If not custom data passed get from the preview
  data =
    typeof data == "undefined" || data == "undefined"
      ? get_item_preview_values()
      : data;
  if (
    data.description === "" &&
    data.long_description === "" &&
    data.rate === ""
  ) {
    return;
  }

  var table_row = "";
  var item_key = lastAddedItemKey
    ? (lastAddedItemKey += 1)
    : $("body").find("tbody .item").length + 1;
  lastAddedItemKey = item_key;

  table_row +=
    '<tr class="sortable item" data-merge-invoice="' +
    merge_invoice +
    '" data-bill-expense="' +
    bill_expense +
    '">';

  table_row += '<td class="dragger">';

  // Check if quantity is number
  if (isNaN(data.qty)) {
    data.qty = 1;
  }

  // Check if rate is number
  if (data.rate === "" || isNaN(data.rate)) {
    data.rate = 0;
  }

  var amount = data.rate * data.qty;

  var tax_name = "newitems[" + item_key + "][taxname][]";
  $("body").append('<div class="dt-loader"></div>');
  var regex = /<br[^>]*>/gi;
  get_taxes_dropdown_template(tax_name, data.taxname).done(function (
    tax_dropdown
  ) {
    // order input
    table_row +=
      '<input type="hidden" class="order" name="newitems[' +
      item_key +
      '][order]">';

    table_row += "</td>";

    table_row +=
      '<td class="bold description"><textarea name="newitems[' +
      item_key +
      '][description]" class="form-control" rows="5">' +
      data.description +
      "</textarea>";

    table_row += '<div class="tw-mt-1.5">';
    table_row += '<div class="checkbox checkbox-info">';
    table_row += '<input class="optional-item-checkbox" ' + (data.is_optional ? ' checked' : '') + ' value="1" id="' + item_key + '-optional" type="checkbox" name="newitems[' + item_key + '][is_optional]" data-index="' + item_key + '" />';
    table_row += '<label for="' + item_key + '-optional">' + app.lang.item_is_optional + '</label>';
    table_row += '</div>';

    table_row += '<div class="checkbox" style="' + (data.is_optional ? '' : 'display: none; ') + '">';
    table_row += '<input class="optional-choose-item-checkbox" ' + (data.is_selected ? ' checked' : '') + ' value="1" id="' + item_key + '-optional-choosen" type="checkbox" name="newitems[' + item_key + '][is_selected]" />';
    table_row += '<label for="' + item_key + '-optional-choosen">' + app.lang.item_is_selected + '</label>';
    table_row += '</div>';
    table_row += '</div>';

    table_row += "</td>";

    table_row +=
      '<td><textarea name="newitems[' +
      item_key +
      '][long_description]" class="form-control item_long_description" rows="5">' +
      data.long_description.replace(regex, "\n") +
      "</textarea></td>";

    var custom_fields = $("tr.main td.custom_field");
    var cf_has_required = false;

    if (custom_fields.length > 0) {
      $.each(custom_fields, function () {
        var cf = $(this).clone();
        var cf_html = "";
        var cf_field = $(this).find("[data-fieldid]");
        var cf_name =
          "newitems[" +
          item_key +
          "][custom_fields][items][" +
          cf_field.attr("data-fieldid") +
          "]";

        if (cf_field.is(":checkbox")) {
          var checked = $(this).find('input[type="checkbox"]:checked');
          var checkboxes = cf.find('input[type="checkbox"]');

          $.each(checkboxes, function (i, e) {
            var random_key = Math.random().toString(20).slice(2);
            $(this)
              .attr("id", random_key)
              .attr("name", cf_name)
              .next("label")
              .attr("for", random_key);
            if ($(this).attr("data-custom-field-required") == "1") {
              cf_has_required = true;
            }
          });

          $.each(checked, function (i, e) {
            cf.find('input[value="' + $(e).val() + '"]').attr("checked", true);
          });

          cf_html = cf.html();
        } else if (cf_field.is("input") || cf_field.is("textarea")) {
          if (cf_field.is("input")) {
            cf.find("[data-fieldid]").attr("value", cf_field.val());
          } else {
            cf.find("[data-fieldid]").html(cf_field.val());
          }
          cf.find("[data-fieldid]").attr("name", cf_name);
          if (
            cf.find("[data-fieldid]").attr("data-custom-field-required") == "1"
          ) {
            cf_has_required = true;
          }
          cf_html = cf.html();
        } else if (cf_field.is("select")) {
          if ($(cf_field).attr("data-custom-field-required") == "1") {
            cf_has_required = true;
          }

          var selected = $(this)
            .find("select[data-fieldid]")
            .selectpicker("val");
          selected = typeof (selected != "array")
            ? new Array(selected)
            : selected;

          // Check if is multidimensional by multi-select customfield
          selected = selected[0].constructor === Array ? selected[0] : selected;

          var selectNow = cf.find("select");
          var $wrapper = $("<div/>");
          selectNow.attr("name", cf_name);

          var $select = selectNow.clone();
          $select.prop('required', 1);
          $wrapper.append($select);
          $.each(selected, function (i, e) {
            $wrapper
              .find('select option[value="' + e + '"]')
              .attr("selected", true);
          });

          cf_html = $wrapper.html();
        }
        table_row += '<td class="custom_field">' + cf_html + "</td>";
      });
    }

    table_row +=
      '<td><input type="number" min="0" onblur="calculate_total();" onchange="calculate_total();" data-quantity name="newitems[' +
      item_key +
      '][qty]" value="' +
      data.qty +
      '" class="form-control">';

    if (!data.unit || typeof data.unit == "undefined") {
      data.unit = "";
    }

    table_row +=
      '<input type="text" placeholder="' +
      app.lang.unit +
      '" name="newitems[' +
      item_key +
      '][unit]" class="form-control input-transparent text-right" value="' +
      data.unit +
      '">';

    table_row += "</td>";

    table_row +=
      '<td class="rate"><input type="number" data-toggle="tooltip" title="' +
      app.lang.item_field_not_formatted +
      '" onblur="calculate_total();" onchange="calculate_total();" name="newitems[' +
      item_key +
      '][rate]" value="' +
      data.rate +
      '" class="form-control"></td>';

    table_row += '<td class="taxrate">' + tax_dropdown + "</td>";

    table_row +=
      '<td class="amount" align="right">' +
      format_money(amount, true) +
      "</td>";

    table_row +=
      '<td><a href="#" class="btn btn-danger pull-left !tw-px-3" onclick="delete_item(this,' +
      itemid +
      '); return false;"><i class="fa fa-trash"></i></a></td>';

    table_row += "</tr>";

    $("table.items tbody").append(table_row);

    $(document).trigger({
      type: "item-added-to-table",
      data: data,
      row: table_row,
    });

    setTimeout(function () {
      calculate_total();
    }, 15);

    var billed_task = $('input[name="task_id"]').val();
    var billed_expense = $('input[name="expense_id"]').val();

    if (billed_task !== "" && typeof billed_task != "undefined") {
      billed_tasks = billed_task.split(",");
      $.each(billed_tasks, function (i, obj) {
        $("#billed-tasks").append(
          hidden_input("billed_tasks[" + item_key + "][]", obj)
        );
      });
    }

    if (billed_expense !== "" && typeof billed_expense != "undefined") {
      billed_expenses = billed_expense.split(",");
      $.each(billed_expenses, function (i, obj) {
        $("#billed-expenses").append(
          hidden_input("billed_expenses[" + item_key + "][]", obj)
        );
      });
    }

    if (
      $("#item_select").hasClass("ajax-search") &&
      $("#item_select").selectpicker("val") !== ""
    ) {
      $("#item_select").prepend("<option></option>");
    }

    init_selectpicker();
    init_datepicker();
    init_color_pickers();
    clear_item_preview_values();
    reorder_items();

    $("body").find("#items-warning").remove();
    $("body").find(".dt-loader").remove();
    $("#item_select").selectpicker("val", "");

    if (cf_has_required && $(".invoice-form").length) {
      validate_invoice_form();
    } else if (cf_has_required && $(".estimate-form").length) {
      validate_estimate_form();
    } else if (cf_has_required && $(".proposal-form").length) {
      validate_proposal_form();
    } else if (cf_has_required && $(".credit-note-form").length) {
      validate_credit_note_form();
    }

    if (bill_expense == "undefined" || !bill_expense) {
      $('select[name="task_select"]')
        .find('[value="' + billed_task + '"]')
        .remove();
      $('select[name="task_select"]').selectpicker("refresh");
    }
    return true;
  });

  return false;
}

function get_taxes_dropdown_template(name, taxname) {
  return $.post(admin_url + "misc/get_taxes_dropdown_template", {
    name: name,
    taxname: taxname,
  });
}

function init_ajax_project_search_by_customer_id(selector) {
  selector =
    typeof selector == "undefined" ? "#project_id.ajax-search" : selector;
  init_ajax_search("project", selector, {
    customer_id: function () {
      return $("#clientid").val();
    },
  });
}

function init_ajax_projects_search(selector) {
  selector =
    typeof selector == "undefined" ? "#project_id.ajax-search" : selector;
  init_ajax_search("project", selector);
}

function init_items_sortable(preview_table) {
  var _items_sortable = $("#wrapper").find(".items tbody");

  if (_items_sortable.length === 0) {
    return;
  }
  _items_sortable.sortable({
    helper: fixHelperTableHelperSortable,
    handle: ".dragger",
    placeholder: "ui-placeholder",
    itemPath: "> tbody",
    itemSelector: "tr.sortable",
    items: "tr.sortable",
    update: function () {
      if (typeof preview_table == "undefined") {
        reorder_items();
      } else {
        // If passed from the admin preview there is other function for re-ordering
        save_ei_items_order();
      }
    },
    sort: function (event, ui) {
      // Firefox fixer when dragging
      var $target = $(event.target);
      if (!/html|body/i.test($target.offsetParent()[0].tagName)) {
        var top =
          event.pageY -
          $target.offsetParent().offset().top -
          ui.helper.outerHeight(true) / 2;
        ui.helper.css({
          top: top + "px",
        });
      }
    },
  });
}

function reorder_items() {
  var rows = $(".table.has-calculations tbody tr.item");
  var i = 1;
  $.each(rows, function () {
    $(this).find("input.order").val(i);
    i++;
  });
}

function get_item_preview_values() {
  var response = {};
  response.description = $('.main textarea[name="description"]').val();
  response.long_description = $(
    '.main textarea[name="long_description"]'
  ).val();
  response.qty = $('.main input[name="quantity"]').val();
  response.taxname = $(".main select.tax").selectpicker("val");
  response.rate = $('.main input[name="rate"]').val();
  response.unit = $('.main input[name="unit"]').val();
  response.is_optional = $('#main-optional').prop('checked') ? 1 : 0;
  response.is_selected = $('#main-optional-choosen').prop('checked') ? 1 : 0;
  return response;
}

function calculate_total() {
  if ($("body").hasClass("no-calculate-total")) {
    return false;
  }

  var calculated_tax,
    taxrate,
    item_taxes,
    row,
    _amount,
    _tax_name,
    taxes = {},
    taxes_rows = [],
    subtotal = 0,
    total = 0,
    quantity = 1,
    total_discount_calculated = 0,
    rows = $(".table.has-calculations tbody tr.item"),
    discount_area = $("#discount_area"),
    adjustment = $('input[name="adjustment"]').val(),
    discount_percent = $('input[name="discount_percent"]').val(),
    discount_fixed = $('input[name="discount_total"]').val(),
    discount_total_type = $(".discount-total-type.selected"),
    discount_type = $('select[name="discount_type"]').val();

  $(".tax-area").remove();

  $.each(rows, function () {
    quantity = $(this).find("[data-quantity]").val();
    if (quantity === "") {
      quantity = 1;
      $(this).find("[data-quantity]").val(1);
    }

    // Check if item is optional and not chosen - skip calculation if so
    var isOptional = $(this).find('.optional-item-checkbox').prop('checked');
    var isChosen = $(this).find('.optional-choose-item-checkbox').prop('checked');

    _amount = accounting.toFixed(
      $(this).find("td.rate input").val() * quantity,
      app.options.decimal_places
    );
    _amount = parseFloat(_amount);

    $(this).find("td.amount").html(format_money(_amount, true));

    if (isOptional && !isChosen) {
      return true; // skip to next iteration
    }
   
    subtotal += _amount;
    row = $(this);
    item_taxes = $(this).find("select.tax").selectpicker("val");

    if (item_taxes) {
      $.each(item_taxes, function (i, taxname) {
        taxrate = row
          .find('select.tax [value="' + taxname + '"]')
          .data("taxrate");
        calculated_tax = (_amount / 100) * taxrate;
        if (!taxes.hasOwnProperty(taxname)) {
          if (taxrate != 0) {
            _tax_name = taxname.split("|");
            tax_row =
              '<tr class="tax-area"><td>' +
              _tax_name[0] +
              "(" +
              taxrate +
              '%)</td><td id="tax_id_' +
              slugify(taxname) +
              '"></td></tr>';
            $(discount_area).after(tax_row);
            taxes[taxname] = calculated_tax;
          }
        } else {
          // Increment total from this tax
          taxes[taxname] = taxes[taxname] += calculated_tax;
        }
      });
    }
  });

  // Discount by percent
  if (
    discount_percent !== "" &&
    discount_percent != 0 &&
    discount_type == "before_tax" &&
    discount_total_type.hasClass("discount-type-percent")
  ) {
    total_discount_calculated = (subtotal * discount_percent) / 100;
  } else if (
    discount_fixed !== "" &&
    discount_fixed != 0 &&
    discount_type == "before_tax" &&
    discount_total_type.hasClass("discount-type-fixed")
  ) {
    total_discount_calculated = discount_fixed;
  }

  $.each(taxes, function (taxname, total_tax) {
    if (
      discount_percent !== "" &&
      discount_percent != 0 &&
      discount_type == "before_tax" &&
      discount_total_type.hasClass("discount-type-percent")
    ) {
      total_tax_calculated = (total_tax * discount_percent) / 100;
      total_tax = total_tax - total_tax_calculated;
    } else if (
      discount_fixed !== "" &&
      discount_fixed != 0 &&
      discount_type == "before_tax" &&
      discount_total_type.hasClass("discount-type-fixed")
    ) {
      var t = (discount_fixed / subtotal) * 100;
      total_tax = total_tax - (total_tax * t) / 100;
    }

    total += total_tax;
    total_tax = format_money(total_tax);
    $("#tax_id_" + slugify(taxname)).html(total_tax);
  });

  total = total + subtotal;

  // Discount by percent
  if (
    discount_percent !== "" &&
    discount_percent != 0 &&
    discount_type == "after_tax" &&
    discount_total_type.hasClass("discount-type-percent")
  ) {
    total_discount_calculated = (total * discount_percent) / 100;
  } else if (
    discount_fixed !== "" &&
    discount_fixed != 0 &&
    discount_type == "after_tax" &&
    discount_total_type.hasClass("discount-type-fixed")
  ) {
    total_discount_calculated = discount_fixed;
  }

  total = total - total_discount_calculated;
  adjustment = parseFloat(adjustment);

  // Check if adjustment not empty
  if (!isNaN(adjustment)) {
    total = total + adjustment;
  }

  var discount_html = "-" + format_money(total_discount_calculated);
  $('input[name="discount_total"]').val(
    accounting.toFixed(total_discount_calculated, app.options.decimal_places)
  );

  // Append, format to html and display
  $(".discount-total").html(discount_html);
  $(".adjustment").html(format_money(adjustment));
  $(".subtotal").html(
    format_money(subtotal) +
      hidden_input(
        "subtotal",
        accounting.toFixed(subtotal, app.options.decimal_places)
      )
  );
  $(".total").html(
    format_money(total) +
      hidden_input(
        "total",
        accounting.toFixed(total, app.options.decimal_places)
      )
  );

  $(document).trigger("sales-total-calculated");
}

function exclude_tax_from_amount(tax_percent, total_amount) {
  totalTax = accounting.toFixed(
    (total_amount * tax_percent) / (100 + tax_percent),
    app.options.decimal_places
  );
  return accounting.toFixed(
    total_amount - totalTax,
    app.options.decimal_places
  );
}

function delete_item(row, itemid) {
  $(row)
    .parents("tr")
    .addClass("animated fadeOut", function () {
      setTimeout(function () {
        $(row).parents("tr").remove();
        calculate_total();
      }, 50);
    });

  // If is edit we need to add to input removed_items to track activity
  if (itemid && $('input[name="isedit"]').length > 0) {
    $("#removed-items").append(hidden_input("removed_items[]", itemid));
  }
}

function format_money(total, excludeSymbol) {
  if (typeof excludeSymbol != "undefined" && excludeSymbol) {
    return accounting.formatMoney(total, {
      symbol: "",
    });
  }

  return accounting.formatMoney(total);
}

function init_currency(id, callback) {
  var $accountingTemplate = $("body").find(".accounting-template");

  if ($accountingTemplate.length || id) {
    var selectedCurrencyId = !id
      ? $accountingTemplate.find('select[name="currency"]').val()
      : id;

    requestGetJSON("misc/get_currency/" + selectedCurrencyId).done(function (
      currency
    ) {
      // Used for formatting money
      accounting.settings.currency.decimal = currency.decimal_separator;
      accounting.settings.currency.thousand = currency.thousand_separator;
      accounting.settings.currency.symbol = currency.symbol;
      accounting.settings.currency.format =
        currency.placement == "after" ? "%v %s" : "%s%v";
      calculate_total();

      if (callback) {
        callback();
      }
    });
  }
}

function init_currency_symbol() {
  console.warn(
    '"init_currency_symbol" is deprecated, use "init_currency" instead'
  );
  init_currency();
}

function validate_invoice_form(selector) {
  selector = typeof selector == "undefined" ? "#invoice-form" : selector;

  appValidateForm($(selector), {
    clientid: {
      required: {
        depends: function () {
          var customerRemoved =
            $("select#clientid").hasClass("customer-removed");
          return !customerRemoved;
        },
      },
    },
    date: "required",
    currency: "required",
    repeat_every_custom: {
      min: 1,
    },
    number: {
      required: true,
    },
  });
  var numberValidationData = {
          number: function () {
            return $('input[name="number"]').val();
          },
          isedit: function () {
            return $('input[name="number"]').data("isedit");
          },
          original_number: function () {
            return $('input[name="number"]').data("original-number");
          },
          date: function () {
            return $('input[name="date"]').val();
          },
        };
  var nativeParent = $(selector).find('[name="_native_invoice_id"]');
  if (nativeParent.length) {
    numberValidationData.invoice_id = function () { return nativeParent.val(); };
    numberValidationData.revision = function () { return $(selector).find('[name="_native_invoice_revision"]').val(); };
  }
  $("body")
    .find('input[name="number"]')
    .rules("add", {
      remote: {
        url: admin_url + "invoices/validate_invoice_number",
        type: "post",
        data: numberValidationData,
      },
      messages: {
        remote: app.lang.invoice_number_exists,
      },
    });
}

function _init_tasks_billable_select(tasks, project_id) {
  var billable_tasks_area = $("#task_select");
  if (billable_tasks_area.length > 0) {
    var option_data;
    billable_tasks_area
      .find("option")
      .filter(function () {
        return (
          this.value ||
          $.trim(this.value).length > 0 ||
          $.trim(this.text).length > 0
        );
      })
      .remove();

    $.each(tasks, function (i, obj) {
      option_data = " ";
      if (obj.started_timers === true) {
        option_data +=
          'disabled class="text-danger" data-subtext="' +
          app.lang.invoice_task_billable_timers_found +
          '"';
      } else if (obj.started_timers === false && obj.rel_type != "project") {
        option_data += 'data-subtext="' + obj.rel_name + '"';
      }
      billable_tasks_area.append(
        '<option value="' +
          obj.id +
          '"' +
          option_data +
          ">" +
          obj.name +
          "</option>"
      );
    });

    var tasks_help_wrapper = $(".input-group-addon-bill-tasks-help");
    tasks_help_wrapper.find(".popover-invoker").popover("destroy");
    tasks_help_wrapper.empty();

    var help_tooltip = "";

    if (!empty(project_id)) {
      help_tooltip =
        app.lang["showing_billable_tasks_from_project"] +
        " " +
        $("#project_id option:selected").text().trim();
    } else {
      help_tooltip = app.lang["invoice_task_item_project_tasks_not_included"];
    }

    tasks_help_wrapper.html(
      '<span class="pointer popover-invoker" data-container=".form-group-select-task_select" data-trigger="click" data-placement="top" data-toggle="popover" data-content="' +
        help_tooltip +
        '"><i class="fa-regular fa-circle-question"></i></span>'
    );

    delay(function () {
      if (
        (tasks_help_wrapper.attr("info-shown-count") < 3 ||
          typeof tasks_help_wrapper.attr("info-shown-count") == "undefined") &&
        $(".projects-wrapper").is(":visible") &&
        tasks.length > 0
      ) {
        tasks_help_wrapper.attr(
          "info-shown-count",
          typeof tasks_help_wrapper.attr("info-shown-count") == "undefined"
            ? 1
            : parseInt(tasks_help_wrapper.attr("info-shown-count")) + 1
        );
        tasks_help_wrapper.find(".popover-invoker").click();
      }
    }, 3500);
  }

  billable_tasks_area.selectpicker("refresh");
}

function clear_billing_and_shipping_details() {
  for (var f in billingAndShippingFields) {
    if (billingAndShippingFields[f].indexOf("country") > -1) {
      $('select[name="' + billingAndShippingFields[f] + '"]').selectpicker(
        "val",
        ""
      );
    } else {
      $('input[name="' + billingAndShippingFields[f] + '"]').val("");
      $('textarea[name="' + billingAndShippingFields[f] + '"]').val("");
    }
    if (billingAndShippingFields[f] == "billing_country") {
      $('input[name="include_shipping"]').prop("checked", false);
      $('input[name="include_shipping"]').change();
    }
  }

  init_billing_and_shipping_details();
}

function init_billing_and_shipping_details() {
  var _f;
  var include_shipping = $('input[name="include_shipping"]').prop("checked");

  for (var f in billingAndShippingFields) {
    _f = "";
    if (billingAndShippingFields[f].indexOf("country") > -1) {
      _f = $("#" + billingAndShippingFields[f] + " option:selected").data(
        "subtext"
      );
    } else if (
      billingAndShippingFields[f].indexOf("shipping_street") > -1 ||
      billingAndShippingFields[f].indexOf("billing_street") > -1
    ) {
      if ($('textarea[name="' + billingAndShippingFields[f] + '"]').length) {
        _f = $('textarea[name="' + billingAndShippingFields[f] + '"]')
          .val()
          .replace(/(?:\r\n|\r|\n)/g, "<br />");
      }
    } else {
      _f = $('input[name="' + billingAndShippingFields[f] + '"]').val();
    }
    if (billingAndShippingFields[f].indexOf("shipping") > -1) {
      if (!include_shipping) {
        _f = "";
      }
    }
    if (typeof _f == "undefined") {
      _f = "";
    }
    _f = _f !== "" ? _f : "--";
    $("." + billingAndShippingFields[f]).html(_f);
  }
  $("#billing_and_shipping_details").modal("hide");
}

$(function () {
  init_ajax_search("customer", "#clientid.ajax-search");

  init_items_sortable();

  $("body").on("change", "#unlimited_cycles", function () {
    $(this)
      .parents(".recurring-cycles")
      .find("#cycles")
      .prop("disabled", $(this).prop("checked"));
  });

  // For expenses and recurring tasks
  $("body").on(
    "change",
    '[name="repeat_every"], [name="recurring"]',
    function () {
      var val = $(this).val();
      val == "custom"
        ? $(".recurring_custom").removeClass("hide")
        : $(".recurring_custom").addClass("hide");
      if (val !== "" && val != 0) {
        $("body").find("#cycles_wrapper").removeClass("hide");
      } else {
        $("body").find("#cycles_wrapper").addClass("hide");
        $("body").find("#cycles_wrapper #cycles").val(0);
        $("#unlimited_cycles").prop("checked", true).change();
      }
    }
  );


  $("body").on("submit", "._transaction_form", function () {
    // On submit re-calculate total and reorder the items for all cases.
    calculate_total();

    $("body").find("#items-warning").remove();
    var $itemsTable = $(this).find("table.items");
    var $previewItem = $itemsTable.find(".main");

    if (
      $previewItem.find('[name="description"]').length &&
      $previewItem.find('[name="description"]').val().trim().length > 0 &&
      $previewItem.find('[name="rate"]').val().trim().length > 0
    ) {
      $itemsTable.before(
        '<div class="alert alert-warning mbot20" id="items-warning">' +
          app.lang.item_forgotten_in_preview +
          '<i class="fa fa-angle-double-down pointer pull-right fa-2x" style="margin-top:-4px;" onclick="add_item_to_table(\'undefined\',\'undefined\',undefined); return false;"></i></div>'
      );

      $("html,body").animate({
        scrollTop: $("#items-warning").offset().top,
      });

      return false;
    } else {
      if ($itemsTable.length && $itemsTable.find(".item").length === 0) {
        $itemsTable.before(
          '<div class="alert alert-warning mbot20" id="items-warning">' +
            app.lang.no_items_warning +
            "</div>"
        );
        $("html,body").animate({
          scrollTop: $("#items-warning").offset().top,
        });
        return false;
      }
    }

    reorder_items();

    // Remove the disabled attribute from the disabled fields becuase if they are disabled won't be sent with the request.
    $('select[name="currency"]').prop("disabled", false);
    $('select[name="project_id"]').prop("disabled", false);
    $('input[name="date"]').prop("disabled", false);

    // Add disabled to submit buttons
    $(this).find(".transaction-submit").prop("disabled", true);

    return true;
  });

  $("body").on("click", ".transaction-submit", function () {
    var that = $(this);
    var form = that.parents("form._transaction_form");
    if (form.valid()) {
      if (that.hasClass("save-as-draft")) {
        form.append(hidden_input("save_as_draft", "true"));
      } else if (that.hasClass("save-and-send")) {
        form.append(hidden_input("save_and_send", "true"));
      } else if (that.hasClass("save-and-record-payment")) {
        form.append(hidden_input("save_and_record_payment", "true"));
      } else if (that.hasClass("save-and-send-later")) {
        form.append(hidden_input("save_and_send_later", "true"));
      }
    }
    form.submit();
  });


  // Include shipping show/hide details
  $("body").on("change", "#include_shipping", function () {
    var $sd = $("#shipping_details");
    $(this).prop("checked") === true
      ? $sd.removeClass("hide")
      : $sd.addClass("hide");
  });

  // Init the billing and shipping details in the field - estimates and invoices
  $("body").on("click", ".save-shipping-billing", function (e) {
    init_billing_and_shipping_details();
  });

  // On change currency recalculate price and change symbol
  $("body").on("change", 'select[name="currency"]', function () {
    init_currency();
  });

  // Recaulciate total on these changes
  $("body").on("change", 'input[name="adjustment"],select.tax', function () {
    calculate_total();
  });

  $("body").on("click", ".discount-total-type", function (e) {
    e.preventDefault();
    $("#discount-total-type-dropdown")
      .find(".discount-total-type")
      .removeClass("selected");
    $(this).addClass("selected");
    $(".discount-total-type-selected").html($(this).text());
    if ($(this).hasClass("discount-type-percent")) {
      $(".input-discount-fixed").addClass("hide").val(0);
      $(".input-discount-percent").removeClass("hide");
    } else {
      $(".input-discount-fixed").removeClass("hide");
      $(".input-discount-percent").addClass("hide").val(0);
      $("#discount_percent-error").remove();
    }
    calculate_total();
  });

  // Discount type for estimate/invoice
  $("body").on("change", 'select[name="discount_type"]', function () {
    // if discount_type == ''
    if ($(this).val() === "") {
      $('input[name="discount_percent"]').val(0);
    }
    // Recalculate the total
    calculate_total();
  });

  // In case user enter discount percent but there is no discount type set
  $("body").on(
    "change",
    'input[name="discount_percent"],input[name="discount_total"]',
    function () {
      if (
        $('select[name="discount_type"]').val() === "" &&
        $(this).val() != 0
      ) {
        if ($("td[id^='tax_id_']").length === 0) {
          return;
        }
        alert("You need to select discount type");
        $("html,body").animate(
          {
            scrollTop: 0,
          },
          "slow"
        );
        $("#wrapper").highlight($('label[for="discount_type"]').text());
        setTimeout(function () {
          $("#wrapper").unhighlight();
        }, 3000);
        return false;
      }
      if ($(this).valid() === true) {
        calculate_total();
      }
    }
  );

  $("body").on("change", ".invoice #project_id", function () {
    var project_id = $(this).selectpicker("val");
    if (project_id !== "") {
      requestGetJSON("tasks/get_billable_tasks_by_project/" + project_id).done(
        function (tasks) {
          _init_tasks_billable_select(tasks, project_id);
        }
      );
    } else {
      var client_id = $("#clientid").selectpicker("val");
      if (client_id !== "") {
        requestGetJSON(
          "tasks/get_billable_tasks_by_customer_id/" + client_id
        ).done(function (tasks) {
          _init_tasks_billable_select(tasks);
        });
      } else {
        // Empty dropdown
        _init_tasks_billable_select([], "");
      }
    }
  });

  // Add task data to preview from the dropdown for invoiecs
  $("body").on("change", 'select[name="task_select"]', function () {
    $(this).selectpicker("val") !== ""
      ? add_task_to_preview_as_item($(this).selectpicker("val"))
      : "";
  });

  // When user record payment check if is online mode
  $("body").on("change", 'select[name="paymentmode"]', function () {
    var $notRedirect = $(".do_not_redirect");
    !$.isNumeric($(this).val())
      ? $notRedirect.removeClass("hide")
      : $notRedirect.addClass("hide");
  });

  $("body").on("change", ".f_client_id #clientid", function () {
    var val = $(this).val();
    var projectAjax = $('select[name="project_id"]');
    var clonedProjectsAjaxSearchSelect = projectAjax.html("").clone();
    var projectsWrapper = $(".projects-wrapper");
    projectAjax.selectpicker("destroy").remove();
    projectAjax = clonedProjectsAjaxSearchSelect;
    $("#project_ajax_search_wrapper").append(clonedProjectsAjaxSearchSelect);
    init_ajax_project_search_by_customer_id();
    clear_billing_and_shipping_details();
    if (!val) {
      $("#merge").empty();
      $("#expenses_to_bill").empty();
      $("#invoice_top_info").addClass("hide");
      projectsWrapper.addClass("hide");
      return false;
    }

    var currentInvoiceID = $("body")
      .find('input[name="merge_current_invoice"]')
      .val();
    currentInvoiceID =
      typeof currentInvoiceID == "undefined" ? "" : currentInvoiceID;

    requestGetJSON(
      "invoices/client_change_data/" + val + "/" + currentInvoiceID
    ).done(function (response) {
      $("#merge").html(response.merge_info);
      var $billExpenses = $("#expenses_to_bill");
      // Invoice from project, in invoice_template this is not shown
      $billExpenses.length === 0
        ? (response.expenses_bill_info = "")
        : $billExpenses.html(response.expenses_bill_info);
      response.merge_info !== "" || response.expenses_bill_info !== ""
        ? $("#invoice_top_info").removeClass("hide")
        : $("#invoice_top_info").addClass("hide");

      for (var f in billingAndShippingFields) {
        if (billingAndShippingFields[f].indexOf("billing") > -1) {
          if (billingAndShippingFields[f].indexOf("country") > -1) {
            $(
              'select[name="' + billingAndShippingFields[f] + '"]'
            ).selectpicker(
              "val",
              response["billing_shipping"][0][billingAndShippingFields[f]]
            );
          } else {
            if (billingAndShippingFields[f].indexOf("billing_street") > -1) {
              $('textarea[name="' + billingAndShippingFields[f] + '"]').val(
                response["billing_shipping"][0][billingAndShippingFields[f]]
              );
            } else {
              $('input[name="' + billingAndShippingFields[f] + '"]').val(
                response["billing_shipping"][0][billingAndShippingFields[f]]
              );
            }
          }
        }
      }

      if (!empty(response["billing_shipping"][0]["shipping_street"])) {
        $('input[name="include_shipping"]').prop("checked", true).change();
      }

      for (var fsd in billingAndShippingFields) {
        if (billingAndShippingFields[fsd].indexOf("shipping") > -1) {
          if (billingAndShippingFields[fsd].indexOf("country") > -1) {
            $(
              'select[name="' + billingAndShippingFields[fsd] + '"]'
            ).selectpicker(
              "val",
              response["billing_shipping"][0][billingAndShippingFields[fsd]]
            );
          } else {
            if (billingAndShippingFields[fsd].indexOf("shipping_street") > -1) {
              $('textarea[name="' + billingAndShippingFields[fsd] + '"]').val(
                response["billing_shipping"][0][billingAndShippingFields[fsd]]
              );
            } else {
              $('input[name="' + billingAndShippingFields[fsd] + '"]').val(
                response["billing_shipping"][0][billingAndShippingFields[fsd]]
              );
            }
          }
        }
      }

      init_billing_and_shipping_details();

      var client_currency = response["client_currency"];
      var s_currency = $("body").find(
        '.accounting-template select[name="currency"]'
      );
      client_currency = parseInt(client_currency);
      client_currency != 0
        ? s_currency.val(client_currency)
        : s_currency.val(s_currency.data("base"));
      _init_tasks_billable_select(
        response["billable_tasks"],
        projectAjax.selectpicker("val")
      );
      response.customer_has_projects === true
        ? projectsWrapper.removeClass("hide")
        : projectsWrapper.addClass("hide");
      s_currency.selectpicker("refresh");
      init_currency();
    });
  });

  // When customer_id is passed init the data
  if ($("body").find('input[name="isedit"]').length === 0) {
    $('.f_client_id select[name="clientid"]').change();
  }

  $("body").on("click", "#get_shipping_from_customer_profile", function (e) {
    e.preventDefault();
    var include_shipping = $("#include_shipping");
    if (include_shipping.prop("checked") === false) {
      include_shipping.prop("checked", true);
      $("#shipping_details").removeClass("hide");
    }
    var clientid = $("#clientid").val();
    if (clientid === "") {
      return;
    }
    requestGetJSON(
      "clients/get_customer_billing_and_shipping_details/" + clientid
    ).done(function (response) {
      $('textarea[name="shipping_street"]').val(response[0]["shipping_street"]);
      $('input[name="shipping_city"]').val(response[0]["shipping_city"]);
      $('input[name="shipping_state"]').val(response[0]["shipping_state"]);
      $('input[name="shipping_zip"]').val(response[0]["shipping_zip"]);
      $('select[name="shipping_country"]').selectpicker(
        "val",
        response[0]["shipping_country"]
      );
    });
  });

  if (typeof accounting != "undefined") {
    // For currency
    accounting.settings.currency.precision = app.options.decimal_places;

    // Used for numbers
    accounting.settings.number.thousand = app.options.thousand_separator;
    accounting.settings.number.decimal = app.options.decimal_separator;
    accounting.settings.number.precision = app.options.decimal_places;

    calculate_total();
  }

  // Invoices to merge
  $("body").on("change", 'input[name="invoices_to_merge[]"]', function () {
    var checked = $(this).prop("checked");
    var _id = $(this).val();
    if (checked === true) {
      requestGetJSON("invoices/get_merge_data/" + _id).done(function (
        response
      ) {
        $.each(response.items, function (i, obj) {
          if (obj.rel_type !== "") {
            if (obj.rel_type == "task") {
              $('input[name="task_id"]').val(
                obj.item_related_formatted_for_input
              );
            } else if (obj.rel_type == "expense") {
              $('input[name="expense_id"]').val(
                obj.item_related_formatted_for_input
              );
            }
          }
          _set_item_preview_custom_fields_array(obj.custom_fields);
          add_item_to_table(obj, "undefined", _id);
        });
      });
    } else {
      // Remove the appended invoice to merge
      $("body")
        .find('[data-merge-invoice="' + _id + '"]')
        .remove();
    }
  });

  // Bill expenses to invooice on top
  $("body").on("change", 'input[name="bill_expenses[]"]', function () {
    var checked = $(this).prop("checked");
    var _id = $(this).val();
    if (checked === true) {
      requestGetJSON("invoices/get_bill_expense_data/" + _id).done(function (
        response
      ) {
        $('input[name="expense_id"]').val(_id);
        add_item_to_table(response, "undefined", "undefined", _id);
      });
    } else {
      // Remove the appended expenses
      $("body")
        .find('[data-bill-expense="' + _id + '"]')
        .remove();
      $("body")
        .find('#billed-expenses input[value="' + _id + '"]')
        .remove();
    }
  });

  // Expense bill to popover data
  $("body").on(
    "change",
    ".invoice_inc_expense_additional_info input",
    function () {
      var _data_content = $(this).attr("data-content"),
        new_desc_value,
        desc_selector = $(
          "[data-bill-expense=" +
            $(this).attr("data-id") +
            "] .item_long_description"
        );
      current_desc_val = desc_selector.val();
      current_desc_val = current_desc_val.trim();
      if (_data_content !== "") {
        if ($(this).prop("checked") === true) {
          new_desc_value = current_desc_val + "\n" + _data_content;
          desc_selector.val(new_desc_value.trim());
        } else {
          desc_selector.val(current_desc_val.replace("\n" + _data_content, ""));
          // IN case there is no new line
          desc_selector.val(current_desc_val.replace(_data_content, ""));
        }
      }
    }
  );
});
