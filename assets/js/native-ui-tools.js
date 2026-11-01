/* Shared native UI definitions. No bootstrapping or automatic network operations. */

function requestGet(uri, params) {
  params = typeof params == "undefined" ? {} : params;
  var options = {
    type: "GET",
    url: uri.indexOf(admin_url) > -1 ? uri : admin_url + uri,
  };
  return $.ajax($.extend({}, options, params));
}

function requestGetJSON(uri, params) {
  params = typeof params == "undefined" ? {} : params;
  params.dataType = "json";
  return requestGet(uri, params);
}

function init_tags_inputs() {
  appTagsInput();
}

function init_editor(selector, settings) {
  selector = typeof selector == "undefined" ? ".tinymce" : selector;
  var _editor_selector_check = $(selector);

  if (_editor_selector_check.length === 0) {
    return;
  }

  $.each(_editor_selector_check, function () {
    if ($(this).hasClass("tinymce-manual")) {
      $(this).removeClass("tinymce");
    }
  });

  // Original settings
  var _settings = {
    branding: false,
    promotion: false,
    selector: selector,
    browser_spellcheck: true,
    cache_suffix: '?v='+app.version,
    height: 250,
    min_height: 250,
    statusbar: false,
    theme: "silver",
    paste_block_drop: true,
    language: app.tinymce_lang || 'en',
    relative_urls: false,
    entity_encoding: "raw",
    autoresize_bottom_margin: 25,
    valid_elements: "+*[*]",
    valid_children: "+body[style], +style[type]",
    remove_script_host: false,
    removed_menuitems: "newdocument restoredraft",
    forced_root_block: "p",
    autosave_restore_when_empty: false,
    font_size_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt",
    table_default_styles: {
      width: "100%",
    },
    plugins: [
      "advlist", "autoresize", "autosave", "lists", "link", "image", "codesample",
      "visualblocks", "code", "fullscreen",
      "media", "save", "table",
    ],
    toolbar: "fontfamily fontsize | forecolor backcolor | bold italic | alignleft aligncenter alignright alignjustify | image link | bullist numlist | restoredraft",
    contextmenu: "link image | paste copy",
    file_picker_callback : elFinderBrowser,
    setup: function (ed) {
      // Default fontsize is 12
      ed.on("init", function () {
        this.getDoc().body.style.fontSize = "12pt";
      });
    },
  };

  // Add the rtl to the settings if is true
  if(isRTL == "true") {
    _settings.directionality = "rtl"
    _settings.plugins.push('directionality')
  }

  // Possible settings passed to be overwrited or added
  if (typeof settings != "undefined") {
    for (var key in settings) {
      if (key != "append_plugins") {
        _settings[key] = settings[key];
      } else {
        _settings.plugins.push(settings[key]);
      }
    }
  }

  // Init the editor
  var editor = tinymce.init(_settings);

  $(document).trigger("app.editor.initialized");

  return editor;
}

function elFinderBrowser(callback, value, meta) {
  tinymce.activeEditor.windowManager.elfinderCallback = callback
  
  tinymce.activeEditor.windowManager.openUrl({
      url: admin_url + "misc/tinymce_file_browser",
      title: app.lang.media_files,
      width: 900,
      height: 450,
  });
  
  return false;
}

function init_ajax_search(type, selector, server_data, url) {
  var ajaxSelector = $("body").find(selector);

  if (ajaxSelector.length) {
    var options = {
      ajax: {
        url:
          typeof url == "undefined"
            ? admin_url + "misc/get_relation_data"
            : url,
        data: function () {
          var data = {};
          data.type = type;
          data.rel_id = "";
          data.q = "{{{q}}}";
          if (typeof server_data != "undefined") {
            jQuery.extend(data, server_data);
          }
          return data;
        },
      },
      locale: {
        emptyTitle: app.lang.search_ajax_empty,
        statusInitialized: app.lang.search_ajax_initialized,
        statusSearching: app.lang.search_ajax_searching,
        statusNoResults: app.lang.not_results_found,
        searchPlaceholder: app.lang.search_ajax_placeholder,
        currentlySelected: app.lang.currently_selected,
      },
      requestDelay: 500,
      cache: false,
      preprocessData: function (processData) {
        var bs_data = [];
        var len = processData.length;
        for (var i = 0; i < len; i++) {
          var tmp_data = {
            value: processData[i].id,
            text: processData[i].name,
          };
          if (processData[i].subtext) {
            tmp_data.data = {
              subtext: processData[i].subtext,
            };
          }
          if (Object.prototype.hasOwnProperty.call(processData[i], "native_revision")) {
            if (!Number.isInteger(processData[i].native_revision) || processData[i].native_revision < 1 || processData[i].native_revision > 2147483646) throw new Error("native_task_revision_invalid");
            tmp_data.data = tmp_data.data || {};
            tmp_data.data["native-task-revision"] = String(processData[i].native_revision);
          }
          bs_data.push(tmp_data);
        }
        return bs_data;
      },
      preserveSelectedPosition: "after",
      preserveSelected: true,
    };
    if (ajaxSelector.data("empty-title")) {
      options.locale.emptyTitle = ajaxSelector.data("empty-title");
    }
    ajaxSelector.selectpicker().ajaxSelectPicker(options);
  }
}

