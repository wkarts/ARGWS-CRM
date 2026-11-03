/* Native table and kanban functions extracted from main.js; no data bootstrap. */
$.fn.dataTable.ext.errMode = "throw";

$.fn.dataTableExt.oStdClasses.sWrapper =
  "dataTables_wrapper form-inline dt-bootstrap table-loading";

function mainWrapperHeightFix() { return NativeAdminNavigation.height({isMobile:is_mobile,isRTL:isRTL}); }

function initDataTableInline(dt_table) {
  appDataTableInline(dt_table, {
    supportsButtons: true,
    supportsLoading: true,
    autoWidth: false,
  });
}

function initDataTable(
  selector,
  url,
  notsearchable,
  notsortable,
  fnserverparams,
  defaultorder
) {
  var table =
    typeof selector == "string" ? $("body").find("table" + selector) : selector;

  if (table.length === 0) {
    return false;
  }

  fnserverparams =
    fnserverparams == "undefined" || typeof fnserverparams == "undefined"
      ? []
      : fnserverparams;

  // If not order is passed order by the first column
  if (typeof defaultorder == "undefined") {
    defaultorder = [[0, "asc"]];
  } else {
    if (defaultorder.length === 1) {
      defaultorder = [defaultorder];
    }
  }

  var user_table_default_order = table.attr("data-default-order");

  if (!empty(user_table_default_order)) {
    var tmp_new_default_order = JSON.parse(user_table_default_order);
    var new_defaultorder = [];
    for (var i in tmp_new_default_order) {
      // If the order index do not exists will throw errors
      if (
        table.find("thead th:eq(" + tmp_new_default_order[i][0] + ")").length >
        0
      ) {
        new_defaultorder.push(tmp_new_default_order[i]);
      }
    }
    if (new_defaultorder.length > 0) {
      defaultorder = new_defaultorder;
    }
  }

  var length_options = [10, 25, 50, 100];
  var length_options_names = [10, 25, 50, 100];

  app.options.tables_pagination_limit = parseFloat(
    app.options.tables_pagination_limit
  );

  if ($.inArray(app.options.tables_pagination_limit, length_options) == -1) {
    length_options.push(app.options.tables_pagination_limit);
    length_options_names.push(app.options.tables_pagination_limit);
  }

  length_options.sort(function (a, b) {
    return a - b;
  });
  length_options_names.sort(function (a, b) {
    return a - b;
  });

  length_options.push(-1);
  length_options_names.push(app.lang.dt_length_menu_all);

  var dtSettings = {
    language: app.lang.datatables,
    processing: true,
    retrieve: true,
    serverSide: true,
    paginate: true,
    searchDelay: 750,
    bDeferRender: true,
    autoWidth: false,
    dom: "<'row'><'row'<'col-md-7'lB><'col-md-5'f>>rt<'row'<'col-md-4'i><'col-md-8 dataTables_paging'<'#colvis'><'.dt-page-jump'>p>>",
    pageLength: app.options.tables_pagination_limit,
    lengthMenu: [length_options, length_options_names],
    columnDefs: [
      {
        searchable: false,
        targets: notsearchable,
      },
      {
        sortable: false,
        targets: notsortable,
      },
    ],
    fnDrawCallback: function (oSettings) {
      _table_jump_to_page(this, oSettings);
      if (oSettings.aoData.length === 0) {
        $(oSettings.nTableWrapper).addClass("app_dt_empty");
      } else {
        $(oSettings.nTableWrapper).removeClass("app_dt_empty");
      }
    },
    fnCreatedRow: function (nRow, aData, iDataIndex) {
      // If tooltips found
      $(nRow).attr("data-title", aData.Data_Title);
      $(nRow).attr("data-toggle", aData.Data_Toggle);
    },
    initComplete: function (settings, json) {
      var t = this;
      var $btnReload = $(".btn-dt-reload");
      $btnReload.attr("data-toggle", "tooltip");
      $btnReload.attr("title", app.lang.dt_button_reload);

      var $btnColVis = $(".dt-column-visibility");
      $btnColVis.attr("data-toggle", "tooltip");
      $btnColVis.attr("title", app.lang.dt_button_column_visibility);

      t.wrap('<div class="table-responsive"></div>');

      var dtEmpty = t.find(".dataTables_empty");
      if (dtEmpty.length) {
        dtEmpty.attr("colspan", t.find("thead th").length);
      }

      // Hide mass selection because causing issue on small devices
      if (
        is_mobile() &&
        $(window).width() < 400 &&
        t.find('tbody td:first-child input[type="checkbox"]').length > 0
      ) {
        t.DataTable().column(0).visible(false, false).columns.adjust();
        $("a[data-target*='bulk_actions']").addClass("hide");
      }

      t.parents(".table-loading").removeClass("table-loading");
      t.removeClass("dt-table-loading");
      var th_last_child = t.find("thead th:last-child");
      var th_first_child = t.find("thead th:first-child");
      if (th_last_child.text().trim() == app.lang.options) {
        th_last_child.addClass("not-export");
      }
      if (th_first_child.find('input[type="checkbox"]').length > 0) {
        th_first_child.addClass("not-export");
      }
      mainWrapperHeightFix();
    },
    order: defaultorder,
    ajax: {
      url: url,
      type: "POST",
      data: function (d) {
        if (Array.isArray(d.order)) {

          d.order = d.order.map(function (order) {
            var tHead = table.find("thead th:eq(" + order.column + ")");
            if (tHead.length > 0) {
              if (tHead[0].dataset.customField == 1) {
                order.type = tHead[0].dataset.type;
              }
            }
            return order;
          });
        }

        if (typeof csrfData !== "undefined") {
          d[csrfData["token_name"]] = csrfData["hash"];
        }

        for (var key in fnserverparams) {
          d[key] = $(fnserverparams[key]).val();
        }

        if (table.attr("data-last-order-identifier")) {
          d["last_order_identifier"] = table.attr("data-last-order-identifier");
        }

        var tId = table[0].getAttribute('id');

        if(tId && Object.hasOwn(app.dtFilters, tId)) {
          d['filters'] = app.dtFilters[tId]
        }
      },
    },
    buttons: get_datatable_buttons(table),
  };

  table = table.dataTable(dtSettings);
  var tableApi = table.DataTable();

  var hiddenHeadings = table.find("th.not_visible");
  var hiddenIndexes = [];

  $.each(hiddenHeadings, function () {
    hiddenIndexes.push(this.cellIndex);
  });

  setTimeout(function () {
    for (var i in hiddenIndexes) {
      tableApi.columns(hiddenIndexes[i]).visible(false, false).columns.adjust();
    }
  }, 10);

  if (table.hasClass("customizable-table")) {
    var tableToggleAbleHeadings = table.find("th.toggleable");
    var invisible = $("#hidden-columns-" + table.attr("id"));
    try {
      invisible = JSON.parse(invisible.text());
    } catch (err) {
      invisible = [];
    }

    $.each(tableToggleAbleHeadings, function () {
      var cID = $(this).attr("id");
      if ($.inArray(cID, invisible) > -1) {
        tableApi.column("#" + cID).visible(false);
      }
    });

    // For for not blurring out when clicked on the link
    // Causing issues hidden column still to be shown as not hidden because the link is focused
    /* $('body').on('click', '.buttons-columnVisibility a', function() {
             $(this).blur();
         });*/
    /*
                table.on('column-visibility.dt', function(e, settings, column, state) {
                    var hidden = [];
                    $.each(tableApi.columns()[0], function() {
                        var visible = tableApi.column($(this)).visible();
                        var columnHeader = $(tableApi.column($(this)).header());
                        if (columnHeader.hasClass('toggleable')) {
                            if (!visible) {
                                hidden.push(columnHeader.attr('id'))
                            }
                        }
                    });
                    var data = {};
                    data.id = table.attr('id');
                    data.hidden = hidden;
                    if (data.id) {
                        $.post(admin_url + 'staff/save_hidden_table_columns', data).fail(function(data) {
                            // Demo usage, prevent multiple alerts
                            if ($('body').find('.float-alert').length === 0) {
                                alert_float('danger', data.responseText);
                            }
                        });
                    } else {
                        console.error('Table that have ability to show/hide columns must have an ID');
                    }
                });*/
  }

  // Fix for hidden tables colspan not correct if the table is empty
  if (table.is(":hidden")) {
    table
      .find(".dataTables_empty")
      .attr("colspan", table.find("thead th").length);
  }

  table.on("preXhr.dt", function (e, settings, data) {
    if (settings.jqXHR) settings.jqXHR.abort();
  });

  return tableApi;
}

