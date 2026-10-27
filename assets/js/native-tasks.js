/* Native Tasks functions shared by the legacy and issuer-scoped shells.
 * Definitions only: no global bootstrap, network calls or automatic mutations.
 * Keep the global names used by the existing native partials. */

function delete_user_unfinished_timesheet(id) {
  if (confirm_delete()) {
    requestGetJSON("tasks/delete_user_unfinished_timesheet/" + id).done(
      function (response) {
        _init_timers_top_html(JSON.parse(response.timers));
        reload_tasks_tables();
      }
    );
  }
}

function reload_tasks_tables() {
  var av_tasks_tables = [
    ".table-tasks",
    ".table-rel-tasks",
    ".table-rel-tasks-leads",
    ".table-timesheets",
    ".table-timesheets-report",
  ];
  $.each(av_tasks_tables, function (i, selector) {
    if ($.fn.DataTable.isDataTable(selector)) {
      $(selector).DataTable().ajax.reload(null, false);
    }
  });
}

function new_task(url, timer_id) {
  url = typeof url != "undefined" ? url : admin_url + "tasks/task";

  var $leadModal = $("#lead-modal");
  if ($leadModal.is(":visible")) {
    url +=
      "&opened_from_lead_id=" + $leadModal.find('input[name="leadid"]').val();
    if (url.indexOf("?") === -1) {
      url = url.replace("&", "?");
    }
    $leadModal.modal("hide");
  }

  var $taskSingleModal = $("#task-modal");
  if ($taskSingleModal.is(":visible")) {
    $taskSingleModal.modal("hide");
  }

  var $taskEditModal = $("#_task_modal");
  if ($taskEditModal.is(":visible")) {
    $taskEditModal.modal("hide");
  }

  requestGet(url)
    .done(function (response) {
      $("#_task").html(response);
      $("body").find("#_task_modal").modal({
        show: true,
        backdrop: "static",
      });

      var stopTimerPopover = $("#timer-select-task");
      if (stopTimerPopover.is(":visible")) {
        $(".system-popup-close").click();
        window._timer_id = timer_id;
      }
    })
    .fail(function (error) {
      alert_float("danger", error.responseText);
    });
}

function new_task_from_relation(table, rel_type, rel_id) {
  if (typeof rel_type == "undefined" && typeof rel_id == "undefined") {
    rel_id = $(table).data("new-rel-id");
    rel_type = $(table).data("new-rel-type");
  }
  var url = admin_url + "tasks/task?rel_id=" + rel_id + "&rel_type=" + rel_type;
  new_task(url);
}

function edit_task(task_id) {
  requestGet("tasks/task/" + task_id).done(function (response) {
    $("#_task").html(response);
    $("#task-modal").modal("hide");
    $("body").find("#_task_modal").modal({
      show: true,
      backdrop: "static",
    });
  });
}

function task_form_handler(form) {
  tinymce.triggerSave();

  $("#_task_modal").find('input[name="startdate"]').prop("disabled", false);
  // Disable the save button in cases od duplicate clicks
  $("#_task_modal").find('button[type="submit"]').prop("disabled", true);

  $("#_task_modal input[type=file]").each(function () {
    if ($(this).val() === "") {
      $(this).prop("disabled", true);
    }
  });

  var formURL = form.action;
  var formData = new FormData($(form)[0]);

  $.ajax({
    type: $(form).attr("method"),
    data: formData,
    mimeType: $(form).attr("enctype"),
    contentType: false,
    cache: false,
    processData: false,
    url: formURL,
  })
    .done(function (response) {
      response = JSON.parse(response);
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
      }

      if (window._timer_id) {
        requestGet(admin_url + "/tasks/get_task_by_id/" + response.id).done(
          function (response) {
            $('[data-timer-id="' + window._timer_id + '"').click();
            response = JSON.parse(response);
            var option =
              '<option value="' +
              response.id +
              '" title="' +
              response.name +
              '" selected>' +
              response.name +
              "</option>";
            $("#timer_add_task_id").append(option);
            $("#timer_add_task_id")
              .trigger("change")
              .data("AjaxBootstrapSelect").list.cache = {};
            $("#timer_add_task_id").selectpicker("refresh");
            delete window._timer_id;
          }
        );
        $("#_task_modal").modal("hide");
        $("#task-modal").modal("hide");
        return false;
      }

      if (!$("body").hasClass("project")) {
        $("#_task_modal").attr("data-task-created", true);
        $("#_task_modal").modal("hide");
        init_task_modal(response.id);
        reload_tasks_tables();
        if ($("body").hasClass("kan-ban-body") && $("body").hasClass("tasks")) {
          tasks_kanban();
        }
      } else {
        // reload page on project area
        var location = window.location.href;
        var params = [];
        location = location.split("?");
        var group = get_url_param("group");
        var excludeCompletedTasks = get_url_param("exclude_completed");
        if (group) {
          params["group"] = group;
        }
        if (excludeCompletedTasks) {
          params["exclude_completed"] = excludeCompletedTasks;
        }
        params["taskid"] = response.id;
        window.location.href = buildUrl(location[0], params);
      }
    })
    .fail(function (error) {
      alert_float("danger", JSON.parse(error.responseText));
    });

  return false;
}