function system_popup(data) {
  data.content = typeof data.content == "undefined" ? "" : data.content;

  var $popupHTML = $("<div/>", {
    id: "system-popup",
    class: "system-popup",
  }).appendTo("body");

  var overlayHTML = "";
  overlayHTML += '<div class="popup-wrapper fadeIn animated">';
  overlayHTML += '<h2 class="popup-message">';
  overlayHTML += data.message;
  overlayHTML += "</h2>";
  overlayHTML += '<div class="popup-content">';
  overlayHTML += data.content;
  overlayHTML += '<button type="button" class="system-popup-close"> </button>';
  overlayHTML += "</div>";
  overlayHTML += "</div>";

  $popupHTML.html(overlayHTML).removeClass("hide");
  $("body").addClass("system-popup");
  $popupHTML.find(".system-popup-close").on("click", function () {
    var that = this;
    // This native contextual endpoint is a mutation: never issue it as GET.
    var clearRequest;
    if (document.body.classList.contains("native-company-admin")) {
      if (typeof NativeCompanyTransport === "undefined") throw new Error("native_transport_unavailable");
      clearRequest = NativeCompanyTransport.request("misc/clear_system_popup", "POST", {});
    } else {
      clearRequest = requestGet("misc/clear_system_popup");
    }
    clearRequest.done(function (response) {
      setTimeout(function () {
        $("body").removeClass("system-popup");
        $popupHTML.fadeOut(400, function () {
          $popupHTML.remove();
        });
        $(that).off("click");
      }, 50);
    });
  });

  return $popupHTML;
}

function showHideTagsPlaceholder($tagit) {
  var $input = $tagit.data("ui-tagit").tagInput,
    placeholderText = $tagit.data("ui-tagit").options.placeholderText;
  $tagit.tagit("assignedTags").length > 0
    ? $input.removeAttr("placeholder")
    : $input.attr("placeholder", placeholderText);
}

/* Same invoice menu actions; only explicit user clicks mutate, and GET is never retried. */
function native_invoice_communication(element) {
  if (!window.NativeCompanyTransport) throw Error('native_invoice_transport_required');
  var $link = $(element), revision = $link.attr('data-native-invoice-communication-revision');
  if (!/^[a-f0-9]{64}$/.test(revision || '')) throw Error('native_invoice_revision_required');
  if ($link.attr('aria-busy') === 'true') return false;
  var key = $link.attr('data-native-request-key');
  if (!key) {
    var bytes = new Uint8Array(32);
    window.crypto.getRandomValues(bytes);
    key = Array.from(bytes, function (value) { return value.toString(16).padStart(2, '0'); }).join('');
    $link.attr('data-native-request-key', key);
  }
  $link.attr('aria-busy', 'true');
  window.NativeCompanyTransport.request($link.attr('href'), 'POST', {revision: revision, request_key: key}, {dataType: 'json'})
    .done(function (response) {
      if (!response || response.success !== true || typeof response.redirect !== 'string') throw Error('native_invoice_response_invalid');
      window.NativeCompanyTransport.navigate(response.redirect);
    })
    .always(function () { $link.attr('aria-busy', 'false'); });
  return false;
}



function deselect_ajax_search(e) {
  var $elm = $("select#" + $(e).attr("data-id"));
  $elm.data("AjaxBootstrapSelect").list.cache = {};
  var $elmWrapper = $elm.parents(".bootstrap-select");
  $elm
    .html("")
    .append('<option value=""></option>')
    .selectpicker("val", $elm.attr("multiple") == "multiple" ? [] : "");
  $elmWrapper
    .removeClass("ajax-remove-values-option")
    .find(".ajax-clear-values")
    .remove();
  setTimeout(function () {
    $elm.trigger("selected.cleared.ajax.bootstrap.select", e);
    $elm.trigger("change").data("AjaxBootstrapSelect").list.cache = {};
  }, 50);
}