function init_datepicker(element_date, element_time) {
  appDatepicker({
    element_date: element_date,
    element_time: element_time,
  });
}

function init_color_pickers() {
  appColorPicker();
}

function init_selectpicker() {
  appSelectPicker();
}

function init_lightbox() {
  appLightbox();
}

function init_progress_bars() {
  appProgressBar();
}

function dt_custom_view(value, table, custom_input_name, clear_other_filters) {
  var name =
    typeof custom_input_name == "undefined" ? "custom_view" : custom_input_name;
  if (typeof clear_other_filters != "undefined") {
    var filters = $("._filter_data li.active").not(".clear-all-prevent");
    filters.removeClass("active");
    $.each(filters, function () {
      var input_name = $(this).find("a").attr("data-cview");
      $('._filters input[name="' + input_name + '"]').val("");
    });
  }
  var _cinput = do_filter_active(name);
  if (_cinput != name) {
    value = "";
  }
  $('input[name="' + name + '"]').val(value);
  $(table).DataTable().ajax.reload();
}

function do_filter_active(value, parent_selector) {
  if (value !== "" && typeof value != "undefined") {
    $('[data-cview="all"]').parents("li").removeClass("active");
    var selector = $('[data-cview="' + value + '"]');
    if (typeof parent_selector != "undefined") {
      selector = $(parent_selector + ' [data-cview="' + value + '"]');
    }
    var parent = selector.parents("li");
    if (parent.hasClass("filter-group")) {
      var group = parent.data("filter-group");
      $('[data-filter-group="' + group + '"]')
        .not(parent)
        .removeClass("active");
      $.each($('[data-filter-group="' + group + '"]').not(parent), function () {
        $('input[name="' + $(this).find("a").attr("data-cview") + '"]').val("");
      });
      //   $('input[name="' + value + '"]').val('');
    }
    if (!parent.not(".dropdown-submenu").hasClass("active")) {
      parent.addClass("active");
    } else {
      parent.not(".dropdown-submenu").removeClass("active");
      parent.find("a").blur();
      // Remove active class from the parent dropdown if nothing selected in the child dropdown
      var parents_sub = selector.parents("li.dropdown-submenu");
      if (parents_sub.length > 0) {
        if (parents_sub.find("li.active").length === 0) {
          parents_sub.removeClass("active");
        }
      }
      value = "";
    }
    return value;
  } else {
    $("._filters input").val("");
    $("._filter_data li.active").removeClass("active");
    $('[data-cview="all"]').parents("li").addClass("active");
    return "";
  }
}