// Context is transported by the shared NativeCompanyTransport prefilter. These are CAS facts,
// never authority: the original endpoint resolves source IDs and revalidates every write.
function native_task_timer_request(data, frame, requestKey) {
  var decimal = function (v, zero) { return typeof v === "string" && (zero ? /^(0|[1-9][0-9]*)$/ : /^[1-9][0-9]*$/).test(v) && Number(v) <= 2147483646; };
  if (!/^[a-f0-9]{64}$/.test(requestKey || "") || !/^[a-f0-9]{64}$/.test(frame.policy || "")) throw new Error("native_timer_context_missing");
  data.native_request_key = requestKey;
  data.native_policy_hash = frame.policy;
  if (String(data.task_id) !== "0") {
    if (!decimal(frame.taskRevision, false)) throw new Error("native_timer_revision_missing");
    data.native_task_revision = frame.taskRevision;
  }
  if (String(data.timer_id) !== "") {
    if (!decimal(frame.timerRevision, false)) throw new Error("native_timer_revision_missing");
    if (!decimal(frame.noteRevision, true)) throw new Error("native_timer_revision_missing");
    data.native_timer_revision = frame.timerRevision;
    data.native_note_revision = frame.noteRevision;
  }
  return data;
}

function timer_action(e, task_id, timer_id, adminStop) {
  timer_id = typeof timer_id == "undefined" ? "" : timer_id;

  var $timerSelectTask = $("#timer-select-task");
  if (task_id === "" && $timerSelectTask.is(":visible")) {
    return;
  }
  if (timer_id !== "" && task_id == "0") {
    var popupData = {};
    popupData.content = "";
    popupData.content += '<div class="row">';
    popupData.content += '<div class="form-group">';
    if (app.options.has_permission_create_task == "1") {
      popupData.content +=
        '<div class="input-group" style="margin:0 auto;width:60%;">';
    }
    popupData.content +=
      '<select id="timer_add_task_id" data-empty-title="' +
      app.lang.search_tasks +
      '" data-width="60%" class="ajax-search" data-live-search="true">';
    popupData.content += "</select>";
    if (app.options.has_permission_create_task == "1") {
      popupData.content +=
        '<div class="input-group-addon" style="opacity: 1;">';
      popupData.content +=
        '<a href="#" onclick="new_task(\'tasks/task\',' +
        timer_id +
        '); return false;"><i class="fa fa-plus"></i></a>';
      popupData.content += "</div>";
    }
    popupData.content += "</div></div>";
    popupData.content += '<div class="form-group">';
    popupData.content +=
      '<textarea id="timesheet_note" placeholder="' +
      app.lang.note +
      '" style="margin:0 auto;width:60%;" rows="4" class="form-control"></textarea>';
    popupData.content += "</div>";
    popupData.content +=
      "<button type='button' onclick='timer_action(this,document.getElementById(\"timer_add_task_id\").value," +
      timer_id +
      ");return false;' class='btn btn-primary'>" +
      app.lang.confirm +
      "</button>";
    popupData.message = app.lang.task_stop_timer;
    var $popupHTML = system_popup(popupData);
    $popupHTML.attr("id", "timer-select-task");
    init_ajax_search(
      "tasks",
      "#timer_add_task_id",
      undefined,
      admin_url + "tasks/ajax_search_assign_task_to_timer"
    );
    return false;
  }

  $(e).addClass("disabled");

  var data = {};
  data.task_id = task_id;
  data.timer_id = timer_id;
  data.note = $("body").find("#timesheet_note").val();
  if (!data.note) {
    data.note = "";
  }
  var taskModalVisible = $("#task-modal").is(":visible");
  if (document.body.classList.contains("native-company-admin")) {
    var $localTimer = $(e).closest("[data-native-timer-id]");
    var $localTask = $(e).closest("[data-native-task-id]");
    var $row = timer_id !== "" ? ($localTimer.attr("data-native-timer-id") === String(timer_id) ? $localTimer : $("#timer-" + String(timer_id))) : ($localTask.attr("data-native-task-id") === String(task_id) ? $localTask : $(e));
    var $selected = $("#timer_add_task_id option:selected");
    var $modal = $(".task-single-header[data-task-single-id='" + String(task_id) + "']");
    var frame = {
      policy: $row.attr("data-policy-hash") || $modal.attr("data-policy-hash"),
      timerRevision: $row.attr("data-timer-revision"),
      noteRevision: $row.attr("data-note-revision"),
      taskRevision: $timerSelectTask.is(":visible") ? $selected.attr("data-native-task-revision") : ($row.attr("data-timer-revision") || $modal.attr("data-task-revision")),
    };
    var requestKey = $(e).data("native-timer-request-key");
    if (!requestKey) {
      if (!window.crypto || !window.crypto.getRandomValues) throw new Error("native_timer_entropy_unavailable");
      var bytes = new Uint8Array(32); window.crypto.getRandomValues(bytes);
      requestKey = Array.from(bytes, function (v) { return v.toString(16).padStart(2, "0"); }).join("");
      $(e).data("native-timer-request-key", requestKey);
    }
    try { data = native_task_timer_request(data, frame, requestKey); }
    catch (error) { $(e).removeClass("disabled"); throw error; }
  }
  var reqUrl =
    admin_url + "tasks/timer_tracking?single_task=" + taskModalVisible;
  if (adminStop) {
    reqUrl += "&admin_stop=" + adminStop;
  }
  $.post(reqUrl, data).done(function (response) {
    response = JSON.parse(response);

    // Timer action, stopping from staff/member/id
    if ($("body").hasClass("member")) {
      window.location.reload();
    }

    if (taskModalVisible) {
      _task_append_html(response.taskHtml);
    }

    if ($timerSelectTask.is(":visible")) {
      $timerSelectTask.find(".system-popup-close").click();
    }

    _init_timers_top_html(JSON.parse(response.timers));

    $(".popover-top-timer-note").popover("hide");
    reload_tasks_tables();
  }).fail(function () { $(e).removeClass("disabled"); });
}