function unformat_date(date) {
  var date_array = _split_formatted_date_by_separator(date),
    // Y-m-d is default, see below commented code
    month_index = 1,
    year_index = 0,
    day_index = 2;
  if (
    app.options.date_format == "d-m-Y" ||
    app.options.date_format == "d/m/Y" ||
    app.options.date_format == "d.m.Y"
  ) {
    day_index = 0;
    month_index = 1;
    year_index = 2;
  } else if (
    /* else if (app.options.date_format == 'Y-m-d') {
            day_index = 2;
            month_index = 1;
            year_index = 0;
        }*/
    app.options.date_format == "m-d-Y" ||
    app.options.date_format == "m.d.Y" ||
    app.options.date_format == "m/d/Y"
  ) {
    day_index = 1;
    month_index = 0;
    year_index = 2;
  }

  return (
    date_array[year_index] +
    "-" +
    date_array[month_index] +
    "-" +
    date_array[day_index]
  );
}


function _split_formatted_date_by_separator(date) {
  var date_array;

  if (date.indexOf(".") > -1) {
    date_array = date.split(".");
  } else if (date.indexOf("-") > -1) {
    date_array = date.split("-");
  } else if (date.indexOf("/") > -1) {
    date_array = date.split("/");
  }

  return date_array;
}


function initializeTinyMceMentions(editor, usersCallback, options = {}) {
  if(!Object.hasOwn(editor, 'perfexCommands')) {
    editor.perfexCommands = {}
  }

  let cachedUsers = null;

  editor.perfexCommands.getUsersForMention = async function() {
    if(Array.isArray(cachedUsers)) {
      return cachedUsers
    }

    let users = await usersCallback()

    // The task endpoint has historically returned HTML-encoded names; project
    // endpoints return text. Normalize only the declared encoded contract so a
    // literal "&lt;" in a project name is not silently changed.
    const decodeName = value => {
      if (!options.namesHtmlEncoded) return String(value);
      const decoder = editor.getDoc().createElement('textarea');
      decoder.innerHTML = String(value);
      return decoder.value;
    };
    cachedUsers = users.map(u=>({
      value: u.id.toString(),
      text: decodeName(u.name),
    }));

    return cachedUsers
  },
  editor.perfexCommands.insertMentionUser = function (id, name, rng) {
    // Insert in to the editor
    editor.selection.setRng(rng || 0)

    // Text and attribute values are serialized by the DOM, never concatenated
    // into HTML received from the user directory.
    const mention = editor.getDoc().createElement('span');
    mention.className = 'mention';
    mention.setAttribute('contenteditable', 'false');
    mention.setAttribute('data-mention-id', String(id));
    mention.textContent = '@' + String(name);
    editor.insertContent(mention.outerHTML + '&nbsp;');
  }

  editor.ui.registry.addAutocompleter('mentions', {
    trigger: '@', // the trigger character to open the autocompleter
    minChars: 0, // 0 to open the dropdown immediately after the @ is typed
    columns: 1, // must be 1 for text-based results

    // Retrieve the available users
    fetch: function (pattern) {
      return new Promise(resolve =>
          resolve(editor.perfexCommands.getUsersForMention())
      )
    },

    // Executed when user is selected from the dropdown
    onAction: function (autocompleteApi, rng, value) {
      editor.perfexCommands.getUsersForMention().then(users=> {
        let user = users.find(user=>user.value == value)
        editor.perfexCommands.insertMentionUser(value, user.text, rng)
        autocompleteApi.hide()
      })
    },
  })
}
// Shared native UI helper: mention initializer end.