function fix_kanban_height(col_px, container_px) {
  // Set the width of the kanban container
  $("body").find("div.dt-loader").remove();
  var kanbanCol = $(".kan-ban-content-wrapper");
  kanbanCol.css("max-height", window.innerHeight - col_px + "px");
  $(".kan-ban-content").css("min-height", window.innerHeight - col_px + "px");
  var kanbanColCount = parseInt(kanbanCol.length);
  $(".container-fluid").css("min-width", kanbanColCount * container_px + "px");
}

/* The same native ready lifecycle is used by both admin bootstraps. */
(function(root){
  var select_picker_validated_event=false,selectBound=false;
  root.NativeAdminWidgets={
    initTags:function(){init_tags_inputs();},
    initTables:function(){init_color_pickers();initDataTableInline();},
    initVisuals:function(){init_lightbox();init_progress_bars();},
    initDates:function(){init_datepicker();},
    initSelects:function(){
      if(selectBound)return;
      selectBound=true;
  // jQuery validation fix for select, works only on required
  // Wrap it in another validate event because is throwing errors if the form is not yet validated
  $(document).on("app.form-validate", function (e, form) {
    if (select_picker_validated_event === true) {
      return true;
    }

    select_picker_validated_event = true;

    $(form).on(
      "change",
      "select.ajax-search, select.selectpicker",
      function (e) {
        if ($(this).selectpicker("val") && !$(this).is(":disabled")) {
          if (
            typeof $(this).rules() != "undefined" &&
            Object.keys($(this).rules()).length === 1 &&
            $(this).rules().hasOwnProperty("required")
          ) {
            var parent = $(this).parents(".form-group");
            parent.find("#" + $(this).attr("name") + "-error").remove();
            parent.removeClass("has-error");
          }
        }
      }
    );
  });

  init_selectpicker();
    }
  };
}(window));