function init_task_modal(task_id, comment_id) {
  var queryStr = "";
  var $leadModal = $("#lead-modal");
  var $taskAddEditModal = $("#_task_modal");
  if ($leadModal.is(":visible")) {
    queryStr +=
      "?opened_from_lead_id=" + $leadModal.find('input[name="leadid"]').val();
    $leadModal.modal("hide");
  } else if ($taskAddEditModal.attr("data-lead-id") != undefined) {
    queryStr +=
      "?opened_from_lead_id=" + $taskAddEditModal.attr("data-lead-id");
  }

  requestGet("tasks/get_task_data/" + task_id + queryStr)
    .done(function (response) {
      _task_append_html(response);
      if (typeof comment_id != "undefined") {
        setTimeout(function () {
          $('[data-task-comment-href-id="' + comment_id + '"]').click();
        }, 1000);
      }
    })
    .fail(function (data) {
      $("#task-modal").modal("hide");
      alert_float("danger", data.responseText);
    });
}

function _task_append_html(html) {
  var $taskModal = $("#task-modal");

  $taskModal.find(".data").html(html);
  //init_tasks_checklist_items(false, task_id);
  recalculate_checklist_items_progress();
  do_task_checklist_items_height();

  setTimeout(function () {
    $taskModal.modal("show");
    // Init_tags_input is trigged too when task modal is shown
    // This line prevents triggering twice.
    if ($taskModal.is(":visible")) {
      init_tags_inputs();
    }
    init_form_reminder("task");
    fix_task_modal_left_col_height();

    // Show the comment area on mobile when task modal is opened
    // Because the user may want only to upload file, but if the comment textarea is not focused the dropzone won't be shown

    if (is_mobile()) {
      init_new_task_comment(true);
    }
  }, 150);
}