function toggle_small_view(table, main_data) {
  if (
    !is_mobile() &&
    $("#small-table").hasClass("hide") &&
    $(".small-table-right-col").hasClass("col-md-12")
  ) {
    $("#small-table").toggleClass("hide");
    $(".small-table-right-col").toggleClass("col-md-12 col-md-7");
    $(window).trigger("resize");
    return;
  }
  $("body").toggleClass("small-table");
  var tablewrap = $("#small-table");
  if (tablewrap.length === 0) {
    return;
  }
  var _visible = false;
  if (tablewrap.hasClass("col-md-5")) {
    tablewrap.removeClass("col-md-5").addClass("col-md-12");
    _visible = true;
    $(".toggle-small-view")
      .find("i")
      .removeClass("fa fa-angle-double-right")
      .addClass("fa fa-angle-double-left");
  } else {
    tablewrap.addClass("col-md-5").removeClass("col-md-12");
    $(".toggle-small-view")
      .find("i")
      .removeClass("fa fa-angle-double-left")
      .addClass("fa fa-angle-double-right");
  }
  var _table = $(table).DataTable();
  // Show hide hidden columns
  _table.columns(hidden_columns).visible(_visible, false);
  _table.columns.adjust();
  $(main_data).toggleClass("hide");
  $(window).trigger("resize");
}


function do_hash_helper(hash) {
  if (typeof history.pushState != "undefined") {
    var url = window.location.href;
    var obj = {
      Url: url,
    };
    history.pushState(obj, "", obj.Url);
    window.location.hash = hash;
  }
}


function check_kanban_empty_col(selector) {
  var statuses = $("[data-col-status-id]");
  $.each(statuses, function (i, obj) {
    var total = $(obj).find(selector).length;
    if (total == 0) {
      $(obj).find(".kanban-empty").removeClass("hide");
      $(obj).find(".kanban-load-more").addClass("hide");
    } else {
      $(obj).find(".kanban-empty").addClass("hide");
    }
  });
}


function init_kanban(
  url,
  callbackUpdate,
  connect_with,
  column_px,
  container_px,
  callback_after_load
) {
  if ($("#kan-ban").length === 0) {
    return;
  }

  var parameters = [];
  var _kanban_param_val;

  $.each($("#kanban-params input"), function () {
    if ($(this).attr("type") == "checkbox") {
      _kanban_param_val = $(this).prop("checked") === true ? $(this).val() : "";
    } else {
      _kanban_param_val = $(this).val();
    }
    if (_kanban_param_val !== "") {
      parameters[$(this).attr("name")] = _kanban_param_val;
    }
  });

  var search = $('input[name="search"]').val();
  if (typeof search != "undefined" && search !== "") {
    parameters["search"] = search;
  }

  var sort_type = $('input[name="sort_type"]');
  var sort = $('input[name="sort"]').val();
  if (sort_type.length != 0 && sort_type.val() !== "") {
    parameters["sort_by"] = sort_type.val();
    parameters["sort"] = sort;
  }

  parameters["kanban"] = true;

  url = admin_url + url;
  url = buildUrl(url, parameters);
  var $kanBanCols = $(".kan-ban-col");
  if ($kanBanCols.length > 0) {
    var refreshParams = [];
    $kanBanCols.each(function (index, col) {
      refreshParams.push({
        status: $(col).data("col-status-id"),
        total: parseInt($(col).attr("data-total")),
      });
    });
    url +=
      "&" +
      refreshParams
        .map(function (el, idx) {
          return "refresh[" + el.status + "]=" + el.total;
        })
        .join("&");
  }

  delay(function () {
    $("body").append('<div class="dt-loader"></div>');
    $("#kan-ban").load(url, function () {
      fix_kanban_height(column_px, container_px);
      var scrollingSensitivity = 20,
        scrollingSpeed = 60;

      if (typeof callback_after_load != "undefined") {
        callback_after_load();
      }

      $(".status").sortable({
        connectWith: connect_with,
        helper: "clone",
        appendTo: "#kan-ban",
        placeholder: "ui-state-highlight-card",
        revert: "invalid",
        scrollingSensitivity: 50,
        scrollingSpeed: 70,
        sort: function (event, uiHash) {
          var scrollContainer = uiHash.placeholder[0].parentNode;
          // Get the scrolling parent container
          scrollContainer = $(scrollContainer).parents(
            ".kan-ban-content-wrapper"
          )[0];
          var overflowOffset = $(scrollContainer).offset();
          if (
            overflowOffset.top + scrollContainer.offsetHeight - event.pageY <
            scrollingSensitivity
          ) {
            scrollContainer.scrollTop =
              scrollContainer.scrollTop + scrollingSpeed;
          } else if (event.pageY - overflowOffset.top < scrollingSensitivity) {
            scrollContainer.scrollTop =
              scrollContainer.scrollTop - scrollingSpeed;
          }
          if (
            overflowOffset.left + scrollContainer.offsetWidth - event.pageX <
            scrollingSensitivity
          ) {
            scrollContainer.scrollLeft =
              scrollContainer.scrollLeft + scrollingSpeed;
          } else if (event.pageX - overflowOffset.left < scrollingSensitivity) {
            scrollContainer.scrollLeft =
              scrollContainer.scrollLeft - scrollingSpeed;
          }
        },
        change: function () {
          var list = $(this).closest("ul");
          var KanbanLoadMore = $(list).find(".kanban-load-more");
          $(list).append($(KanbanLoadMore).detach());
        },
        start: function (event, ui) {
          $("body").css("overflow", "hidden");

          $(ui.helper).addClass("tilt");
          $(ui.helper).find(".panel-body").css("background", "#fbfbfb");
          // Start monitoring tilt direction
          tilt_direction($(ui.helper));
        },
        stop: function (event, ui) {
          $("body").removeAttr("style");
          $(ui.helper).removeClass("tilt");
          // Unbind temporary handlers and excess data
          $("html").off("mousemove", $(ui.helper).data("move_handler"));
          $(ui.helper).removeData("move_handler");
        },
        update: function (event, ui) {
          callbackUpdate(ui, this);
        },
      });

      $(".status").sortable({
        cancel: ".not-sortable",
      });
    });
  }, 200);
}