function init_timers() {
  requestGetJSON("tasks/get_staff_started_timers").done(function (response) {
    _init_timers_top_html(response);
  });
}

function _init_timers_top_html(data) {
  var $tt = $("#top-timers");
  var $ttIcon = $("#top-timers").find(".icon-started-timers");
  data.total_timers > 0
    ? $ttIcon.removeClass("hide").html(data.total_timers)
    : $ttIcon.addClass("hide");

  data.total_timers > 0
    ? $("#top-timers").find("i").addClass("tw-animate-spin-slow")
    : $("#top-timers").find("i").removeClass("tw-animate-spin-slow");

  $("#started-timers-top").html(data.html);
}

function do_task_checklist_items_height(task_checklist_items) {
  if (typeof task_checklist_items == "undefined") {
    task_checklist_items = $("body").find(
      "textarea[name='checklist-description']"
    );
  }

  $.each(task_checklist_items, function () {
    var val = $(this).val();
    if (
      $(this).outerHeight() <
      this.scrollHeight +
        parseFloat($(this).css("borderTopWidth")) +
        parseFloat($(this).css("borderBottomWidth"))
    ) {
      $(this).height(0).height(this.scrollHeight);
    }
    if (val === "") {
      $(this).removeAttr("style");
    }
  });
}

function recalculate_checklist_items_progress() {
  var total_finished = $('input[name="checklist-box"]:checked').length;
  var total_checklist_items = $('input[name="checklist-box"]').length;
  var percent = 0,
    task_progress_bar = $(".task-progress-bar");
  $(".task-total-checklist-completed").text(total_finished);
  if (total_checklist_items == 0) {
    // remove the heading for checklist items
    // $("body").find(".chk-heading").remove();
    $("#task-no-checklist-items").removeClass("hide");
  } else {
    $("#task-no-checklist-items").addClass("hide");
  }
  if (total_checklist_items >= 2) {
    task_progress_bar.parents(".progress").removeClass("hide");
    percent = (total_finished * 100) / total_checklist_items;
    if (percent == 0) {
      $(".task-progress-bar").addClass("text-dark");
    } else {
      $(".task-progress-bar").removeClass("text-dark");
    }
  } else {
    task_progress_bar.parents(".progress").addClass("hide");
    if (total_finished > 0) {
      $(".chk-toggle-buttons").removeClass("hide");
    } else {
      $(".chk-toggle-buttons").addClass("hide");
    }
    return false;
  }
  task_progress_bar.css("width", percent.toFixed(2) + "%");
  task_progress_bar.text(percent.toFixed(2) + "%");

  if (total_finished > 0) {
    $(".chk-toggle-buttons").removeClass("hide");
  } else {
    $(".chk-toggle-buttons").addClass("hide");
  }

  if (percent == 100) {
    task_progress_bar
      .removeClass("progress-bar-default")
      .addClass("progress-bar-success");
  } else {
    task_progress_bar
      .removeClass("progress-bar-success")
      .addClass("progress-bar-default");
  }
}

function fix_task_modal_left_col_height() {
  if (!is_mobile()) {
    $("body")
      .find(".task-single-col-left")
      .css(
        "min-height",
        $("body").find(".task-single-col-right").outerHeight(true) + "px"
      );
  }
}

function init_new_task_comment(manual) {
  if (tinymce.get('task_comment')) {
    tinymce.remove("#task_comment");
  }

  if (typeof taskCommentAttachmentDropzone != "undefined") {
    taskCommentAttachmentDropzone.destroy();
  }

  $("#dropzoneTaskComment").removeClass("hide");
  $("#addTaskCommentBtn").removeClass("hide");

  taskCommentAttachmentDropzone = new Dropzone(
    "#task-comment-form",
    appCreateDropzoneOptions({
      uploadMultiple: true,
      clickable: "#dropzoneTaskComment",
      previewsContainer: ".dropzone-task-comment-previews",
      autoProcessQueue: false,
      addRemoveLinks: true,
      parallelUploads: 20,
      maxFiles: 20,
      paramName: "file",
      sending: function (file, xhr, formData) {
        formData.append(
          "taskid",
          $("#addTaskCommentBtn").attr("data-comment-task-id")
        );
        if (tinyMCE.activeEditor) {
          formData.append("content", tinyMCE.activeEditor.getContent());
        } else {
          formData.append("content", $("#task_comment").val());
        }
      },
      success: function (files, response) {
        response = JSON.parse(response);
        if (
          this.getUploadingFiles().length === 0 &&
          this.getQueuedFiles().length === 0
        ) {
          _task_append_html(response.taskHtml);
          tinymce.remove("#task_comment");
        }
      },
    })
  );

  var editorConfig = _simple_editor_config();
  
  editorConfig.toolbar_sticky = true

  if (typeof manual == "undefined" || manual === false) {
    editorConfig.auto_focus = true;
  }

  var taskid = $("#task-modal #taskid").val();

  editorConfig.content_style =
    "span.mention {\
        background-color: #eeeeee;\
        padding: 3px;\
    }";

  editorConfig.setup = function (editor) {
    initializeTinyMceMentions(editor, function () {
      return $.getJSON(
        admin_url + "tasks/get_staff_names_for_mentions/" + taskid     
      )
    }, { namesHtmlEncoded: true })
  };

  init_editor("#task_comment", editorConfig)
}

// Stable per-action retry key; changing the submitted action creates a new intent.
function native_task_template_request_key(element, fingerprint) {
  var state = element.data("native-template-request");
  if (!state || state.fingerprint !== fingerprint) {
    if (!window.crypto || !window.crypto.getRandomValues) throw new Error("Secure request identity unavailable");
    var bytes = new Uint8Array(32); window.crypto.getRandomValues(bytes);
    state = { fingerprint: fingerprint, key: Array.from(bytes).map(function (v) { return v.toString(16).padStart(2, "0"); }).join("") };
    element.data("native-template-request", state);
  }
  return state.key;
}

function remove_checklist_item_template(id) {
  var selectedOption = $("select.checklist-items-template-select option").filter(function () { return String(this.value) === String(id); }).first();
  var nativeMode = $("body").hasClass("native-company-admin");
  var request = nativeMode ? $.ajax({url: admin_url + "tasks/remove_checklist_item_template/" + encodeURIComponent(id), type: "POST", dataType: "json", data: {
    native_revision: selectedOption.attr("data-template-revision"),
    native_request_key: native_task_template_request_key(selectedOption, "delete:" + id + ":" + selectedOption.attr("data-template-revision"))
  }}) : requestGetJSON("tasks/remove_checklist_item_template/" + id);
  request.done(function (
    response
  ) {
    if (response.success === true || response.success == "true") {
      var itemsTemplateSelect = $("body").find(
        "select.checklist-items-template-select"
      );
      var deletedItemDescription = itemsTemplateSelect
        .find('option[value="' + id + '"]')
        .text()
        .trim();
      var currentChecklists = $("#task-modal .checklist");
      $.each(currentChecklists, function (i, area) {
        var checkList = $(area);
        if (
          checkList
            .find('textarea[name="checklist-description"]')
            .val()
            .trim() == deletedItemDescription
        ) {
          checkList.find(".save-checklist-template").removeClass("hide");
        }
      });
      itemsTemplateSelect.find('option[value="' + id + '"]').remove();
      itemsTemplateSelect.selectpicker("refresh");
      if (itemsTemplateSelect.find("option").length === 1) {
        itemsTemplateSelect.selectpicker("destroy");
        $(".checklist-templates-wrapper").addClass("hide");
      }
    }
  });
}

// New task checklist items template
function save_checklist_item_template(id, field) {
  var description = $(
    '.checklist[data-checklist-id="' + id + '"] textarea'
  ).val();
  var payload = { description: description };
  if ($("body").hasClass("native-company-admin")) {
    payload.native_request_key = native_task_template_request_key($(field), "create:" + description);
  }
  $.post(admin_url + "tasks/save_checklist_item_template", payload).done(function (response) {
    if (typeof response === "string") response = JSON.parse(response);
    $(field).addClass("hide");
    var singleChecklistTemplate = $(".checklist-templates-wrapper");
    singleChecklistTemplate
      .find('select option[value=""]')
      .after($("<option>").val(response.id).text(description.trim()).attr("data-template-revision", response.revision || ""));
    singleChecklistTemplate.removeClass("hide");
    singleChecklistTemplate.find("select").selectpicker("refresh");
  });
}