function update_kan_ban_total_when_moving(ui, newStatus) {
  if (ui.sender) {
    var oldStatusCol = $(ui.sender.closest(".kan-ban-col"));
    if (oldStatusCol.data("status-id") != newStatus) {
      var newStatusCol = $('[data-col-status-id="' + newStatus + '"]');
      newStatusCol.attr(
        "data-total",
        parseInt(newStatusCol.attr("data-total")) + 1
      );
      var oldStatusCol = $(ui.sender.closest(".kan-ban-col"));
      oldStatusCol.attr(
        "data-total",
        parseInt(oldStatusCol.attr("data-total")) - 1
      );
    }
  }
}


function task_mark_as(status, task_id, url) {
  url =
    typeof url == "undefined" ? "tasks/mark_as/" + status + "/" + task_id : url;
  var taskModalVisible = $("#task-modal").is(":visible");
  url += "?single_task=" + taskModalVisible;
  $("body").append('<div class="dt-loader"></div>');
  var request;
  if ($("body").hasClass("native-company-admin")) {
    var row = $('[data-native-task-id]').filter(function () {
      return $(this).attr('data-native-task-id') === String(task_id);
    }).first();
    var revision = row.attr('data-task-revision');
    var fieldsRevision = row.attr('data-native-fields-revision') || '';
    if (taskModalVisible || !/^[1-5]$/.test(String(status)) || !/^[1-9][0-9]*$/.test(String(task_id)) || !/^[1-9][0-9]*$/.test(revision || '') || (fieldsRevision && !/^[1-9][0-9]*$/.test(fieldsRevision))) {
      $("body").find(".dt-loader").remove();
      alert_float("danger", "Não foi possível confirmar a revisão desta tarefa. Atualize a página e tente novamente.");
      return;
    }
    var key = native_task_template_request_key(row, 'status:' + status + ':' + revision + ':' + fieldsRevision);
    request = $.ajax({url: admin_url + 'tasks/mark_as/' + encodeURIComponent(status) + '/' + encodeURIComponent(task_id), type:'POST', dataType:'json', data:{native_revision:revision,native_fields_revision:fieldsRevision,native_request_key:key}});
  } else {
    request = requestGetJSON(url);
  }
  request.done(function (response) {
    $("body").find(".dt-loader").remove();
    if (response.success === true || response.success == "true") {
      reload_tasks_tables();
      if (taskModalVisible) {
        _task_append_html(response.taskHtml);
      }
      if (
        status == 5 &&
        typeof _maybe_remove_task_from_project_milestone == "function"
      ) {
        _maybe_remove_task_from_project_milestone(task_id);
      }
      if ($(".tasks-kanban").length === 0) {
        alert_float("success", response.message);
      }
    }
  }).fail(function () {
    $("body").find(".dt-loader").remove();
    if ($("body").hasClass("native-company-admin")) alert_float("danger", "Não foi possível alterar o estado da tarefa. Atualize a página e tente novamente.");
  });
}


function tasks_kanban_update(ui, object) {
  if (object === ui.item.parent()[0]) {
    var status = $(ui.item.parent()[0]).attr("data-task-status-id");

    var data = {
      order: [],
      status: status,
    };

    $.each($(ui.item.parent()[0]).find("[data-task-id]"), function (idx, el) {
      var id = $(el).attr("data-task-id");
      if (id) {
        data.order.push([id, idx + 1]);
      }
    });

    task_mark_as(status, $(ui.item).attr("data-task-id"));

    check_kanban_empty_col("[data-task-id]");

    setTimeout(function () {
      $.post(admin_url + "tasks/update_order", data).done(function (response) {
        update_kan_ban_total_when_moving(ui, data.status);
        tasks_kanban();
      });
    }, 200);
  }
}


function tasks_kanban() {
  init_kanban("tasks/kanban", tasks_kanban_update, ".tasks-status", 240, 360);
}


function load_small_table_item(id, selector, input_name, url, table) {
  var _tmpID = $('input[name="' + input_name + '"]').val();
  // Check if id passed from url, hash is prioritized becuase is last
  if (_tmpID !== "" && !window.location.hash) {
    id = _tmpID;
    // Clear the current id value in case user click on the left sidebar credit_note_ids
    $('input[name="' + input_name + '"]').val("");
  } else {
    // check first if hash exists and not id is passed, becuase id is prioritized
    if (window.location.hash && !id) {
      id = window.location.hash.substring(1); //Puts hash in variable, and removes the # character
    }
  }
  if (typeof id == "undefined" || id === "") {
    return;
  }
  destroy_dynamic_scripts_in_element($(selector));
  if (!$("body").hasClass("small-table")) {
    toggle_small_view(table, selector);
  }
  $('input[name="' + input_name + '"]').val(id);
  do_hash_helper(id);
  $(selector).load(admin_url + url + "/" + id)

  $("html, body").animate(
    {
      scrollTop: $(selector).offset().top + (is_mobile() ? 150 : 0),
    },
    600
  );
}


function init_invoice(id) {
  load_small_table_item(
    id,
    "#invoice",
    "invoiceid",
    "invoices/get_invoice_data_ajax",
    ".table-invoices"
  );
}


function validate_credit_note_form(selector) {
  selector = typeof selector == "undefined" ? "#credit-note-form" : selector;

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
    number: {
      required: true,
    },
  });

  $("body")
    .find('input[name="number"]')
    .rules("add", {
      remote: {
        url: admin_url + "credit_notes/validate_number",
        type: "post",
        data: {
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
            return $(".credit_note input[name='date']").val();
          },
        },
      },
      messages: {
        remote: app.lang.credit_note_number_exists,
      },
    });
}


function validate_estimate_form(selector) {
  selector = typeof selector == "undefined" ? "#estimate-form" : selector;

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
    number: {
      required: true,
    },
  });

  $("body")
    .find('input[name="number"]')
    .rules("add", {
      remote: {
        url: admin_url + "estimates/validate_estimate_number",
        type: "post",
        data: {
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
            return $("body").find('.estimate input[name="date"]').val();
          },
        },
      },
      messages: {
        remote: app.lang.estimate_number_exists,
      },
    });
}


function configure_ai_editor(editor) {
  let items = [
      {
        type: 'menuitem',
        text:app.lang.text_enhancement_make_polite,
        icon: 'brightness',
        onAction: function () {
          make_ai_text_enhancement_request(editor, 'polite')
        }
      },
      {
        type: 'menuitem',
        text: app.lang.text_enhancement_make_formal,
        icon: 'accessibility-check',
        onAction: function () {
          make_ai_text_enhancement_request(editor, 'formal')
        }
      },
      {
        type: 'menuitem',
        icon: 'emoji',
        text: app.lang.text_enhancement_make_friendly,
        onAction: function () {
          make_ai_text_enhancement_request(editor, 'friendly')
        }
      }
    ];

    editor.ui.registry.addMenuButton('ai', {
      icon: 'ai',
      fetch: function (callback) {
        callback(items);
      }
    });
}


function make_ai_text_enhancement_request(editor, type) {
  const selectedText = editor.selection.getContent({ format: 'text' });

  if (!selectedText.trim()) {
      alert_float('warning', 'Please select text to enhance.');
      return;
  }

  $.post(admin_url + 'ai/text_enhancement/' + type, {
      text: selectedText,
  })
  .done(function (response) {
      const result = JSON.parse(response);
      if (result.success) {
          editor.selection.setContent(result.message);
      }
  })
  .catch(function (error) {
      const result = JSON.parse(error.responseText);
      alert_float('danger', result.error ? result.error : app.lang.something_went_wrong);
  });
}