// Same original inputs, with explicit indeterminate historical values until the user declares them.
function native_task_form_declaration(form, fields) {
  var booleans = ["is_public", "billable", "visible_to_client"];
  fields.forEach(function (name) {
    var input = form.find('[name="' + name + '"]');
    input.attr("data-native-undeclared", "1").attr("aria-description", "Valor histórico não declarado");
    if (booleans.indexOf(name) !== -1) {
      var proof = $("<input>").attr({type:"hidden", name:"native_declaration_flags[" + name + "]", value:""}).appendTo(form);
      input.prop("indeterminate", true).closest(".checkbox").removeClass("hide");
      input.on("change.nativeDeclaration", function () {
        input.prop("indeterminate", false).attr("data-native-undeclared", "0");proof.val(input.prop("checked") ? "1" : "0");
      });
    } else {
      input.attr("required", "required").on("change.nativeDeclaration input.nativeDeclaration", function () { input.attr("data-native-undeclared", input.val() === "" ? "1" : "0"); });
    }
  });
  form.on("submit.nativeDeclaration", function (event) {
    var unresolved=form.find('[data-native-undeclared="1"]');
    if(unresolved.length){event.preventDefault();event.stopImmediatePropagation();unresolved.first().focus();return false;}
  });
}

function task_tracking_stats(id) {
  requestGet("tasks/task_tracking_stats/" + id).done(function (response) {
    $("<div/>", {
      id: "tracking-stats",
    })
      .appendTo("body")
      .html(response);
    $("#task-tracking-stats-modal").modal("toggle");
  });
}

function init_task_tracking_stats_events() {
  if (init_task_tracking_stats_events.bound) return;
  init_task_tracking_stats_events.bound = true;
  // Task single moda tracking stats close
  $("body").on("click", ".close-task-stats", function () {
    $("#task-tracking-stats-modal").modal("hide");
  });

  // Remove tracking status div because its appended automatically to the DOM on each click
  $("body").on("hidden.bs.modal", "#task-tracking-stats-modal", function () {
    $("#tracking-stats").remove();
  });

  // Task modal single chart for logged time by assigned users
  $("body").on("show.bs.modal", "#task-tracking-stats-modal", function () {
    var tracking_chart_selector = $("body").find("#task-tracking-stats-chart");
    setTimeout(function () {
      if (typeof taskTrackingChart != "undefined") {
        taskTrackingChart.destroy();
      }
      taskTrackingChart = new Chart(tracking_chart_selector, {
        type: "line",
        data: taskTrackingStatsData,
        options: {
          legend: {
            display: false,
          },
          responsive: true,
          maintainAspectRatio: false,
          tooltips: {
            enabled: true,
            mode: "single",
            callbacks: {
              label: function (tooltipItems, data) {
                return decimalToHM(tooltipItems.yLabel);
              },
            },
          },
          scales: {
            yAxes: [
              {
                ticks: {
                  beginAtZero: true,
                  min: 0,
                  userCallback: function (label, index, labels) {
                    return decimalToHM(label);
                  },
                },
              },
            ],
          },
        },
      });
    }, 800);
  });
}

function init_rel_tasks_table(rel_id, rel_type, selector) {
  if (typeof selector == "undefined") {
    selector = ".table-rel-tasks";
  }
  var $selector = $("body").find(selector);
  if ($selector.length === 0) {
    return;
  }

  var TasksServerParams = {},
    tasksRelationTableNotSortable = [0], // bulk actions
    TasksFilters;

  TasksFilters = $("body").find(
    "._hidden_inputs._filters._tasks_filters input"
  );

  $.each(TasksFilters, function () {
    TasksServerParams[$(this).attr("name")] =
      '[name="' + $(this).attr("name") + '"]';
  });

  // Related task filter - used in customer profile
  TasksServerParams['tasks_related_to'] = '[name="tasks_related_to"]'

  var url = admin_url + "tasks/init_relation_tasks/" + rel_id + "/" + rel_type;

  if ($selector.attr("data-new-rel-type") == "project") {
    url += "?bulk_actions=true";
  }

  initDataTable(
    $selector,
    url,
    tasksRelationTableNotSortable,
    tasksRelationTableNotSortable,
    TasksServerParams,
    [$selector.find("th.duedate").index(), "asc"]
  );
}
