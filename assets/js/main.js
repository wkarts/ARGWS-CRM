/* jshint expr: true */
/* jshint sub:true */
/* jshint loopfunc:true */

/**
 * Core Admin JS Files
 * Not recommened to edit this file directly if you plan to upgrade the script when new versions are released.
 * Use hooks to inject custom javascript code
 */

$(window).on("load", function () {
  init_btn_with_tooltips();
});

// Set datatables error throw console log



if (app.options.enable_google_picker == "1") {
  $.fn.googleDrivePicker.defaults.clientId = app.options.google_client_id;
  $.fn.googleDrivePicker.defaults.developerKey = app.options.google_api;
}

// Set dropzone not auto discover
NativeAdminRuntime.configureUploads();

// Check for desktop notifications permissions
if ("Notification" in window && app.options.desktop_notifications == "1") {
  Notification.requestPermission();
}

// Predefined global variables
var original_top_search_val,
  table_leads,
  table_activity_log,
  table_estimates,
  table_invoices,
  table_tasks,
  tab_active = get_url_param("tab"),
  tab_group = get_url_param("group"),
  side_bar = $("#side-menu"),
  content_wrapper = $("#wrapper"),
  setup_menu = $("#setup-menu-wrapper"),
  menu_href_selector,
  calendar_selector = $("#calendar"),
  notifications_wrapper = $("#header").find("li.notifications-wrapper"),
  doc_initial_title = document.title,
  newsfeed_posts_page = 0,
  track_load_post_likes = 0,
  track_load_comment_likes = 0,
  post_likes_total_pages = 0,
  comment_likes_total_pages = 0,
  postid = 0,

  setup_menu_item = $("#setup-menu-item");

// Custom deselect all on bootstrap ajax select input
$("body").on("loaded.bs.select change", "select.ajax-search", function (e) {
  var val = $(this).selectpicker("val");

  if (Array.isArray(val) && val.length == 0) {
    return;
  }

  if (!val || $(this).is(":disabled")) {
    return;
  }

  var $elmWrapper = $(this).parents(".bootstrap-select.ajax-search");
  if ($elmWrapper.find(".ajax-clear-values").length === 0) {
    var id = $(this).attr("id");
    var dropdownToggle = $elmWrapper
      .addClass("ajax-remove-values-option")
      .find("button.dropdown-toggle");
    dropdownToggle.after(
      '<span class="pointer ajax-clear-values" onclick="deselect_ajax_search(this); return false;" data-id="' +
        id +
        '"></span>'
    );
  }
});

// On render select remove the placeholder
$("body").on("rendered.bs.select", "select", function () {
  $(this).parents().removeClass("select-placeholder");
  $(this)
    .parents(".form-group")
    .find(".select-placeholder")
    .removeClass("select-placeholder");
});

$("body").on("loaded.bs.select", "select", function () {
  if ($(this).data("toggle") == 1) {
    $(this).selectpicker("toggle");
  }
});

// Init bootstrap selectpicker
$("body").on("loaded.bs.select", "._select_input_group", function (e) {
  $(this)
    .parents(".form-group")
    .find(".input-group-select .input-group-addon")
    .css("opacity", "1");
});

NativeAdminNavigation.init({isMobile:is_mobile,isRTL:isRTL,persistSetup:function(state){requestGet("misc/set_setup_menu_"+state);}});

$(function () {
  // Add notifications indicator on document title
  if (totalUnreadNotifications > 0) {
    document.title = "(" + totalUnreadNotifications + ") " + doc_initial_title;
  }

  $(".screen-options-btn").on("click", function () {
    $(".screen-options-area").slideToggle();
  });

  // Make the deprecated errors more clear
  if ($("body").hasClass("has-deprecated-errors")) {
    (function () {
      var $errors = $("div:contains('A PHP Error was encountered')");
      var totalErrorsHeight = 0;
      $.each($errors, function () {
        totalErrorsHeight += $(this).outerHeight();
        $(this).css("background", "#fff");
      });
      if (totalErrorsHeight > 0) {
        $("#menu, #setup-menu-wrapper").css(
          "top",
          totalErrorsHeight + 70 + "px"
        );
      }
    })();
  }

  // Form entities where HTML is available, causing issue on mod_security servers, will entitiy encode then before saving in database entiity decode
  $("form")
    .has('[data-entities-encode="true"]')
    .on("submit.app.entity", function (e) {
      if ($(this).validate().checkForm()) {
        $.each($('[data-entities-encode="true"]'), function () {
          if (!$(this).hasClass("_entities-processed")) {
            $(this).val(htmlEntities($(this).val()));
            $(this).addClass("_entities-processed");
          }
        });
      }
    });

  /** Create New Customer **/
  add_hotkey("Shift+C", function () {
    var $leadModal = $("#lead-modal");
    var $taskModal = $("#task-modal");
    if ($leadModal.is(":visible")) {
      convert_lead_to_customer($leadModal.find('input[name="leadid"]').val());
    } else if ($taskModal.is(":visible")) {
      var $taskCommentsWrapper = $taskModal.find(".tasks-comments");
      if (!$taskCommentsWrapper.is(":visible")) {
        $taskCommentsWrapper.css("display", "block");
      }
      init_new_task_comment();
    } else {
      window.location.href = admin_url + "clients/client";
    }
  });

  /** Create New Invoice **/
  add_hotkey("Shift+I", function () {
    window.location.href = admin_url + "invoices/invoice";
  });

  /** Create New Estimate **/
  add_hotkey("Shift+E", function () {
    var $leadModal = $("#lead-modal");
    var $taskModal = $("#task-modal");
    if (!$leadModal.is(":visible") && !$taskModal.is(":visible")) {
      window.location.href = admin_url + "estimates/estimate";
    } else {
      if ($leadModal.is(":visible")) {
        $("a[lead-edit]").click();
      } else if ($taskModal.is(":visible")) {
        edit_task(
          $taskModal.find("[data-task-single-id]").attr("data-task-single-id")
        );
      }
    }
  });

  /** Marks task as finished when modal is opened **/
  add_hotkey("Shift+F", function () {
    var $taskModal = $("#task-modal");
    if ($taskModal.is(":visible")) {
      var $taskSingleBody = $taskModal.find("[data-task-single-id]");
      if ($taskSingleBody.attr("data-status") != 5) {
        mark_complete($taskSingleBody.attr("data-task-single-id"));
      }
    }
  });

  /** Create New Proposal **/
  add_hotkey("Ctrl+Shift+P", function () {
    window.location.href = admin_url + "proposals/proposal";
  });
  /** Create New Expense **/
  add_hotkey("Ctrl+Shift+E", function () {
    window.location.href = admin_url + "expenses/expense";
  });

  /** Create New Lead **/
  add_hotkey("Shift+L", function () {
    init_lead();
  });
  /** Create New Task **/
  add_hotkey("Shift+T", function () {
    var $newTaskRelationBtn = $(".new-task-relation");
    if ($newTaskRelationBtn.length > 0) {
      new_task(
        admin_url +
          "tasks/task?rel_id=" +
          $newTaskRelationBtn.attr("data-rel-id") +
          "&rel_type=" +
          $newTaskRelationBtn.attr("data-rel-type")
      );
    } else if ($("body").hasClass("project")) {
      new_task(
        admin_url + "tasks/task?rel_id=" + project_id + "&rel_type=project"
      );
    } else {
      new_task();
    }
  });
  /** Create New Project **/
  add_hotkey("Shift+P", function () {
    window.location.href = admin_url + "projects/project";
  });
  /** Create New Ticket **/
  add_hotkey("Shift+S", function () {
    window.location.href = admin_url + "tickets/add";
  });
  /** Create New Staff Member **/
  add_hotkey("Ctrl+Shift+S", function () {
    window.location.href = admin_url + "staff/member";
  });

  /** User logout **/
  add_hotkey("Ctrl+Shift+L", function () {
    logout();
  });

  /**
   * List shortcuts
   */

  /** Go to dashboard **/
  add_hotkey("Alt+D", function () {
    window.location.href = admin_url;
  });
  /** List Customers **/
  add_hotkey("Alt+C", function () {
    window.location.href = admin_url + "clients";
  });
  /** List Tasks **/
  add_hotkey("Alt+T", function () {
    window.location.href = admin_url + "tasks/list_tasks";
  });
  /** List Invoices **/
  add_hotkey("Alt+I", function () {
    window.location.href = admin_url + "invoices/list_invoices";
  });
  /** List Estimates **/
  add_hotkey("Alt+E", function () {
    window.location.href = admin_url + "estimates/list_estimates";
  });
  /** List Projects **/
  add_hotkey("Alt+P", function () {
    window.location.href = admin_url + "projects";
  });
  /** List Leads **/
  add_hotkey("Alt+L", function () {
    window.location.href = admin_url + "leads";
  });
  /** List Tickets **/
  add_hotkey("Ctrl+Alt+T", function () {
    window.location.href = admin_url + "tickets";
  });
  /** List Expenses **/
  add_hotkey("Ctrl+Alt+E", function () {
    window.location.href = admin_url + "expenses/list_expenses";
  });

  /** Sales Report **/
  add_hotkey("Alt+R", function () {
    window.location.href = admin_url + "reports/sales";
  });

  /** Settings **/
  add_hotkey("Alt+S", function () {
    window.location.href = admin_url + "settings";
  });

  /** Top Search Focus **/
  add_hotkey("Shift+K", function () {
    $("#search_input").focus();
  });

  /* Focus on seacrh on first datatable found in the DOM */
  add_hotkey("Shift+D", function () {
    $("body .dataTables_wrapper")
      .eq(0)
      .find(".dataTables_filter input")
      .focus();
  });

  add_hotkey("Shift+F", function () {
    $(".hide-menu").click();
  });

  // Init the shortcuts
  $.Shortcuts.start();

  /** TinyMCE modal fix */
  $(document).on("focusin", function (e) {
    if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
      e.stopImmediatePropagation();
    }
  });

  // Custom option to show setup menu item only on hover, not applied on mobile
  if (app.options.show_setup_menu_item_only_on_hover == 1 && !is_mobile()) {
    side_bar.hover(
      function () {
        setTimeout(function () {
          setup_menu_item.css("display", "block");
        }, 200);
      },
      function () {
        setTimeout(function () {
          setup_menu_item.css("display", "none");
        }, 1000);
      }
    );
  }

  // Store navTabs, used multiple times.
  var $navTabs = $("body").find("ul.nav-tabs");
  // Check for active tab if any found in url so we can set this tab to active - Tab active is defined on top
  if (tab_active) {
    $navTabs.find('[href="#' + tab_active + '"]').click();
  }
  // Check for active tab groups (this is custom made) and not related to boostrap - tab_group is defined on top
  if (tab_group) {
    // Do not track bootstrap default tabs
    $navTabs.find("li").not('[role="presentation"]').removeClass("active");
    // Add the class active to this group manually so the tab can be highlighted
    $navTabs
      .find('[data-group="' + tab_group + '"]')
      .parents("li")
      .addClass("active");
  }
  // Set moment locale
  moment.locale(app.locale);
  // Set timezone locale
  moment().tz(app.options.timezone).format();

  // Init tinymce editors
  init_editor();
  // Dont close dropdown on timer top click
  $("body").on(
    "click",
    "#started-timers-top,.popover-top-timer-note",
    function (e) {
      e.stopPropagation();
    }
  );
  NativeAdminWidgets.initTags();
  NativeAdminWidgets.initTables();

  /* Custom fields hyperlink */
  custom_fields_hyperlink();
  NativeAdminWidgets.initVisuals();
  NativeAdminWidgets.initDates();

  NativeAdminWidgets.initSelects();
  // Optimize body
  set_body_small();
  // Validate all form for reminders
  init_form_reminder();
  // On document read check and init for client ajax-search


  // Sidebar, setup and header navigation are initialized by NativeAdminNavigation.

  if (app.browser == "safari") {
    $("body").on("input", ".bootstrap-select .bs-searchbox input", function () {
      $(this).trigger("keyup");
    });
  }

  // Optimize wrapper height
  mainWrapperHeightFix();

  // Init scrollable tabs
  init_tabs_scrollable();

  // Refresh top timers on click
  $("#top-timers").on("click", function () {
    init_timers();
  });

  NativeAdminSearch.init({history:app.user_recent_searches,browser:app.browser,language:app.user_language,
    search:function(q){return $.post(admin_url+'misc/search',{q:q});},
    remove:function(row,index){return requestGet('misc/remove_recent_search/'+index);}
  });

  // Format timesheet duration type and do necesary checkings
  $("body").on("blur", "#timesheet_duration", function () {
    var that = $(this);
    var pattern = /[^0-9:]/gi;
    var val = $(this).val();
    val = val.replace(pattern, "");
    if (val.indexOf(":") > -1) {
      var duration_array = val.split(":");

      if (duration_array[0].length === 0) {
        duration_array[0] = "00";
      }

      if (duration_array[1] >= 60) {
        var subtr = Math.floor(parseInt(duration_array[1] / 60));
        duration_array[0] = subtr + parseInt(duration_array[0]);
        duration_array[1] = duration_array[1] - subtr * 60;
      }

      if (duration_array[0].toString().length === 1) {
        duration_array[0] = "0" + duration_array[0];
      }

      if (duration_array[1].toString().length === 1) {
        duration_array[1] = "0" + duration_array[1];
      } else if (duration_array[1].toString().length === 0) {
        duration_array[1] = "00";
      }

      val = duration_array[0] + ":" + duration_array[1];
    } else if (val.length === 1 && val.indexOf(":") === -1) {
      val = "0" + val + ":" + "00";
    } else if (val.length >= 2 && val.indexOf(":") === -1) {
      val = val + ":" + "00";
    }
    val = val == "00:00" ? "" : val;
    that.val(val);
  });

  // Switching timesheet enter type
  $("body").on("click", ".timesheet-toggle-enter-type", function (e) {
    e.preventDefault();
    var $switch_to = $(this)
      .find("span.switch-to")
      .removeClass("switch-to")
      .addClass("hide");
    $(this)
      .find("span")
      .not($switch_to)
      .removeClass("hide")
      .addClass("switch-to");
    $(".timesheet-start-end-time, .timesheet-duration").toggleClass("hide");
    $(".timesheet-start-end-time input").val("");
    $(".timesheet-duration input").val("");
  });

  /* Insert new checklist items on enter press */
  $("body").on(
    "keypress",
    'textarea[name="checklist-description"]',
    function (event) {
      if (event.which == "13") {
        var that = $(this);
        update_task_checklist_item(that).done(function () {
          add_task_checklist_item(that.attr("data-taskid"));
        });
        return false;
      }
    }
  );

  /* Update tasks checklist items when focusing out */
  $("body").on(
    "blur paste",
    'textarea[name="checklist-description"]',
    function () {
      update_task_checklist_item($(this));
    }
  );

  $("body").on(
    "show.bs.select",
    "select.checklist-items-template-select",
    _make_task_checklist_items_deletable
  );
  $("body").on(
    "refreshed.bs.select",
    "select.checklist-items-template-select",
    _make_task_checklist_items_deletable
  );

  // trigger jquery validation validate
  $("body").on("changed.bs.select", "select", function (e) {
    $(e.currentTarget).trigger("keyup.validate");
  });

  // Can't update if no values selected, no $_POST data send
  $("body").on(
    "changed.bs.select",
    "select.custom-field-multi-select",
    function (e) {
      var val = $(this).val();
      $(this)
        .find('option[value=""]')
        .prop("selected", val.length === 0 ? true : false);
      $(this).selectpicker("refresh");
    }
  );

  // Task single modal inline changes, eq start date, due date...
  $("body").on("change", ".task-single-inline-field", function () {
    var singleDateInputs = $("body").find(".task-single-inline-field");
    var data = {};
    $.each(singleDateInputs, function () {
      var name = $(this).attr("name");
      var val = $(this).val();
      var $parentwrap = $(this).parents(".task-single-inline-wrap");
      if (name == "startdate" && val === "") {
        $parentwrap.addClass("text-danger");
      } else if (name == "startdate" && val !== "") {
        $parentwrap.removeClass("text-danger");
      }
      if ((name == "startdate" && val !== "") || name != "startdate") {
        data[$(this).attr("name")] = val;
        // Name is required
        if (name != "startdate" && val === "") {
          $parentwrap.css("opacity", 0.5);
        } else {
          $parentwrap.css("opacity", 1);
        }
      }
    });
    var $taskModal = $("#task-modal");
    var dTaskID = $taskModal
      .find("[data-task-single-id]")
      .attr("data-task-single-id");
    $.post(admin_url + "tasks/task_single_inline_update/" + dTaskID, data);
  });

  // When user select from checklist items template add this template
  $("body").on("change", "#task-modal #checklist_items_templates", function () {
    var val = $(this).val();
    var valTemplate = $(this)
      .find('option[value="' + val + '"]')
      .html()
      .trim();
    if (valTemplate !== "") {
      var $taskModal = $("#task-modal");
      add_task_checklist_item(
        $taskModal.find("[data-task-single-id]").attr("data-task-single-id"),
        valTemplate
      );
      $(this).selectpicker("val", "");
    }
  });

  // Used on task comment date href, can be used directly to add the link in browser for scroll.
  $("body").on("click", ".task-date-as-comment-id", function (e) {
    e.preventDefault();
    var task_comment_temp = $(this).attr("href").split("#");
    var comment_position = $(
      "#" + task_comment_temp[task_comment_temp.length - 1]
    ).position();
    $("#task-modal").scrollTop(comment_position.top);
  });

  // Search by tags from the tables for any tag clicked.
  $("body").on(
    "click",
    "table.dataTable tbody .tags-labels .label-tag",
    function () {
      $(this)
        .parents("table")
        .DataTable()
        .search($(this).find(".tag").text())
        .draw();
      $("div.dataTables_filter input").focus();
    }
  );

  // Search by customer groups from the tables for any group clicked.
  $("body").on(
    "click",
    "table.dataTable tbody .customer-group-list",
    function () {
      $(this).parents("table").DataTable().search($(this).text()).draw();
      $("div.dataTables_filter input").focus();
    }
  );

  // Permissions change, apply necessary action to disable OWN or VIEW OWN
  $("[data-can-view-own], [data-can-view]").on("change", function () {
    var is_own_attr = $(this).attr("data-can-view-own");
    view_chk_selector = $(this)
      .parents("tr")
      .find(
        "td input[" +
          (typeof is_own_attr !== typeof undefined && is_own_attr !== false
            ? "data-can-view"
            : "data-can-view-own") +
          "]"
      );

    if (view_chk_selector.data("not-applicable") == true) {
      return;
    }

    view_chk_selector.prop("checked", false);
    view_chk_selector.prop("disabled", $(this).prop("checked") === true);
  });

  // Init single task data
  if (typeof taskid !== "undefined" && taskid !== "") {
    init_task_modal(taskid);
  }

  // Task checklist mark as complete/incomplete
  $("body").on("change", 'input[name="checklist-box"]', function () {
    if ($("body").hasClass("native-company-admin")) {
      var checkbox = $(this), item = checkbox.closest('.checklist'), checked = checkbox.prop('checked');
      native_task_checklist_command($('#checklist-items'), 'complete', {item_id:item.attr('data-checklist-id'), item_revision:item.attr('data-checklist-item-revision'), finished:checked ? '1' : '0'}).done(function () {
        recalculate_checklist_items_progress();
      }).fail(function () { checkbox.prop('checked', !checked); alert_float('danger', 'Não foi possível atualizar o item. Atualize a tarefa e tente novamente.'); });
      return;
    }
    requestGet(
      admin_url +
        "tasks/checkbox_action/" +
        $(this).parents(".checklist").data("checklist-id") +
        "/" +
        ($(this).prop("checked") === true ? 1 : 0)
    );
    recalculate_checklist_items_progress();
    if (
      $(this).prop("checked") &&
      $('button[data-hide="1"]').hasClass("hide")
    ) {
      $(this).closest(".checklist ").addClass("hide");
    }
  });

  // Fix task checklist content textarea height
  $("body").on(
    "keyup paste click",
    "textarea[name='checklist-description']",
    function (e) {
      do_task_checklist_items_height($(this));
    }
  );

  // On click on task comment textarea make it tinymce, by default is plain textarea
  $("body").on("click focus", "#task_comment", function (e) {
    init_new_task_comment();
  });

  // Delete task timesheet from the task single modal
  $("body").on("click", ".task-single-delete-timesheet", function (e) {
    e.preventDefault();
    if (confirm_delete()) {
      var _delete_timesheet_task_id = $(this).data("task-id");
      requestGet($(this).attr("href")).done(function (response) {
        init_task_modal(_delete_timesheet_task_id);
        setTimeout(function () {
          reload_tasks_tables();
          init_timers();
        }, 20);
      });
    }
  });

  $("#task-modal").on(
    "change",
    'input[name="timesheet_start_time"], input[name="timesheet_end_time"], input[name="timesheet_duration"]',
    function () {
      if (
        $(this).val() &&
        $(this).parents(".form-group").hasClass("has-error")
      ) {
        $(this).parents(".form-group").removeClass("has-error");
      }
    }
  );

  // New timesheet add manually from task single modal
  $("body").on("click", ".task-single-add-timesheet", function (e) {
    e.preventDefault();

    var start_time_input = $("body").find(
      '#task-modal input[name="timesheet_start_time"]'
    );
    var end_time_input = $("body").find(
      '#task-modal input[name="timesheet_end_time"]'
    );
    var duration_input = $("body").find(
      '#task-modal input[name="timesheet_duration"]'
    );

    var start_time = start_time_input.val();
    var end_time = end_time_input.val();
    var duration = duration_input.val();

    start_time === "" &&
      start_time_input.parents(".form-group").addClass("has-error");
    end_time === "" &&
      end_time_input.parents(".form-group").addClass("has-error");
    duration === "" &&
      duration_input.parents(".form-group").addClass("has-error");

    if ((start_time !== "" && end_time !== "") || duration !== "") {
      var data = {};
      data.timesheet_duration = duration;
      data.start_time = start_time;
      data.end_time = end_time;
      data.timesheet_task_id = $(this).data("task-id");
      data.note = $("body").find("#task_single_timesheet_note").val();
      data.timesheet_staff_id = $("body")
        .find('#task-modal select[name="single_timesheet_staff_id"]')
        .val();
      $.post(admin_url + "tasks/log_time", data).done(function (response) {
        response = JSON.parse(response);
        if (response.success === true || response.success == "true") {
          init_task_modal(data.timesheet_task_id);
          alert_float("success", response.message);
          setTimeout(function () {
            reload_tasks_tables();
          }, 20);
        } else {
          alert_float("warning", response.message);
        }
      });
    }
  });

  // Copy task href/button event.
  $("body").on("click", ".copy_task_action", function () {
    var data = {};
    $(this).prop("disabled", true);
    data.copy_from = $(this).data("task-copy-from");
    data.copy_task_assignees = $("body")
      .find("#copy_task_assignees")
      .prop("checked");
    data.copy_task_followers = $("body")
      .find("#copy_task_followers")
      .prop("checked");
    data.copy_task_checklist_items = $("body")
      .find("#copy_task_checklist_items")
      .prop("checked");
    data.copy_task_attachments = $("body")
      .find("#copy_task_attachments")
      .prop("checked");
    data.copy_task_status = $("body")
      .find('input[name="copy_task_status"]:checked')
      .val();
    $.post(admin_url + "tasks/copy", data).done(function (response) {
      response = JSON.parse(response);
      if (response.success === true || response.success == "true") {
        var $taskModal = $("#_task_modal");
        if ($taskModal.is(":visible")) {
          $taskModal.modal("hide");
        }
        init_task_modal(response.new_task_id);
        reload_tasks_tables();
      }
      alert_float(response.alert_type, response.message);
    });
    return false;
  });

  // Creates new task in specific milestones, the milestone is auto selected on the new task modal
  $("body").on("click", ".new-task-to-milestone", function (e) {
    e.preventDefault();
    var milestone_id = $(this)
      .parents(".milestone-column")
      .data("col-status-id");
    new_task(
      admin_url +
        "tasks/task?rel_type=project&rel_id=" +
        project_id +
        "&milestone_id=" +
        milestone_id
    );
    $('body [data-toggle="popover"]').popover("hide");
  });

  // On shown task add/edit modal
  $("body").on("shown.bs.modal", "#_task_modal", function (e) {
    if (!$(e.currentTarget).hasClass("edit")) {
      $("body").find("#_task_modal #name").focus();
    } else {
      if ($(this).find(".tinymce-task").val().trim() !== "") {
        init_editor(".tinymce-task", {
          height: 200,
        });
      }
    }
    init_tags_inputs();
  });

  // Remove the tinymce description task editor
  $("body").on("hidden.bs.modal", "#_task_modal", function () {
    tinyMCE.remove(".tinymce-task");
    // Clear _ticket_message from single tickets in case user tried to convert ticket to task to prevent populating the fields again with the last ticket message click
    if (typeof _ticket_message != "undefined") {
      _ticket_message = undefined;
    }

    if (
      $(this).attr("data-lead-id") != undefined &&
      !$(this).attr("data-task-created")
    ) {
      init_lead($(this).attr("data-lead-id"));
    }

    destroy_dynamic_scripts_in_element($("body #_task_modal"));

    $("#_task").empty();
  });

  // Don't allow the task modal to close if lightbox is visible in for the task attachments
  // Used when user hit the ESC button
  // Empty task data
  $("body").on("hide.bs.modal", "#task-modal", function () {
    if ($("#lightbox").is(":visible") == true) {
      return false;
    }
    if (typeof taskAttachmentDropzone != "undefined") {
      taskAttachmentDropzone.destroy();
    }
    var viewDescriptionEditor = tinyMCE.get("#task_view_description");
    if (viewDescriptionEditor) {
      // Invoke the blur event before the modal is closed in case
      // there are unsaved changes.
      // see edit_task_inline_description function
      viewDescriptionEditor.blur();

      tinyMCE.remove("#task_view_description");
    }
  });

  // Prevent closing the modal if comment is being written
  $("body").on("hide.bs.modal", "#task-modal", function (e) {
    tinymce.get().forEach(function (editor) {
      if (
        editor.id.includes("task_comment") &&
        editor.getContent() !== "" &&
        $(editor.editorContainer).is(":visible")
      ) {
        e.preventDefault();
      }
    });
  });

  // Prevent closing the modal if timesheet is being recorded/edited
  $("body").on("hide.bs.modal", "#task-modal", function (e) {
    var taskModal = $("#task-modal");
    if (taskModal.find(".timesheet-edit").is(":visible")) {
      e.preventDefault();
    }

    if (taskModal.find(".add-timesheet").is(":visible")) {
      var timesheetStartTime = taskModal.find("#timesheet_start_time").val();
      var timesheetEndTime = taskModal.find("#timesheet_end_time").val();
      var timesheetDuration = taskModal.find("#timesheet_duration").val();
      var timesheetNote = taskModal.find("#task_single_timesheet_note").val();

      if (
        timesheetStartTime !== "" ||
        timesheetEndTime !== "" ||
        timesheetDuration !== "" ||
        timesheetNote !== ""
      ) {
        e.preventDefault();
      }
    }
  });

  // On task single modal hidden remove all html data
  $("body").on("hidden.bs.modal", "#task-modal", function () {
    // Clear memory leak
    destroy_dynamic_scripts_in_element($(this));

    $(this).find(".data").empty();
  });

  // On task single modal shown perform few actions
  $("body").on("shown.bs.modal", "#task-modal", function () {
    do_task_checklist_items_height();
    init_tags_inputs();
    fix_task_modal_left_col_height();
    $(document).off("focusin.modal");
    var current_url = window.location.href;
    if (current_url.indexOf("#comment_") > -1) {
      var task_comment_id = current_url.split("#comment_");
      task_comment_id = task_comment_id[task_comment_id.length - 1];
      $('[data-task-comment-href-id="' + task_comment_id + '"]').click();
    }
  });

  // On focus out on the taks modal single update the tags in case changes are found
  $("body").on("blur", "#task-modal ul.tagit li.tagit-new input", function () {
    setTimeout(function () {
      task_single_update_tags();
    }, 100);
  });

  // Assign task to staff member
  $("body").on("change", 'select[name="select-assignees"]', function () {
    $("body").append('<div class="dt-loader"></div>');
    var data = {};
    data.assignee = $('select[name="select-assignees"]').val();
    if (data.assignee !== "") {
      data.taskid = $(this).attr("data-task-id");
      $.post(admin_url + "tasks/add_task_assignees", data).done(function (
        response
      ) {
        $("body").find(".dt-loader").remove();
        response = JSON.parse(response);
        reload_tasks_tables();
        _task_append_html(response.taskHtml);
      });
    }
  });

  // Add follower to task
  $("body").on("change", 'select[name="select-followers"]', function () {
    var data = {};
    data.follower = $('select[name="select-followers"]').val();
    if (data.follower !== "") {
      data.taskid = $(this).attr("data-task-id");
      $("body").append('<div class="dt-loader"></div>');
      $.post(admin_url + "tasks/add_task_followers", data).done(function (
        response
      ) {
        response = JSON.parse(response);
        $("body").find(".dt-loader").remove();
        _task_append_html(response.taskHtml);
      });
    }
  });

  init_task_tracking_stats_events();

  // In lead modal issue with reminder modal height
  $("body").on("shown.bs.modal", "#sync_data_proposal_data", function () {
    if ($("#sync_data_proposal_data").data("rel-type") == "lead") {
      $("#lead-modal .data")
        .eq(0)
        .css(
          "height",
          $("#sync_data_proposal_data .modal-content").height() + 80 + "px"
        )
        .css("overflow-x", "hidden");
    }
  });

  // Remove on lead modal reminder inline style
  $("body").on("hidden.bs.modal", "#sync_data_proposal_data", function () {
    if ($("#sync_data_proposal_data").data("rel-type") == "lead") {
      $("#lead-modal .data").prop("style", "");
    }
  });

  // Maybe lead ID passed from url?
  if (typeof openLeadID != "undefined" && openLeadID !== "") {
    init_lead(openLeadID, get_url_param("edit") ? true : false);
  }

  // Status color change
  $("body").on("click", ".leads-kan-ban .cpicker", function () {
    var color = $(this).data("color");
    var status_id = $(this).parents(".panel-heading").data("status-id");
    $.post(admin_url + "leads/change_status_color", {
      color: color,
      status_id: status_id,
    });
  });

  // Lead edit toggle view/edit
  $("body").on("click", "[lead-edit]", function (e) {
    e.preventDefault();
    var $leadEdit = $("body .lead-edit");
    $("body .lead-view").toggleClass("hide");
    $leadEdit.toggleClass("hide");
    if (!$leadEdit.hasClass("hide")) {
      var $address = $("#lead-modal").find("#address");
      var scrollHeight = $address[0].scrollHeight;
      if ($address.is("textarea")) {
        $address.height(0).height(scrollHeight - 15);
        $address.css("padding-top", "9px");
      }
    }
  });

  // Creates new lead with pre-selected status from leads kan ban
  $("body").on("click", ".new-lead-from-status", function (e) {
    e.preventDefault();
    var status_id = $(this).parents(".kan-ban-col").data("col-status-id");
    init_lead_modal_data(
      undefined,
      admin_url + "leads/lead?status_id=" + status_id
    );
    $('body [data-toggle="popover"]').popover("hide");
  });

  // When converting lead to customer, custom fields merging options.
  $("body").on("change", "input.include_leads_custom_fields", function () {
    var val = $(this).val();
    var fieldid = $(this).data("field-id");
    val == 2
      ? $("#merge_db_field_" + fieldid).removeClass("hide")
      : $("#merge_db_field_" + fieldid).addClass("hide");
    val == 3
      ? $("#merge_db_contact_field_" + fieldid)
      : $("#merge_db_contact_field_" + fieldid).addClass("hide");
  });

  // Check if calendar exists in the DOM and init.
  if (window.NativeAdminCalendar) NativeAdminCalendar.init();

  // On select with name tax apply necessary actions if tax2 exists too
  $("body").on("change", 'select[name="tax"]', function () {
    var sp_tax_2 = $("body").find('select[name="tax2"]');
    var sp_tax_1 = $(this);
    if (sp_tax_1.val() !== "") {
      sp_tax_2.prop("disabled", false);
    } else {
      sp_tax_2.prop("disabled", true);
      if (sp_tax_2.val() !== "") {
        sp_tax_1.selectpicker("val", sp_tax_2.val());
        sp_tax_2.val("");
        sp_tax_1.selectpicker("refresh");
      }
    }
    sp_tax_2.selectpicker("refresh");
  });

  $("body").on("click", "#invoice_create_credit_note", function (e) {
    if ($(this).attr("data-status") == 2) {
      return true;
    } else {
      var $m = $("#confirm_credit_note_create_from_invoice");
      $m.modal("show");
      $m.find("#confirm-invoice-credit-note").attr(
        "href",
        $(this).attr("href")
      );
      e.preventDefault();
    }
  });

  $("body").on(
    "change blur",
    ".apply-credits-to-invoice .apply-credits-field",
    function () {
      var $applyCredits = $("#apply_credits");
      var $amountInputs = $applyCredits.find("input.apply-credits-field");
      var total = 0;
      var creditsRemaining = $applyCredits.attr("data-credits-remaining");

      $.each($amountInputs, function () {
        if ($(this).valid() === true) {
          var amount = $(this).val();
          amount = parseFloat(amount);
          if (!isNaN(amount)) {
            total += amount;
          } else {
            $(this).val(0);
          }
        }
      });

      $applyCredits.find("#credits-alert").remove();
      $applyCredits.find(".amount-to-credit").html(format_money(total));
      if (creditsRemaining < total) {
        $(".credits-table").before(
          $("<div/>", {
            id: "credits-alert",
            class: "alert alert-danger",
          }).html(
            app.lang.credit_amount_bigger_then_credit_note_remaining_credits
          )
        );
        $applyCredits.find('[type="submit"]').prop("disabled", true);
      } else {
        $applyCredits
          .find(".credit-note-balance-due")
          .html(format_money(creditsRemaining - total));
        $applyCredits.find('[type="submit"]').prop("disabled", false);
      }
    }
  );

  $('body').on('change', '#main-optional', function(e){
    if(e.target.checked){
      $('#main-optional-choosen').parent().css('display', "block");
    } else {
      $('#main-optional-choosen').parent().css('display', "none");
    }
  });

  $('body').on('change', '.optional-item-checkbox', function(e){
    if(e.target.checked){
      $(e.target).parent().next().css('display', "block")
    } else {
       $(e.target).parent().next().css('display', "none")
    }
    calculate_total();
  });

  $('body').on('change', '.optional-choose-item-checkbox', function(e){
    calculate_total();
  });

  $("body").on(
    "change blur",
    ".apply-credits-from-invoice .apply-credits-field",
    function () {
      var $applyCredits = $("#apply_credits");
      var $amountInputs = $applyCredits.find("input.apply-credits-field");
      var total = 0;
      var invoiceBalanceDue = $applyCredits.attr("data-balance-due");

      $.each($amountInputs, function () {
        if ($(this).valid() === true) {
          var amount = $(this).val();
          amount = parseFloat(amount);
          if (!isNaN(amount)) {
            total += amount;
          } else {
            $(this).val(0);
          }
        }
      });

      $applyCredits.find("#credits-alert").remove();
      $applyCredits.find(".amount-to-credit").html(format_money(total));
      if (total > invoiceBalanceDue) {
        $(".credits-table").before(
          $("<div/>", {
            id: "credits-alert",
            class: "alert alert-danger",
          }).html(app.lang.credit_amount_bigger_then_invoice_balance)
        );
        $applyCredits.find('[type="submit"]').prop("disabled", true);
      } else {
        $applyCredits
          .find(".invoice-balance-due")
          .html(format_money(invoiceBalanceDue - total));
        $applyCredits.find('[type="submit"]').prop("disabled", false);
      }
    }
  );

  // Leads integrations notify type
  $('input[name="notify_type"]').on("change", function () {
    var val = $('input[name="notify_type"]:checked').val();
    var specific_staff_notify = $("#specific_staff_notify");
    var role_notify = $("#role_notify");
    if (val == "specific_staff") {
      specific_staff_notify.removeClass("hide");
      role_notify.addClass("hide");
    } else if (val == "roles") {
      specific_staff_notify.addClass("hide");
      role_notify.removeClass("hide");
    } else if (val == "assigned") {
      specific_staff_notify.addClass("hide");
      role_notify.addClass("hide");
    }
  });

  // Auto focus the lead name if user is adding new lead
  $("body").on("shown.bs.modal", "#lead-modal", function (e) {
    custom_fields_hyperlink();
    if ($("body").find('#lead-modal input[name="leadid"]').length === 0) {
      $("body").find('#lead-modal input[name="name"]').focus();
    }
    init_tabs_scrollable();
    if ($("body").find(".lead-wrapper").hasClass("open-edit")) {
      $("body").find("a[lead-edit]").click();
    }
  });

  // Remove the more button for leads if there is no options in the dropdown
  // This is happening because the if statements are not checked
  $("body").on("show.bs.modal", "#lead-modal", function (e) {
    if ($("#lead-more-dropdown").find("li").length == 0) {
      $("#lead-more-btn").css("display", "none").css("pointer-events", "none");
    }
  });

  // On hidden lead modal some actions need to be operated here
  $("#lead-modal").on("hidden.bs.modal", function (event) {
    destroy_dynamic_scripts_in_element($(this));
    $(this).data("bs.modal", null);
    $("#lead_reminder_modal").html("");
    // clear the hash
    if (!$("#lead-modal").is(":visible")) {
      history.pushState(
        "",
        document.title,
        window.location.pathname + window.location.search
      );
    }
    $("body #lead-modal .datetimepicker").datetimepicker("destroy");
    if (typeof leadAttachmentsDropzone != "undefined") {
      leadAttachmentsDropzone.destroy();
    }
  });

  $("body").on("submit", "#lead-modal .consent-form", function () {
    var data = $(this).serialize();
    $.post($(this).attr("action"), data).done(function (response) {
      response = JSON.parse(response);
      init_lead_modal_data(response.lead_id);
    });
    return false;
  });
  // Set hash on modal tab change
  $("body").on("click", '#lead-modal a[data-toggle="tab"]', function () {
    if (
      this.hash == "#tab_lead_profile" ||
      this.hash == "#attachments" ||
      this.hash == "#lead_notes" ||
      this.hash == "#gdpr" ||
      this.hash == "#lead_activity"
    ) {
      window.location.hash = this.hash;
    } else {
      history.pushState(
        "",
        document.title,
        window.location.pathname + window.location.search
      );
    }
    // Lead modal backdrop is showing some issues with index, is fixed after triggering document resize
    $(document).resize();
  });

  // Manually add lead activity
  $("body").on("click", "#lead_enter_activity", function () {
    var message = $("#lead_activity_textarea").val();
    var aLeadId = $("#lead-modal").find('input[name="leadid"]').val();
    if (message === "") {
      return;
    }
    $.post(admin_url + "leads/add_activity", {
      leadid: aLeadId,
      activity: message,
    })
      .done(function (response) {
        response = JSON.parse(response);
        _lead_init_data(response, response.id);
      })
      .fail(function (data) {
        alert_float("danger", data.responseText);
      });
  });

  // Submit notes on lead modal do ajax not the regular request
  $("body").on("submit", "#lead-modal #lead-notes", function () {
    var form = $(this);
    var data = $(form).serialize();
    $.post(form.attr("action"), data)
      .done(function (response) {
        response = JSON.parse(response);
        _lead_init_data(response, response.id);
      })
      .fail(function (data) {
        alert_float("danger", data.responseText);
      });
    return false;
  });

  // Add additional server params $_POST
  var LeadsServerParams = {
    custom_view: "[name='custom_view']",
    assigned: "[name='view_assigned']",
    status: "[name='view_status[]']",
    source: "[name='view_source']",
  };

  // Init the table
  table_leads = $("table.table-leads");
  if (table_leads.length) {
    var tableLeadsConsentHeading = table_leads.find("#th-consent");
    var leadsTableNotSortable = [0];
    var leadsTableNotSearchable = [0, table_leads.find("#th-assigned").index()];

    if (tableLeadsConsentHeading.length > 0) {
      leadsTableNotSortable.push(tableLeadsConsentHeading.index());
      leadsTableNotSearchable.push(tableLeadsConsentHeading.index());
    }

    _table_api = initDataTable(
      table_leads,
      admin_url + "leads/table",
      leadsTableNotSearchable,
      leadsTableNotSortable,
      LeadsServerParams,
      [table_leads.find("th.date-created").index(), "desc"]
    );

    if (_table_api && tableLeadsConsentHeading.length > 0) {
      _table_api.on("draw", function () {
        var tableData = table_leads.find("tbody tr");
        $.each(tableData, function () {
          $(this).find("td:eq(3)").addClass("bg-neutral");
        });
      });
    }

    $.each(LeadsServerParams, function (i, obj) {
      $("select" + obj).on("change", function () {
        $("[name='view_status[]']")
          .prop("disabled", $(this).val() == "lost" || $(this).val() == "junk")
          .selectpicker("refresh");

        table_leads.DataTable().ajax.reload();
      });
    });
  }

  // When adding if lead is contacted today
  $("body").on("change", 'input[name="contacted_today"]', function () {
    var checked = $(this).prop("checked");
    var lsdc = $(".lead-select-date-contacted");
    checked == false ? lsdc.removeClass("hide") : lsdc.addClass("hide");
  });

  // Lead modal show contacted indicator input
  $("body").on("change", 'input[name="contacted_indicator"]', function () {
    var lsdc = $(".lead-select-date-contacted");
    $(this).val() == "yes" ? lsdc.removeClass("hide") : lsdc.addClass("hide");
  });

  // Custom close function for reminder modals in case is modal in modal
  $("body").on("click", ".close-reminder-modal", function () {
    $(
      ".reminder-modal-" +
        $(this).data("rel-type") +
        "-" +
        $(this).data("rel-id")
    ).modal("hide");
  });

  // Init are you sure on forms
  $("form")
    .not(
      "#single-ticket-form,#calendar-event-form,#proposal-form, #department-form"
    )
    .areYouSure();

  // For inline tinymce editors when content is blank a message is shown, on click this message should be hidden.
  $("body").on("click", ".editor-add-content-notice", function () {
    var that = $(this);
    setTimeout(function () {
      that.remove();
      tinymce.triggerSave();
    }, 500);
  });

  // Global on change for mass delete to hide all other elements for bulk actions
  $(".bulk_actions").on("change", 'input[name="mass_delete"]', function () {
    var $bulkChange = $("#bulk_change");
    if ($(this).prop("checked") === true) {
      $bulkChange.find("select").selectpicker("val", "");
      $bulkChange.addClass("hide");
      $(".mass_delete_separator, merge_tickets_checkbox").addClass("hide");
      $("#merge_tickets").prop("checked", false);
    } else {
      $bulkChange.removeClass("hide");
      $(".mass_delete_separator, merge_tickets_checkbox").removeClass("hide");
    }
  });

  // Fix for bigger items descriptions, the select is going out of the container
  $("body").on("change loaded.bs.select", "#item_select", function () {
    var selectWrapper = $(".items-wrapper .items-select-wrapper");
    $(".items-wrapper .bootstrap-select").css(
      "max-width",
      selectWrapper.width() + "px"
    );
    $(".items-wrapper .bootstrap-select").css("width", "100%");
  });

  // Send test sms
  $(".send-test-sms").on("click", function () {
    var id = $(this).data("id");
    var errorContainer = $('#sms_test_response[data-id="' + id + '"]');
    var message = $('textarea[data-id="' + id + '"]').val();
    var number = $('input.test-phone[data-id="' + id + '"]').val();
    var that = $(this);
    errorContainer.empty();
    message = message.trim();
    if (message != "" && number != "") {
      that.prop("disabled", true);
      $.post(window.location.href, {
        message: message,
        number: number,
        id: id,
        sms_gateway_test: true,
      })
        .done(function (response) {
          response = JSON.parse(response);
          if (response.success == true) {
            errorContainer.html(
              '<div class="alert alert-success no-mbot mtop15">SMS Sent Successfully!</div>'
            );
          } else {
            errorContainer.html(
              '<div class="alert alert-warning no-mbot mtop15">' +
                response.error +
                "</div>"
            );
          }
        })
        .always(function () {
          that.prop("disabled", false);
        });
    }
  });

  // Init tooltips
  $("body").tooltip({
    selector: '[data-toggle="tooltip"]',
  });

  // Init popovers
  $("body").popover({
    selector: '[data-toggle="popover"]',
  });

  // Do not close the dropdownmenu for filter when filtering
  $("body").on(
    "click",
    "._filter_data ul.dropdown-menu li a,.not-mark-as-read-inline,.not_mark_all_as_read a",
    function (e) {
      e.stopPropagation();
      e.preventDefault();
    }
  );

  // On shown for all modals
  $("body").on("shown.bs.modal", ".modal", function () {
    // Fix for all modals scroll..
    $("body").addClass("modal-open");
    // Close the top timers dropdown in case user click on some task
    $("body").find("#started-timers-top").parents("li").removeClass("open");
  });

  // On hidden for all modals
  $("body").on("hidden.bs.modal", ".modal", function (event) {
    $(".modal:visible").length && $(document.body).addClass("modal-open");
    $(this).data("bs.modal", null);
  });

  // Activity log datepicker on change
  $(".datepicker.activity-log-date").on("change", function () {
    table_activity_log.DataTable().ajax.reload();
  });

  // Import form submit
  $(".btn-import-submit").on("click", function () {
    if ($(this).hasClass("simulate")) {
      $("#import_form").append(hidden_input("simulate", true));
    }
    $("#import_form").submit();
  });

  // On mass_select all select all the availble rows in the tables.
  $("body").on("change", "#mass_select_all", function () {
    var to, rows, checked;
    to = $(this).data("to-table");

    rows = $(".table-" + to).find("tbody tr");
    checked = $(this).prop("checked");
    $.each(rows, function () {
      $($(this).find("td").eq(0)).find("input").prop("checked", checked);
    });
  });

  // Init the editor for email templates where changing data is allowed
  $("body").on("show.bs.modal", ".modal.email-template", function () {
    init_editor($(this).data("editor-id"), {
      urlconverter_callback: merge_field_format_url,
    });
  });

  // Remove the editor inited for the email sending templates where changing the email template data is allowed
  $("body").on("hidden.bs.modal", ".modal.email-template", function () {
    tinymce.remove($(this).data("editor-id"));
  });

  // Shared color picker handler lives in native-admin-calendar.js.


  NativeAdminNotifications.init({initialTitle:doc_initial_title,browser:app.browser,label:app.lang.new_notification,dismissAfter:app.options.dismiss_desktop_not_after,
    openTask:init_task_modal,openLead:init_lead,openEvent:view_event,openPost:function(id){postid=id;},
    transport:{read:function(){return requestGetJSON('misc/notifications_check');},mark:function(id,mode){
      if(mode==='seen')return $.post(admin_url+'misc/set_notifications_read');
      if(mode==='inline')return requestGet('misc/set_notification_read_inline/'+id);
      return requestGet(id===null?'misc/mark_all_notifications_as_read_inline/':'misc/set_desktop_notification_read/'+id);
    }}
  });

  // Tables
  init_table_tickets();
  init_table_announcements();
  init_table_staff_projects();

  // Ticket pipe log and system activity log
  table_activity_log = $("table.table-activity-log");
  if (table_activity_log.length) {
    var ActivityLogServerParams = [];
    ActivityLogServerParams["activity_log_date"] = '[name="activity_log_date"]';
    initDataTable(
      table_activity_log,
      window.location.href,
      "undefined",
      "undefined",
      ActivityLogServerParams,
      [1, "desc"]
    );
  }

  table_invoices = $("table.table-invoices");
  table_estimates = $("table.table-estimates");

  if (table_invoices.length > 0 || table_estimates.length > 0) {
    // Invoices additional server params
    var Sales_table_ServerParams = {};
    var Sales_table_Filter = $("._hidden_inputs._filters input");

    $.each(Sales_table_Filter, function () {
      Sales_table_ServerParams[$(this).attr("name")] =
        '[name="' + $(this).attr("name") + '"]';
    });

    if (table_invoices.length) {
      // Invoices tables
      initDataTable(
        table_invoices,
        admin_url +
          "invoices/table" +
          ($("body").hasClass("recurring") ? "?recurring=1" : ""),
        "undefined",
        "undefined",
        Sales_table_ServerParams,
        !$("body").hasClass("recurring")
          ? [
              [3, "desc"],
              [0, "desc"],
            ]
          : [table_invoices.find("th.next-recurring-date").index(), "asc"]
      );
    }

    if (table_estimates.length) {
      // Estimates table
      initDataTable(
        table_estimates,
        admin_url + "estimates/table",
        "undefined",
        "undefined",
        Sales_table_ServerParams,
        [
          [3, "desc"],
          [0, "desc"],
        ]
      );
    }
  }

  table_tasks = $(".table-tasks");
  if (table_tasks.length) {
    var TasksServerParams = {},
      Tasks_Filters;
    Tasks_Filters = $("._hidden_inputs._filters._tasks_filters input");
    $.each(Tasks_Filters, function () {
      TasksServerParams[$(this).attr("name")] =
        '[name="' + $(this).attr("name") + '"]';
    });

    // Tasks not sortable
    var tasksTableNotSortable = [0]; // bulk actions
    var tasksTableURL = admin_url + "tasks/table";

    if ($("body").hasClass("tasks-page")) {
      tasksTableURL += "?bulk_actions=true";
    }

    _table_api = initDataTable(
      table_tasks,
      tasksTableURL,
      tasksTableNotSortable,
      tasksTableNotSortable,
      TasksServerParams,
      [table_tasks.find("th.duedate").index(), "asc"]
    );

    if (_table_api && $("body").hasClass("dashboard")) {
      _table_api
        .column(5)
        .visible(false, false)
        .column(6)
        .visible(false, false)
        .columns.adjust();
    }
  }

  // Send file modal populate the hidden files when is shown
  $("#send_file").on("show.bs.modal", function (e) {
    var $sendFile = $("#send_file");
    $sendFile
      .find('input[name="filetype"]')
      .val($($(e.relatedTarget)).data("filetype"));
    $sendFile
      .find('input[name="file_path"]')
      .val($($(e.relatedTarget)).data("path"));
    $sendFile
      .find('input[name="file_name"]')
      .val($($(e.relatedTarget)).data("file-name"));
    if ($('input[name="email"]').length > 0) {
      $sendFile
        .find('input[name="send_file_email"]')
        .val($('input[name="email"]').val());
    }
  });

  $("#send_file form").on("submit", function () {
    $(this).find('button[type="submit"]').prop("disabled", true);
  });

  // Set password checkbox change
  $("body").on("change", 'input[name="send_set_password_email"]', function () {
    $("body").find(".client_password_set_wrapper").toggleClass("hide");
  });

  // Newsfeed close and open
  $("body").on("click", ".open_newsfeed, .close_newsfeed", function (e) {
    e.preventDefault();
    if (typeof $(this).data("close") == "undefined") {
      requestGet("newsfeed/get_data").done(function (response) {
        $("#newsfeed").html(response);
        load_newsfeed(postid);
        init_newsfeed_form();
        init_selectpicker();
        init_lightbox();
      });
    } else if ($(this).data("close") === true) {
      newsFeedDropzone.destroy();
      $("#newsfeed").html("");
      newsfeed_posts_page = 0;
      track_load_post_likes = 0;
      track_load_comment_likes = 0;
      postid = undefined;
    }
    $("#newsfeed").toggleClass("hide");
    $("body").toggleClass("noscroll");
  });

  if ($("[data-newsfeed-auto]").length > 0) {
    if ($(window).width() > 769) {
      $(".open_newsfeed.desktop").click();
    } else {
      $(".open_newsfeed.mobile").click();
    }
  }

  // When adding comment if user press enter to submit comment too for newsfeed comments
  $("body").on("keyup", ".comment-input input", function (event) {
    if (event.keyCode == 13) {
      add_comment(this);
    }
  });

  // Showing post likes modal
  $("#modal_post_likes").on("show.bs.modal", function (e) {
    track_load_post_likes = 0;
    $("#modal_post_likes_wrapper").empty();
    $(".likes_modal .modal-footer").removeClass("hide");
    var invoker = $(e.relatedTarget);
    var postid = $(invoker).data("postid");
    post_likes_total_pages = $(invoker).data("total-pages");
    $(".load_more_post_likes").attr("data-postid", postid);
    load_post_likes(postid);
  });

  // Showing comment likes modal
  $("#modal_post_comment_likes").on("show.bs.modal", function (e) {
    $("#modal_comment_likes_wrapper").empty();
    track_load_comment_likes = 0;
    $(".likes_modal .modal-footer").removeClass("hide");
    var invoker = $(e.relatedTarget);
    var commentid = $(invoker).data("commentid");
    comment_likes_total_pages = $(invoker).data("total-pages");
    $(".load_more_post_comment_likes").attr("data-commentid", commentid);
    load_comment_likes(commentid);
  });

  // Load more post likes from modal
  $(".load_more_post_likes").on("click", function (e) {
    e.preventDefault();
    load_post_likes($(this).data("postid"));
  });

  // Load more comment likes from modal
  $(".load_more_post_comment_likes").on("click", function (e) {
    e.preventDefault();
    load_comment_likes($(this).data("commentid"));
  });

  // Add post attachment used for dropzone
  $(".add-attachments").on("click", function (e) {
    e.preventDefault();
    $("#post-attachments").toggleClass("hide");
  });

  // Init invoices top stats
  init_invoices_total();
  // Init expenses total
  init_expenses_total();
  // Make items sortable
  init_estimates_total();
  // Make items sortable


  $(".settings-textarea-merge-field").on("click", function (e) {
    e.preventDefault();
    var mergeField = $(this).text().trim();
    var textArea = $('textarea[name="settings[' + $(this).data("to") + ']"]');
    textArea.val(textArea.val() + "\n" + mergeField);
  });

  if ($("body").hasClass("estimates-pipeline")) {
    var estimate_id = $('input[name="estimateid"]').val();
    estimate_pipeline_open(estimate_id);
  }

  if ($("body").hasClass("proposals-pipeline")) {
    var proposal_id = $('input[name="proposalid"]').val();
    proposal_pipeline_open(proposal_id);
  }

  // add invoice/estimate note
  $("body").on("submit", "#sales-notes", function () {
    var form = $(this);
    if (form.find('textarea[name="description"]').val() === "") {
      return;
    }

    $.post(form.attr("action"), $(form).serialize()).done(function (rel_id) {
      // Reset the note textarea value
      form.find('textarea[name="description"]').val("");
      // Reload the notes
      if (form.hasClass("estimate-notes-form")) {
        get_sales_notes(rel_id, "estimates");
      } else if (form.hasClass("invoice-notes-form")) {
        get_sales_notes(rel_id, "invoices");
      } else if (form.hasClass("proposal-notes-form")) {
        get_sales_notes(rel_id, "proposals");
      } else if (form.hasClass("contract-notes-form")) {
        get_sales_notes(rel_id, "contracts");
      }
    });
    return false;
  });

  // Show quantity as change we need to change on the table QTY heading for better user experience
  $("body").on("change", 'input[name="show_quantity_as"]', function () {
    $("body").find("th.qty").html($(this).data("text"));
  });

  // No duedate for credit note, separate event
  $("body").on("change", 'div.credit_note input[name="date"]', function () {
    do_prefix_year($(this).val());
  });

  $("body").on(
    "change",
    'div.invoice input[name="date"], div.estimate input[name="date"], div.proposal input[name="date"]',
    function () {
      var date = $(this).val();
      do_prefix_year(date);

      // This function not work on edit
      if ($('input[name="isedit"]').length > 0) {
        return;
      }

      var due_date_input_name = "duedate";
      var due_calc_url = admin_url + "invoices/get_due_date";

      if ($("body").find("div.estimate").length > 0) {
        due_calc_url = admin_url + "estimates/get_due_date";
        due_date_input_name = "expirydate";
      } else if ($("body").find("div.proposal").length > 0) {
        due_calc_url = admin_url + "proposals/get_due_date";
        due_date_input_name = "open_till";
      }

      if (date === "") {
        $('input[name="' + due_date_input_name + '"]').val("");
      }

      if (date !== "") {
        $.post(due_calc_url, {
          date: date,
        }).done(function (formatted) {
          if (formatted) {
            $('input[name="' + due_date_input_name + '"]').val(formatted);
          }
        });
      }
    }
  );

  $("#sales_attach_file").on("hidden.bs.modal", function (e) {
    $("#sales_uploaded_files_preview").empty();
    $(".dz-file-preview").empty();
  });

  if (typeof Dropbox != "undefined") {
    if ($("#dropbox-chooser-sales").length > 0) {
      document.getElementById("dropbox-chooser-sales").appendChild(
        Dropbox.createChooseButton({
          success: function (files) {
            salesExtenalFileUpload(files, "dropbox");
          },
          linkType: "preview",
          extensions: app.options.allowed_files.split(","),
        })
      );
    }
  }

  if ($("#sales-upload").length > 0) {
    new Dropzone(
      "#sales-upload",
      appCreateDropzoneOptions({
        sending: function (file, xhr, formData) {
          formData.append(
            "rel_id",
            $("body").find('input[name="_attachment_sale_id"]').val()
          );
          formData.append(
            "type",
            $("body").find('input[name="_attachment_sale_type"]').val()
          );
        },
        success: function (files, response) {
          response = JSON.parse(response);
          var type = $("body")
            .find('input[name="_attachment_sale_type"]')
            .val();
          var dl_url, delete_function;
          dl_url = "download/file/sales_attachment/";
          delete_function = "delete_" + type + "_attachment";
          if (type == "estimate") {
            $("body").hasClass("estimates-pipeline")
              ? estimate_pipeline_open(response.rel_id)
              : init_estimate(response.rel_id);
          } else if (type == "proposal") {
            $("body").hasClass("proposals-pipeline")
              ? proposal_pipeline_open(response.rel_id)
              : init_proposal(response.rel_id);
          } else {
            if (typeof window["init_" + type] == "function") {
              window["init_" + type](response.rel_id);
            }
          }
          var data = "";
          if (response.success === true || response.success == "true") {
            data +=
              '<div class="display-block sales-attach-file-preview" data-attachment-id="' +
              response.attachment_id +
              '">';
            data += '<div class="col-md-10">';
            data +=
              '<div class="pull-left"><i class="attachment-icon-preview fa-regular fa-file"></i></div>';
            data +=
              '<a href="' +
              site_url +
              dl_url +
              response.key +
              '" target="_blank">' +
              response.file_name +
              "</a>";
            data += '<p class="text-muted">' + response.filetype + "</p>";
            data += "</div>";
            data += '<div class="col-md-2 text-right">';
            data +=
              '<a href="#" class="text-danger" onclick="' +
              delete_function +
              "(" +
              response.attachment_id +
              '); return false;"><i class="fa fa-times"></i></a>';
            data += "</div>";
            data += '<div class="clearfix"></div><hr/>';
            data += "</div>";
            $("#sales_uploaded_files_preview").append(data);
          }
        },
      })
    );
  }

  // Show send to email invoice modal
  $("body").on("click", ".invoice-send-to-client", function (e) {
    e.preventDefault();
    $("#invoice_send_to_client_modal").modal("show");
  });

  // Show send to email estimate modal
  $("body").on("click", ".estimate-send-to-client", function (e) {
    e.preventDefault();
    $("#estimate_send_to_client_modal").modal("show");
  });

  // Send templaate modal custom close function causing problems if is on pipeline view
  $("body").on("click", ".close-send-template-modal", function () {
    $("#estimate_send_to_client_modal").modal("hide");
    $("#proposal_send_to_customer").modal("hide");
  });


});

// For manually modals where no close is defined
$(document).keyup(function (e) {
  if (e.keyCode == 27) {
    // escape key maps to keycode `27`

    if ($(".popup-wrapper").is(":visible")) {
      $(".popup-wrapper").find(".system-popup-close").click();
    }

    if ($("#search-history").is(":visible")) {
      $("#search-history").removeClass("display-block");
    }
  }
});

function _make_task_checklist_items_deletable() {
  if (app.options.has_permission_tasks_checklist_items_delete == "1") {
    var itemsHtml = $("body")
      .find(".checklist-templates-wrapper ul.dropdown-menu li")
      .not(":first-child");
    var itemsSelect = $("body")
      .find(".checklist-templates-wrapper select option")
      .not(":first-child");
    $.each(itemsSelect, function (i, item) {
      var $item = $(item);
      if (
        $(itemsHtml[i]).find(".checklist-item-template-remove").length === 0
      ) {
        $(itemsHtml[i])
          .find("a > span.text")
          .after(
            '<small class="checklist-item-template-remove" onclick="remove_checklist_item_template(' +
              $item.attr("value") +
              '); event.stopPropagation();"><i class="fa fa-remove"></i></small>'
          );
      }
    });
  }
}



// Fix for height on the wrapper


// Set body small based on device width
function set_body_small() { return NativeAdminNavigation.bodySmall({isMobile:is_mobile,isRTL:isRTL}); }

// General validate form function
// This should not be used too, but it's added for readibility
// @deprecated 2.3.
function _validate_form(form, form_rules, submithandler, overwriteMessages) {
  appValidateForm(form, form_rules, submithandler, overwriteMessages);
}

// Delete option from database AJAX
// Not tested, do not use this function
// Not used?
function delete_option(child, id) {
  if (confirm_delete()) {
    requestGetJSON("settings/delete_option/" + id).done(function (response) {
      if (response.success === true || response.success == "true") {
        $(child).parents(".option").remove();
      }
    });
  }
}

// Initing relation tasks tables

// Datatbles inline/offline - no serverside


// General function for all datatables serverside


// Update tags in task single modal
function task_single_update_tags() {
  var taskTags = $("#taskTags");
  $.post(admin_url + "tasks/update_tags", {
    tags: taskTags.tagit("assignedTags"),
    task_id: taskTags.attr("data-taskid"),
  });
}

// By default only 2 attachments for tasks are shown the other are hidden and there is button show more, this will show all the attachments
function task_attachments_toggle() {
  var $taskModal = $("#task-modal");
  $taskModal
    .find(".task_attachments_wrapper .task-attachments-more")
    .toggleClass("hide");
  $taskModal
    .find(".task_attachments_wrapper .task-attachments-less")
    .toggleClass("hide");
}

// Date picker init with selected timeformat from settings


// Init color pickers


// Init select picker


// Init light box


// Init progress bars


// All inputs used for tags
// Shared native UI helper: init_tags_inputs (assets/js/native-ui-tools.js).

// Datatables custom view will fill input with the value


// Sets table filters dropdown to active


// Called when editing member profile
// Shared native staff component: init_roles_permissions (assets/js/native-admin-staff.js).

// Show/hide full table
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

// Main logout function check if timers found to show the warning
function logout() {
  var started_timers = $(".started-timers-top").find("li.timer").length;
  if (started_timers > 0) {
    var warning = $("#timers-logout-template-warning").html();
    var $p = system_popup({
      message: " ",
      content: warning,
    });
    $p.find(".popup-message").addClass("hide");
    return false;
  } else {
    // No timer logout free
    window.location.href = admin_url + "authentication/logout";
  }
}

// Init the media elfinder for tinymce browser
// Shared native UI helper: elFinderBrowser (assets/js/native-ui-tools.js).

// Function to init the tinymce editor
// Shared native UI helper: init_editor (assets/js/native-ui-tools.js).

// Function used to add custom bootstrap menu for setup and main menu and to add fa on front like fa fa-question
function _formatMenuIconInput(e) {
  if (typeof e == "undefined") {
    return;
  }
  var _input = $(e.target);
  if (!_input.val().match(/^fa /)) {
    _input.val("fa " + _input.val());
  }
}

// This is used for mobile where tooltip on _buttons class wrapper is found
// Will show all buttons tooltips as regular button with text
function init_btn_with_tooltips() {
  if (is_mobile()) {
    var is_iPad = navigator.userAgent.match(/iPad/i) != null;
    if (is_iPad) {
      return false;
    }
    var tooltips_href_btn = $("._buttons").find(".btn-with-tooltip");
    $.each(tooltips_href_btn, function () {
      var title = $(this).attr("title");
      if (typeof title == "undefined") {
        title = $(this).attr("data-title");
      }
      if (typeof title != "undefined") {
        $(this).append(" " + title);
        $(this).removeClass("btn-with-tooltip");
      }
    });
    var tooltips_group = $("._buttons").find(".btn-with-tooltip-group");
    $.each(tooltips_group, function () {
      var title = $(this).attr("title");
      if (typeof title == "undefined") {
        title = $(this).attr("data-title");
      }
      if (typeof title != "undefined") {
        $(this)
          .find(".btn")
          .eq(0)
          .append(" " + title);
        $(this).removeClass("btn-with-tooltip-group");
      }
    });
  }
}

// Helper hash id for estimates,invoices,proposals,expenses, credit notes
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

// Reminder handlers are shared in native-admin-reminders.js.

// Toggles sales file visibility for customer eq for invoices, estimates, proposals
// File visibility handler is shared in native-admin-files.js.

// Fixes kanban height to be compatible with content and screen height


// Kanban load more
function kanban_load_more(status_id, e, url, column_px, container_px) {
  var LoadMoreParameters = [];
  var search = $('input[name="search"]').val();
  var _kanban_param_val;
  var page = $(e).attr("data-page");
  var $column = $('[data-col-status-id="' + status_id + '"]');
  var total_pages = $column.data("total-pages");

  if (page <= total_pages) {
    var sort_type = $('input[name="sort_type"]');
    var sort = $('input[name="sort"]').val();
    if (sort_type.length != 0 && sort_type.val() !== "") {
      LoadMoreParameters["sort_by"] = sort_type.val();
      LoadMoreParameters["sort"] = sort;
    }

    if (typeof search != "undefined" && search !== "") {
      LoadMoreParameters["search"] = search;
    }

    $.each($("#kanban-params input"), function () {
      if ($(this).attr("type") == "checkbox") {
        _kanban_param_val =
          $(this).prop("checked") === true ? $(this).val() : "";
      } else {
        _kanban_param_val = $(this).val();
      }
      if (_kanban_param_val !== "") {
        LoadMoreParameters[$(this).attr("name")] = _kanban_param_val;
      }
    });

    LoadMoreParameters["status"] = status_id;
    LoadMoreParameters["page"] = page;
    LoadMoreParameters["page"]++;
    requestGet(buildUrl(admin_url + url, LoadMoreParameters))
      .done(function (response) {
        page++;
        $('[data-load-status="' + status_id + '"]').before(response);
        $('[data-col-status-id="' + status_id + '"]').attr(
          "data-total",
          parseInt(
            $('[data-col-status-id="' + status_id + '"]').attr("data-total")
          ) + $("<ul></ul>").append(response).find("li").length
        );
        $(e).attr("data-page", page);
        fix_kanban_height(column_px, container_px);
      })
      .fail(function (error) {
        alert_float("danger", error.responseText);
      });
    if (page >= total_pages - 1) {
      $(e).addClass("disabled");
    }
  }
}

// Check if kanban col is empty and perform necessary actions
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

// General function to init kan ban based on settings
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

function kan_ban_sort(type, callback) {
  var sort_type = $('input[name="sort_type"]');
  sort_type.val(type);
  var sort = $('input[name="sort"]');
  var val = sort.val().toLowerCase();
  sort.val(val == "asc" ? "DESC" : "ASC");
  init_kan_ban_sort_icon(sort.val(), type);
  callback();
}

function init_kan_ban_sort_icon(sort, type) {
  $("body").find(".kanban-sort-icon").remove();
  $("body")
    .find("." + type)
    .prepend(
      " <i class='kanban-sort-icon fa fa-sort-amount-" +
        sort.toLowerCase() +
        "'></i>"
    );
}

// When window scroll to down load more posts
$("#newsfeed").scroll(function (e) {
  var elem = $(e.currentTarget);
  if (elem[0].scrollHeight - elem.scrollTop() == elem.outerHeight()) {
    load_newsfeed();
  }
  $("#newsfeed .close_newsfeed").css("top", $(this).scrollTop() + 20 + "px");
});

// Newsfeed form after newfeed href is clicked
function init_newsfeed_form() {
  // Configure dropzone
  if (typeof newsFeedDropzone == "undefined") {
    // Init new post form
    $("body").on("submit", "#new-post-form", function () {
      $.post(this.action, $(this).serialize()).done(function (response) {
        response = JSON.parse(response);
        if (response.postid) {
          if (newsFeedDropzone.getQueuedFiles().length > 0) {
            newsFeedDropzone.options.url =
              admin_url + "newsfeed/add_post_attachments/" + response.postid;
            newsFeedDropzone.processQueue();
            return;
          }
          newsfeed_new_post(response.postid);
          clear_newsfeed_post_area();
        }
      });
      return false;
    });
  }

  newsFeedDropzone = new Dropzone(
    "#new-post-form",
    appCreateDropzoneOptions({
      clickable: ".add-post-attachments",
      autoProcessQueue: false,
      addRemoveLinks: true,
      parallelUploads: app.options.newsfeed_maximum_files_upload,
      maxFiles: app.options.newsfeed_maximum_files_upload,
      dragover: function (file) {
        $("#new-post-form").addClass("dropzone-active");
      },
      complete: function (file) {},
      drop: function (file) {
        $("#new-post-form").removeClass("dropzone-active");
      },
      success: function (files, response) {
        if (
          this.getUploadingFiles().length === 0 &&
          this.getQueuedFiles().length === 0
        ) {
          response = JSON.parse(response);
          newsfeed_new_post(response.postid);
          clear_newsfeed_post_area();
          this.removeAllFiles();
        }
      },
    })
  );
}

// Clear newsfeed new post area
function clear_newsfeed_post_area() {
  $("#new-post-form textarea").val("");
  $("#post-visibility").selectpicker("deselectAll");
}

// Load post likes modal
function load_post_likes(postid) {
  if (track_load_post_likes <= post_likes_total_pages) {
    $.post(admin_url + "newsfeed/load_likes_modal", {
      page: track_load_post_likes,
      postid: postid,
    }).done(function (response) {
      track_load_post_likes++;
      $("#modal_post_likes_wrapper").append(response);
    });

    if (track_load_post_likes >= post_likes_total_pages - 1) {
      $(".likes_modal .modal-footer").addClass("hide");
    }
  }
}

// Load comment likes modal
function load_comment_likes(commentid) {
  if (track_load_comment_likes <= comment_likes_total_pages) {
    $.post(admin_url + "newsfeed/load_comment_likes_model", {
      page: track_load_comment_likes,
      commentid: commentid,
    }).done(function (response) {
      track_load_comment_likes++;
      $("#modal_comment_likes_wrapper").append(response);
    });

    if (track_load_comment_likes >= comment_likes_total_pages - 1) {
      $(".likes_modal .modal-footer").addClass("hide");
    }
  }
}

// On click href load more comments from single post
function load_more_comments(link) {
  var postid = $(link).data("postid");
  var page = $(link).find('input[name="page"]').val();
  var total_pages = $(link).data("total-pages");

  if (page <= total_pages) {
    $.post(admin_url + "newsfeed/init_post_comments/" + postid, {
      page: page,
    }).done(function (response) {
      $(link).data("track-load-comments", page);
      $('[data-comments-postid="' + postid + '"] .load-more-comments').before(
        response
      );
    });
    page++;
    $(link).find('input[name="page"]').val(page);
    if (page >= total_pages - 1) {
      $(link).addClass("hide");
      $(link).removeClass("display-block");
    }
  }
}

// New post added append data
function newsfeed_new_post(postid) {
  var data = {};
  data.postid = postid;
  $.post(admin_url + "newsfeed/load_newsfeed", data).done(function (response) {
    var pinned = $("#newsfeed_data").find(".pinned");
    var pinned_length = pinned.length;
    if (pinned_length === 0) {
      $("#newsfeed_data").prepend(response);
    } else {
      var last_pinned = $("#newsfeed_data")
        .find(".pinned")
        .eq(pinned_length - 1);
      $(last_pinned).after(response);
    }
  });
}

// Init newsfeed data
function load_newsfeed(postid) {
  var data = {};
  data.page = newsfeed_posts_page;
  if (typeof postid != "undefined" && postid != 0) {
    data.postid = postid;
  }
  var total_pages = $('input[name="total_pages_newsfeed"]').val();
  if (newsfeed_posts_page <= total_pages) {
    $.post(admin_url + "newsfeed/load_newsfeed", data).done(function (
      response
    ) {
      newsfeed_posts_page++;
      $("#newsfeed_data").append(response);
    });
    if (newsfeed_posts_page >= total_pages - 1) {
      return;
    }
  }
}

// When user click heart button
function like_post(postid) {
  requestGetJSON("newsfeed/like_post/" + postid).done(function (response) {
    if (response.success === true || response.success == "true") {
      refresh_post_likes(postid);
    }
  });
}

// Unlikes post
function unlike_post(postid) {
  requestGetJSON("newsfeed/unlike_post/" + postid).done(function (response) {
    if (response.success === true || response.success == "true") {
      refresh_post_likes(postid);
    }
  });
}

// Like post comment
function like_comment(commentid, postid) {
  requestGetJSON("newsfeed/like_comment/" + commentid + "/" + postid).done(
    function (response) {
      if (response.success === true || response.success == "true") {
        $('[data-commentid="' + commentid + '"]').replaceWith(response.comment);
      }
    }
  );
}

// Unlike post comment
function unlike_comment(commentid, postid) {
  requestGetJSON("newsfeed/unlike_comment/" + commentid + "/" + postid).done(
    function (response) {
      if (response.success === true || response.success == "true") {
        $('[data-commentid="' + commentid + '"]').replaceWith(response.comment);
      }
    }
  );
}

// Add new comment to post
function add_comment(input) {
  var postid = $(input).data("postid");
  $.post(admin_url + "newsfeed/add_comment", {
    content: $(input).val(),
    postid: postid,
  }).done(function (response) {
    response = JSON.parse(response);
    if (response.success === true || response.success == "true") {
      $(input).val("");
      if (
        $("body").find('[data-comments-postid="' + postid + '"] .post-comment')
          .length > 0
      ) {
        $("body")
          .find('[data-comments-postid="' + postid + '"] .post-comment')
          .prepend(response.comment);
      } else {
        refresh_post_comments(postid);
      }
    }
  });
}

// Removes post comment
function remove_post_comment(id, postid) {
  requestGetJSON("newsfeed/remove_post_comment/" + id + "/" + postid).done(
    function (response) {
      if (response.success === true || response.success == "true") {
        $('.comment[data-commentid="' + id + '"]').remove();
      }
    }
  );
}

// Refreshing only post likes
function refresh_post_likes(postid) {
  requestGet(
    "newsfeed/init_post_likes/" + postid + "?refresh_post_likes=true"
  ).done(function (response) {
    $('[data-likes-postid="' + postid + '"]').html(response);
  });
}

// Refreshing only post comments
function refresh_post_comments(postid) {
  $.post(
    admin_url +
      "newsfeed/init_post_comments/" +
      postid +
      "?refresh_post_comments=true"
  ).done(function (response) {
    $('[data-comments-postid="' + postid + '"]').html(response);
  });
}

// Delete post from database
function delete_post(postid) {
  if (confirm_delete()) {
    $.post(
      admin_url + "newsfeed/delete_post/" + postid,
      function (response) {
        if (response.success === true || response.success == "true") {
          $('[data-main-postid="' + postid + '"]').remove();
        }
      },
      "json"
    );
  }
}

// Pin post to top
function pin_post(id) {
  requestGetJSON("newsfeed/pin_newsfeed_post/" + id).done(function (response) {
    if (response.success === true || response.success == "true") {
      window.location.reload();
    }
  });
}

// Unpin post from top
function unpin_post(id) {
  requestGetJSON("newsfeed/unpin_newsfeed_post/" + id).done(function (
    response
  ) {
    if (response.success === true || response.success == "true") {
      window.location.reload();
    }
  });
}

function _gen_lead_add_inline_on_select_field(type) {
  var html = "";
  if (
    $("body").hasClass("leads-email-integration") ||
    $("body").hasClass("web-to-lead-form")
  ) {
    type = "lead_" + type;
  }
  html =
    '<div id="new_lead_' +
    type +
    '_inline" class="form-group"><label for="new_' +
    type +
    '_name">' +
    $('label[for="' + type + '"]')
      .html()
      .trim() +
    '</label><div class="input-group"><input type="text" id="new_' +
    type +
    '_name" name="new_' +
    type +
    '_name" class="form-control"><div class="input-group-addon"><a href="#" onclick="lead_add_inline_select_submit(\'' +
    type +
    '\'); return false;" class="lead-add-inline-submit-' +
    type +
    '"><i class="fa fa-check"></i></a></div></div></div>';
  $(".form-group-select-input-" + type).after(html);
  $("body")
    .find("#new_" + type + "_name")
    .focus();
  $(
    '.lead-save-btn,#form_info button[type="submit"],#leads-email-integration button[type="submit"],.btn-import-submit'
  ).prop("disabled", true);
  $(".inline-field-new").addClass("disabled").css("opacity", 0.5);
  $(".form-group-select-input-" + type).addClass("hide");
}

function new_lead_status_inline() {
  _gen_lead_add_inline_on_select_field("status");
}

function new_lead_source_inline() {
  _gen_lead_add_inline_on_select_field("source");
}

function lead_add_inline_select_submit(type) {
  var val = $("#new_" + type + "_name")
    .val()
    .trim();
  if (val !== "") {
    var requestURI = type;
    if (type.indexOf("lead_") > -1) {
      requestURI = requestURI.replace("lead_", "");
    }

    var data = {};
    data.name = val;
    data.inline = true;
    $.post(admin_url + "leads/" + requestURI, data).done(function (response) {
      response = JSON.parse(response);
      if (response.success === true || response.success == "true") {
        var select = $("body").find("select#" + type);
        select.append(
          '<option value="' + response.id + '">' + val + "</option>"
        );
        select.selectpicker("val", response.id);
        select.selectpicker("refresh");
        select.parents(".form-group").removeClass("has-error");
      }
    });
  }

  $("#new_lead_" + type + "_inline").remove();
  $(".form-group-select-input-" + type).removeClass("hide");
  $(
    '.lead-save-btn,#form_info button[type="submit"],#leads-email-integration button[type="submit"],.btn-import-submit'
  ).prop("disabled", false);
  $(".inline-field-new").removeClass("disabled").removeAttr("style");
}

// Init lead for add/edit/view or refresh data
function init_lead(id, isEdit) {
  if ($("#task-modal").is(":visible")) {
    $("#task-modal").modal("hide");
  }
  // In case header error
  if (init_lead_modal_data(id, undefined, isEdit)) {
    $("#lead-modal").modal("show");
  }
}

// Lead form validation
function validate_lead_form() {
  var validationObject = {
    name: "required",
    source: "required",
    status: {
      required: {
        depends: function (element) {
          if ($("[lead-is-junk-or-lost]").length > 0) {
            return false;
          } else {
            return true;
          }
        },
      },
    },
  };

  var messages = {};
  $.each(leadUniqueValidationFields, function (key, field) {
    validationObject[field] = {};

    if (field == "email") {
      validationObject[field].email = true;
    }

    validationObject[field].remote = {
      url: admin_url + "leads/validate_unique_field",
      type: "post",
      data: {
        field: field,
        lead_id: function () {
          return $("#lead-modal").find('input[name="leadid"]').val();
        },
      },
    };

    if (typeof app.lang[field + "_exists"] != "undefined") {
      messages[field] = {
        remote: app.lang[field + "_exists"],
      };
    }
  });

  appValidateForm(
    $("#lead_form"),
    validationObject,
    lead_profile_form_handler,
    messages
  );
}

// Lead conver to customer form validation
function validate_lead_convert_to_client_form() {
  var rules_convert_lead = {
    firstname: "required",
    lastname: "required",
    password: {
      required: {
        depends: function (element) {
          var sent_set_password = $('input[name="send_set_password_email"]');
          if (sent_set_password.prop("checked") === false) {
            return true;
          }
        },
      },
    },
    email: {
      required: true,
      email: true,
      remote: {
        url: admin_url + "misc/contact_email_exists",
        type: "post",
        data: {
          email: function () {
            return $('#lead_to_client_form input[name="email"]').val();
          },
          userid: "",
        },
      },
    },
  };
  if (app.options.company_is_required == 1) {
    rules_convert_lead.company = "required";
  }
  appValidateForm($("#lead_to_client_form"), rules_convert_lead);
}

// Lead profile data function form handler
function lead_profile_form_handler(form) {
  form = $(form);
  var data = form.serialize();
  var leadid = $("#lead-modal").find('input[name="leadid"]').val();
  $(".lead-save-btn").addClass("disabled").attr('disabled', true);
  $.post(form.attr("action"), data)
    .done(function (response) {
      response = JSON.parse(response);
      if (response.message !== "") {
        alert_float("success", response.message);
      }
      if (response.proposal_warning && response.proposal_warning != false) {
        $("body").find("#lead_proposal_warning").removeClass("hide");
        $("body").find("#lead-modal").animate(
          {
            scrollTop: 0,
          },
          800
        );
      } else {
        _lead_init_data(response, response.id);
      }
      if ($.fn.DataTable.isDataTable(".table-leads")) {
        table_leads.DataTable().ajax.reload(null, false);
      } else if ($("body").hasClass("kan-ban-body")) {
        leads_kanban();
      }
    })
    .fail(function (data) {
      alert_float("danger", data.responseText);
      return false;
    });
  return false;
}

// Updates all proposals emails linked to lead, this wil be executed when eq lead email is changed
function update_all_proposal_emails_linked_to_lead(id) {
  $.post(admin_url + "leads/update_all_proposal_emails_linked_to_lead/" + id, {
    update: true,
  }).done(function (response) {
    response = JSON.parse(response);
    if (response.success) {
      alert_float("success", response.message);
    }
    init_lead_modal_data(id);
  });
}

// Add lead data returned from server to the lead modal
function _lead_init_data(data, id) {
  var hash = window.location.hash;

  var $leadModal = $("#lead-modal");
  $("#lead_reminder_modal").html(data.leadView.reminder_data);

  $leadModal.find(".data").html(data.leadView.data);

  $leadModal.modal({
    show: true,
    backdrop: "static",
  });

  init_tags_inputs();
  init_selectpicker();
  init_form_reminder();
  init_datepicker();
  init_color_pickers();
  custom_fields_hyperlink();
  validate_lead_form();

  var hashes = [
    "#tab_lead_profile",
    "#attachments",
    "#lead_notes",
    "#lead_activity",
    "#gdpr",
  ];

  if (hashes.indexOf(hash) > -1) {
    window.location.hash = hash;
  }

  initDataTableInline($("#consentHistoryTable"));

  $("#lead-modal")
    .find(".gpicker")
    .googleDrivePicker({
      onPick: function (pickData) {
        leadExternalFileUpload(pickData, "gdrive", id);
      },
    });

  if (id !== "" && typeof id != "undefined") {
    if (typeof Dropbox != "undefined") {
      document.getElementById("dropbox-chooser-lead").appendChild(
        Dropbox.createChooseButton({
          success: function (files) {
            leadExternalFileUpload(files, "dropbox", id);
          },
          linkType: "preview",
          extensions: app.options.allowed_files.split(","),
        })
      );
    }

    if (typeof leadAttachmentsDropzone != "undefined") {
      leadAttachmentsDropzone.destroy();
    }

    leadAttachmentsDropzone = new Dropzone(
      "#lead-attachment-upload",
      appCreateDropzoneOptions({
        sending: function (file, xhr, formData) {
          formData.append("id", id);
          if (this.getQueuedFiles().length === 0) {
            formData.append("last_file", true);
          }
        },
        success: function (file, response) {
          response = JSON.parse(response);
          if (
            this.getUploadingFiles().length === 0 &&
            this.getQueuedFiles().length === 0
          ) {
            _lead_init_data(response, response.id);
          }
        },
      })
    );

    $leadModal
      .find('.nav-tabs a[href="' + window.location.hash + '"]')
      .tab("show");
    var latest_lead_activity = $leadModal
      .find("#lead_activity .feed-item:last-child .text")
      .html();
    if (typeof latest_lead_activity != "undefined") {
      $leadModal.find("#lead-latest-activity").html(latest_lead_activity);
    } else {
      $leadModal
        .find(".lead-latest-activity > .lead-info-heading")
        .addClass("hide");
    }

    // The status is not required when lead is lost or junk
    // Remove the * required mark
    if ($("[lead-is-junk-or-lost]").length > 0) {
      $(".form-group-select-input-status").find(".req").remove();
    }
  }
}

// Fetches lead modal data, can be edit/add/view
function init_lead_modal_data(id, url, isEdit) {
  var requestURL =
    (typeof url != "undefined" ? url : "leads/lead/") +
    (typeof id != "undefined" ? id : "");

  if (isEdit === true) {
    var concat = "?";
    if (requestURL.indexOf("?") > -1) {
      concat += "&";
    }
    requestURL += concat + "edit=true";
  }

  requestGetJSON(requestURL)
    .done(function (response) {
      _lead_init_data(response, id);
    })
    .fail(function (data) {
      alert_float("danger", data.responseText);
    });
}

function print_lead_information() {
  var $leadViewWrapper = $("#leadViewWrapper").clone();
  var name = $leadViewWrapper.find(".lead-name").text().trim();

  $leadViewWrapper
    .find("p")
    .css("font-size", "100%")
    .css("font", "inherit")
    .css("vertical-align", "baseline")
    .css("margin", "0px");

  $leadViewWrapper.find("h4").css("font-size", "100%");

  $leadViewWrapper.find("dt").each(function() {
      $(this).replaceWith("<p style='margin-bottom:5px; color:#777'><strong>" + $(this).text() + "</strong></p>");
  });

  $leadViewWrapper.find("dd").each(function() {
      $(this).replaceWith("<div='margin-bottom:10px;'>" + $(this).text() + "</div>");
  });
  
  $leadViewWrapper.find(".lead-field-heading + p").css("margin-bottom", "15px");

  var mywindow = _create_print_window(name);

  mywindow.document.write("<html><head><title>" + app.lang.lead + "</title>");
  _add_print_window_default_styles(mywindow);
  mywindow.document.write("<style>");
  mywindow.document.write(
    ".lead-information-col { " + "float: left; width: 33.33333333%;" + "}" + ""
  );
  mywindow.document.write("</style>");

  mywindow.document.write("</head><body>");
  mywindow.document.write("<h1>" + name + "</h1>");
  mywindow.document.write(
    '<div id="#leadViewWrapper">' + $leadViewWrapper.html() + "</div>"
  );
  mywindow.document.write("</body></html>");

  mywindow.document.close(); // necessary for IE >= 10
  mywindow.focus(); // necessary for IE >= 10*/

  mywindow.print();
  setTimeout(function () {
    mywindow.close();
  }, 1000);
}

function print_expense_information() {
  var $expenseViewWrapper = $("#tab_expense").clone();
  var $headings = $("#expenseHeadings");
  var name =
    $headings.find("#expenseCategory").text().trim() +
    "<h4>" +
    $headings.find("#expenseName").text().trim() +
    "</h4>";

  $expenseViewWrapper.find("#expenseReceipt").remove();

  $expenseViewWrapper.find("#amountWrapper").css("margin-bottom", "15px");

  var mywindow = _create_print_window(name);

  mywindow.document.write(
    "<html><head><title>" + app.lang.expense + "</title>"
  );

  _add_print_window_default_styles(mywindow);

  mywindow.document.write("</head><body>");
  mywindow.document.write("<h1>" + name + "</h1>");
  mywindow.document.write(
    '<div id="#tab_expense">' + $expenseViewWrapper.html() + "</div>"
  );
  mywindow.document.write("</body></html>");

  mywindow.document.close(); // necessary for IE >= 10
  mywindow.focus(); // necessary for IE >= 10*/

  mywindow.print();
  setTimeout(function () {
    mywindow.close();
  }, 1000);
}

function print_ticket_message(id, type) {
  var printMessage = $("[data-" + type + '-id="' + id + '"]').html();
  var printSubject = $("#ticket_subject").text().trim();
  var mywindow = _create_print_window(printSubject);

  mywindow.document.write("<html><head><title>" + app.lang.ticket + "</title>");

  _add_print_window_default_styles(mywindow);

  mywindow.document.write("</head><body>");
  mywindow.document.write("<h1>" + printSubject + "</h1>");
  mywindow.document.write(printMessage);
  mywindow.document.write("</body></html>");

  mywindow.document.close(); // necessary for IE >= 10
  mywindow.focus(); // necessary for IE >= 10*/

  mywindow.print();
  setTimeout(function () {
    mywindow.close();
  }, 1000);
}

// Kan ban leads sorting
function leads_kanban_sort(type) {
  kan_ban_sort(type, leads_kanban);
}

// Update lead when action is performed from leads kan ban eq order or status change
function leads_kanban_update(ui, object) {
  if (object !== ui.item.parent()[0]) {
    return;
  }

  var data = {
    status: $(ui.item.parent()[0]).attr("data-lead-status-id"),
    leadid: $(ui.item).attr("data-lead-id"),
    order: [],
  };

  $.each($(ui.item).parents(".leads-status").find("li"), function (idx, el) {
    var id = $(el).attr("data-lead-id");
    if (id) {
      data.order.push([id, idx + 1]);
    }
  });

  setTimeout(function () {
    $.post(admin_url + "leads/update_lead_status", data).done(function (
      response
    ) {
      update_kan_ban_total_when_moving(ui, data.status);
      leads_kanban();
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

// Leads statuses kanban sortable
function init_leads_status_sortable() {
  $("#kan-ban").sortable({
    helper: "clone",
    item: ".kan-ban-col",
    update: function (event, ui) {
      data = {
        order: [],
      };

      $.each($(".kan-ban-col"), function (idx, el) {
        data.order.push([$(el).attr("data-col-status-id"), idx + 1]);
      });

      $.post(admin_url + "leads/update_status_order", data);
    },
  });
}

// Init the leads kanban
function leads_kanban(search) {
  init_kanban(
    "leads/kanban",
    leads_kanban_update,
    ".leads-status",
    290,
    360,
    init_leads_status_sortable
  );
}

// Deleting lead attachments
function delete_lead_attachment(wrapper, id, lead_id) {
  if (confirm_delete()) {
    requestGetJSON("leads/delete_attachment/" + id + "/" + lead_id)
      .done(function (response) {
        if (response.success === true || response.success == "true") {
          $(wrapper).parents(".lead-attachment-wrapper").remove();
          _lead_init_data(response, response.id);
        }
      })
      .fail(function (data) {
        alert_float("danger", data.responseText);
      });
  }
}

// Delete lead note
function delete_lead_note(wrapper, id, lead_id) {
  if (confirm_delete()) {
    requestGetJSON("leads/delete_note/" + id + "/" + lead_id)
      .done(function (response) {
        if (response.success === true || response.success == "true") {
          $(wrapper).parents(".lead-note").remove();
          _lead_init_data(response, response.id);
        }
      })
      .fail(function (data) {
        alert_float("danger", data.responseText);
      });
  }
}

// Mark lead as lost function
function lead_mark_as_lost(id) {
  requestGetJSON("leads/mark_as_lost/" + id)
    .done(function (response) {
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
        $("body")
          .find("tr#lead_" + id)
          .remove();
        $("body")
          .find('#kan-ban li[data-lead-id="' + id + '"]')
          .remove();
      }
      _lead_init_data(response, response.id);
    })
    .fail(function (error) {
      alert_float("danger", error.responseText);
    });
}

// Unmark lead as lost function
function lead_unmark_as_lost(id) {
  requestGetJSON("leads/unmark_as_lost/" + id)
    .done(function (response) {
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
      }
      _lead_init_data(response, response.id);
    })
    .fail(function (error) {
      alert_float("danger", error.responseText);
    });
}

// Mark lead as junk function
function lead_mark_as_junk(id) {
  requestGetJSON("leads/mark_as_junk/" + id)
    .done(function (response) {
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
        $("body")
          .find("tr#lead_" + id)
          .remove();
        $("body")
          .find('#kan-ban li[data-lead-id="' + id + '"]')
          .remove();
      }
      _lead_init_data(response, response.id);
    })
    .fail(function (error) {
      alert_float("danger", error.responseText);
    });
}
// From lead table mark as
function lead_mark_as(status_id, lead_id) {
  var data = {};
  data.status = status_id;
  data.leadid = lead_id;
  $.post(admin_url + "leads/update_lead_status", data).done(function (
    response
  ) {
    table_leads.DataTable().ajax.reload(null, false);
  });
}

// Unmark lead as junk function
function lead_unmark_as_junk(id) {
  requestGetJSON("leads/unmark_as_junk/" + id)
    .done(function (response) {
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
      }
      _lead_init_data(response, response.id);
    })
    .fail(function (error) {
      alert_float("danger", error.responseText);
    });
}

// Convert lead to customer
function convert_lead_to_customer(id) {
  var $leadModal = $("#lead-modal");
  var eventNamespace = "hidden.bs.modal.convert";

  $leadModal.on(eventNamespace, function () {
    $leadModal.find(".data").html("");

    requestGet("leads/get_convert_data/" + id)
      .done(function (response) {
        $("#lead_convert_to_customer").html(response);

        $("#convert_lead_to_client_modal").modal({
          show: true,
          backdrop: "static",
          keyboard: false,
        });
      })
      .fail(function (data) {
        alert_float("danger", data.responseText);
      })
      .always(function () {
        $leadModal.off(eventNamespace);
      });
  });

  $leadModal.modal("hide");
}

// Leads bulk action
function leads_bulk_action(event) {
  if (confirm_delete()) {
    var mass_delete = $("#mass_delete").prop("checked");
    var ids = [];
    var data = {};
    if (mass_delete == false || typeof mass_delete == "undefined") {
      data.lost = $("#leads_bulk_mark_lost").prop("checked");
      data.status = $("#move_to_status_leads_bulk").val();
      data.assigned = $("#assign_to_leads_bulk").val();
      data.source = $("#move_to_source_leads_bulk").val();
      data.last_contact = $("#leads_bulk_last_contact").val();
      data.tags = $("#tags_bulk").tagit("assignedTags");
      data.visibility = $('input[name="leads_bulk_visibility"]:checked').val();

      data.assigned = typeof data.assigned == "undefined" ? "" : data.assigned;
      data.visibility =
        typeof data.visibility == "undefined" ? "" : data.visibility;

      if (
        data.status === "" &&
        data.lost === false &&
        data.assigned === "" &&
        data.source === "" &&
        data.last_contact === "" &&
        data.tags.length == 0 &&
        data.visibility === ""
      ) {
        return;
      }
    } else {
      data.mass_delete = true;
    }
    var rows = table_leads.find("tbody tr");
    $.each(rows, function () {
      var checkbox = $($(this).find("td").eq(0)).find("input");
      if (checkbox.prop("checked") === true) {
        ids.push(checkbox.val());
      }
    });
    data.ids = ids;
    $(event).addClass("disabled");
    setTimeout(function () {
      $.post(admin_url + "leads/bulk_action", data)
        .done(function () {
          window.location.reload();
        })
        .fail(function (data) {
          $("#lead-modal").modal("hide");
          alert_float("danger", data.responseText);
        });
    }, 200);
  }
}

function init_proposal_editor() {
  init_tinymce_inline_editor({
    saveUsing: save_proposal_content,
    onSetup: function(editor) {
      editor.on("MouseDown ContextMenu", function () {
        if (!is_mobile() && 
          !$("#small-table").hasClass("hide")) {
          small_table_full_view();
        }
      });
    }
  })
}

function update_comments_count() {
  var count = $(".comment-item").length;
  $(".total_comments").text(count);
  if (count === 0) {
    $(".total_comments").addClass("hide");
  } else {
    $(".total_comments").removeClass("hide");
  }
}

function add_proposal_comment() {
  var comment = $("#comment").val();
  if (comment == "") {
    return;
  }
  var data = {};
  data.content = comment;
  data.proposalid = proposal_id;
  $("body").append('<div class="dt-loader"></div>');
  $.post(admin_url + "proposals/add_proposal_comment", data).done(function (
    response
  ) {
    response = JSON.parse(response);
    $("body").find(".dt-loader").remove();
    if (response.success == true) {
      $("#comment").val("");
      get_proposal_comments();
    }
  });
}

function get_proposal_comments() {
  if (typeof proposal_id == "undefined") {
    return;
  }
  requestGet("proposals/get_proposal_comments/" + proposal_id).done(function (
    response
  ) {
    $("body").find("#proposal-comments").html(response);
    update_comments_count("proposal");
  });
}

function remove_proposal_comment(commentid) {
  if (confirm_delete()) {
    requestGetJSON("proposals/remove_comment/" + commentid).done(function (
      response
    ) {
      if (response.success == true) {
        $('[data-commentid="' + commentid + '"]').remove();
        update_comments_count("proposal");
      }
    });
  }
}

function edit_proposal_comment(id) {
  var content = $("body")
    .find('[data-proposal-comment-edit-textarea="' + id + '"] textarea')
    .val();
  if (content != "") {
    $.post(admin_url + "proposals/edit_comment/" + id, {
      content: content,
    }).done(function (response) {
      response = JSON.parse(response);
      if (response.success == true) {
        alert_float("success", response.message);
        $("body")
          .find('[data-proposal-comment="' + id + '"]')
          .html(nl2br(content));
      }
    });
    toggle_proposal_comment_edit(id);
  }
}

function toggle_proposal_comment_edit(id) {
  $("body")
    .find('[data-proposal-comment="' + id + '"]')
    .toggleClass("hide");
  $("body")
    .find('[data-proposal-comment-edit-textarea="' + id + '"]')
    .toggleClass("hide");
}

function proposal_convert_template(invoker) {
  var template = $(invoker).data("template");
  var html_helper_selector;
  if (template == "estimate") {
    html_helper_selector = "estimate";
  } else if (template == "invoice") {
    html_helper_selector = "invoice";
  } else {
    return false;
  }

  requestGet(
    "proposals/get_" + html_helper_selector + "_convert_data/" + proposal_id
  ).done(function (data) {
    if ($(".proposal-pipeline-modal").is(":visible")) {
      $(".proposal-pipeline-modal").modal("hide");
    }
    $("#convert_helper").html(data);
    $("#convert_to_" + html_helper_selector).modal({
      show: true,
      backdrop: "static",
    });
    reorder_items();
  });
}

function save_proposal_content(manual) {
  var editor = tinyMCE.activeEditor;
  var data = {};
  data.proposal_id = proposal_id;
  data.content = editor.getContent();
  $.post(admin_url + "proposals/save_proposal_data", data)
    .done(function (response) {
      response = JSON.parse(response);
      if (typeof manual != "undefined") {
        // Show some message to the user if saved via CTRL + S
        alert_float("success", response.message);
      }
      // Invokes to set dirty to false
      editor.save();
    })
    .fail(function (error) {
      var response = JSON.parse(error.responseText);
      alert_float("danger", response.message);
    });
}

// Proposal sync data in case eq mail is changed, shown for lead and customers.
function sync_proposals_data(rel_id, rel_type) {
  var data = {};
  var modal_sync = $("#sync_data_proposal_data");
  data.country = modal_sync.find('select[name="country"]').val();
  data.zip = modal_sync.find('input[name="zip"]').val();
  data.state = modal_sync.find('input[name="state"]').val();
  data.city = modal_sync.find('input[name="city"]').val();
  data.address = modal_sync.find('textarea[name="address"]').val();
  data.phone = modal_sync.find('input[name="phone"]').val();
  data.rel_id = rel_id;
  data.rel_type = rel_type;
  $.post(admin_url + "proposals/sync_data", data).done(function (response) {
    response = JSON.parse(response);
    alert_float("success", response.message);
    modal_sync.modal("hide");
  });
}

// Table announcements
function init_table_announcements(manual) {
  if (typeof manual == "undefined" && $("body").hasClass("dashboard")) {
    return false;
  }
  initDataTable(
    ".table-announcements",
    admin_url + "announcements",
    undefined,
    undefined,
    "undefined",
    [1, "desc"]
  );
}

// Table tickets

// Staff projects table in staff profile
// Shared native staff component: init_table_staff_projects (assets/js/native-admin-staff.js).

// Fix task checklist content textarea height
// Shared native task component: do_task_checklist_items_height (assets/js/native-tasks.js).

// Recalculate task checklist items progress, this progress is shown only if there is more then 2 checklist items
// Shared native task component: recalculate_checklist_items_progress (assets/js/native-tasks.js).

// Remove task checklist items template
// Shared original checklist template actions: assets/js/native-tasks.js.

// Updates task checklist items order
function update_checklist_order() {
  var order = [];
  var items = $("body").find(".checklist");
  if (items.length === 0) {
    return;
  }
  if ($("body").hasClass("native-company-admin")) {
    var ids = items.map(function () { return $(this).attr('data-checklist-id'); }).get();
    native_task_checklist_command($('#checklist-items'), 'reorder', {ids:ids}).fail(function () { alert_float('danger', 'Não foi possível alterar a ordem. Atualize a tarefa e tente novamente.'); });
    return;
  }
  var i = 1;
  $.each(items, function () {
    order.push([$(this).data("checklist-id"), i]);
    i++;
  });
  var data = {};
  data.order = order;
  $.post(admin_url + "tasks/update_checklist_order", data);
}

// New task checklist item
function add_task_checklist_item(task_id, description, e) {
  if ($("body").hasClass("native-company-admin")) {
    var container = $('#checklist-items');
    description = typeof description === 'string' ? description.trim() : '';
    if (!description) {
      var draft = $('<div><div class="checklist" data-native-checklist-draft="1"><div class="tw-flex"><div class="tw-grow"><textarea name="checklist-description" rows="1"></textarea></div><div class="mleft10"><a href="#" class="text-muted remove-checklist"><i class="fa-regular fa-trash-can"></i></a></div></div></div></div>');
      draft.find('textarea').attr('data-taskid', String(task_id));draft.find('.remove-checklist').on('click', function (event) { event.preventDefault(); draft.remove(); });container.append(draft);draft.find('textarea').trigger('focus');return;
    }
    if (e) $(e).addClass('disabled');
    native_task_checklist_command(container, 'add', {text:description}).done(function () { init_tasks_checklist_items(true, task_id); }).fail(function () { alert_float('danger', 'Não foi possível adicionar o item. Tente novamente.'); }).always(function () { if (e) $(e).removeClass('disabled'); });
    return;
  }
  if (e) {
    $(e).addClass("disabled");
  }

  description = typeof description == "undefined" ? "" : description;
  var order = $("#checklist-items").find(".checklist").length;

  $.post(admin_url + "tasks/add_checklist_item", {
    taskid: task_id,
    description: description,
    list_order: order,
  })
    .done(function () {
      init_tasks_checklist_items(true, task_id);
    })
    .always(function () {
      if (e) {
        $(e).removeClass("disabled");
      }
    });
}

function update_task_checklist_item(textArea) {
  var deferred = $.Deferred();
  setTimeout(function () {
    var description = textArea.val();
    description = description.trim();
    var listid = textArea.parents(".checklist").data("checklist-id");
    if ($("body").hasClass("native-company-admin")) {
      var item = textArea.closest('.checklist');
      if (item.attr('data-native-checklist-draft') === '1') {
        if (!description) { item.parent().remove(); deferred.resolve(); return; }
        native_task_checklist_command($('#checklist-items'), 'add', {text:description}).done(function () { init_tasks_checklist_items(true, textArea.attr('data-taskid')).done(function () { deferred.resolve(); }).fail(function () { deferred.reject(); }); }).fail(function () { alert_float('danger', 'Não foi possível adicionar o item. Tente novamente.'); deferred.reject(); });
        return;
      }
      var command = description === '' ? 'delete' : 'edit';
      var payload = {item_id:item.attr('data-checklist-id'), item_revision:item.attr('data-checklist-item-revision')};
      if (command === 'edit') payload.text = description;
      native_task_checklist_command($('#checklist-items'), command, payload).done(function () { if (command === 'delete') item.remove(); deferred.resolve(); }).fail(function () { alert_float('danger', 'Não foi possível atualizar o item. Atualize a tarefa e tente novamente.'); deferred.reject(); });
      return;
    }

    $.post(admin_url + "tasks/update_checklist_item", {
      description: description,
      listid: listid,
    }).done(function (response) {
      deferred.resolve();
      response = JSON.parse(response);
      if (response.can_be_template === true) {
        textArea
          .parents(".checklist")
          .find(".save-checklist-template")
          .removeClass("hide");
      }
      if (description === "") {
        $("#checklist-items")
          .find('.checklist[data-checklist-id="' + listid + '"]')
          .remove();
      }
    });
  }, 300);
  return deferred.promise();
}

// Remove task checklist item from the task
function delete_checklist_item(id, field) {
  if ($("body").hasClass("native-company-admin")) {
    var item = $(field).closest('.checklist');
    native_task_checklist_command($('#checklist-items'), 'delete', {item_id:item.attr('data-checklist-id'), item_revision:item.attr('data-checklist-item-revision')}).done(function () { item.remove(); recalculate_checklist_items_progress(); }).fail(function () { alert_float('danger', 'Não foi possível remover o item. Atualize a tarefa e tente novamente.'); });
    return;
  }
  requestGetJSON("tasks/delete_checklist_item/" + id).done(function (response) {
    if (response.success === true || response.success == "true") {
      $(field).parents(".checklist").remove();
      recalculate_checklist_items_progress();
    }
  });
}

// Fetches task checklist items.
function init_tasks_checklist_items(is_new, task_id) {
  return $.post(admin_url + "tasks/init_checklist_items", {
    taskid: task_id,
  }).done(function (data) {
    $("#checklist-items").html(data);
    if (typeof is_new != "undefined") {
      var last = $("#checklist-items").find(".checklist textarea").eq(-1);
      if (last.val() === "") {
        last.focus();
      }
    }
    recalculate_checklist_items_progress();
    if (!$('body').hasClass('native-company-admin')) update_checklist_order();
  });
}

function _task_attachments_more_and_less_checks() {
  var att_wrap = $("body").find(".task_attachments_wrapper");
  var attachments = att_wrap.find(".task-attachment-col");
  var taskAttachmentsMore = $("body").find(
    "#show-more-less-task-attachments-col .task-attachments-more"
  );
  if (attachments.length === 0) {
    att_wrap.remove();
  } else if (attachments.length == 2 && taskAttachmentsMore.hasClass("hide")) {
    $("body").find("#show-more-less-task-attachments-col").remove();
  } else if (
    $(".task_attachments_wrapper .task-attachment-col:visible").length === 0 &&
    !taskAttachmentsMore.hasClass("hide")
  ) {
    taskAttachmentsMore.click();
  }

  $.each($("#task-modal .comment-content"), function () {
    if ($(this).find(".task-attachment-col").length === 0) {
      $(this).find(".download-all").remove();
    }
  });
}

// Removes task single attachment
function remove_task_attachment(link, id) {
  if (confirm_delete()) {
    requestGetJSON("tasks/remove_task_attachment/" + id).done(function (
      response
    ) {
      if (response.success === true || response.success == "true") {
        $('[data-task-attachment-id="' + id + '"]').remove();
      }
      _task_attachments_more_and_less_checks();
      if (response.comment_removed) {
        $("#comment_" + response.comment_removed).remove();
      }
    });
  }
}

// Add new task comment from the modal
function add_task_comment(task_id) {
  var data = {};

  if (taskCommentAttachmentDropzone.files.length > 0) {
    taskCommentAttachmentDropzone.processQueue(task_id);
    return;
  }
  if (tinymce.activeEditor) {
    data.content = tinyMCE.activeEditor.getContent();
  } else {
    data.content = $("#task_comment").val();
    data.no_editor = true;
  }
  data.taskid = task_id;
  $.post(admin_url + "tasks/add_task_comment", data).done(function (response) {
    response = JSON.parse(response);
    _task_append_html(response.taskHtml);
    // Remove task comment editor instance
    // Causing error because of are you sure you want to leave this page, the plugin still sees as active and dirty.
    tinymce.remove("#task_comment");
  });
}

// Deletes task comment from database
function remove_task_comment(commentid) {
  if (confirm_delete()) {
    requestGetJSON("tasks/remove_comment/" + commentid).done(function (
      response
    ) {
      if (response.success === true || response.success == "true") {
        $('[data-commentid="' + commentid + '"]').remove();
        $('[data-comment-attachment="' + commentid + '"]').remove();
        _task_attachments_more_and_less_checks();
      }
    });
  }
}

// Remove task assignee
function remove_assignee(id, task_id) {
  if (confirm_delete()) {
    requestGetJSON("tasks/remove_assignee/" + id + "/" + task_id).done(
      function (response) {
        if (response.success === true || response.success == "true") {
          alert_float("success", response.message);
          _task_append_html(response.taskHtml);
        }
      }
    );
  }
}

// Remove task follower
function remove_follower(id, task_id) {
  if (confirm_delete()) {
    requestGetJSON("tasks/remove_follower/" + id + "/" + task_id).done(
      function (response) {
        if (response.success === true || response.success == "true") {
          alert_float("success", response.message);
          _task_append_html(response.taskHtml);
        }
      }
    );
  }
}

// Marking task as complete
function mark_complete(task_id) {
  task_mark_as(5, task_id);
}

// Unmarking task as complete
function unmark_complete(task_id) {
  task_mark_as(4, task_id, "tasks/unmark_complete/" + task_id);
}

// Mark task status
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

// Change task priority from sigle modal
function task_change_priority(priority_id, task_id) {
  url = "tasks/change_priority/" + priority_id + "/" + task_id;
  var taskModalVisible = $("#task-modal").is(":visible");
  url += "?single_task=" + taskModalVisible;
  var request;
  if ($("body").hasClass("native-company-admin")) {
    var row = $('[data-native-task-id]').filter(function () {
      return $(this).attr('data-native-task-id') === String(task_id);
    }).first();
    var revision = row.attr('data-task-revision');
    var fieldsRevision = row.attr('data-native-fields-revision') || '';
    if (taskModalVisible || !/^[1-4]$/.test(String(priority_id)) || !/^[1-9][0-9]*$/.test(String(task_id)) || !/^[1-9][0-9]*$/.test(revision || '') || (fieldsRevision && !/^[1-9][0-9]*$/.test(fieldsRevision))) {
      alert_float("danger", "Não foi possível confirmar a revisão desta tarefa. Atualize a página e tente novamente.");
      return;
    }
    var key = native_task_template_request_key(row, 'priority:' + priority_id + ':' + revision + ':' + fieldsRevision);
    request = $.ajax({url: admin_url + 'tasks/change_priority/' + encodeURIComponent(priority_id) + '/' + encodeURIComponent(task_id), type:'POST', dataType:'json', data:{native_revision:revision,native_fields_revision:fieldsRevision,native_request_key:key}});
  } else {
    request = requestGetJSON(url);
  }
  request.done(function (response) {
    if (response.success === true || response.success == "true") {
      reload_tasks_tables();
      if (taskModalVisible) {
        _task_append_html(response.taskHtml);
      }
    }
  }).fail(function () {
    if ($("body").hasClass("native-company-admin")) alert_float("danger", "Não foi possível alterar a prioridade da tarefa. Atualize a página e tente novamente.");
  });
}

// Change task milestone from single modal
function task_change_milestone(milestone_id, task_id) {
  url = "tasks/change_milestone/" + milestone_id + "/" + task_id;
  var taskModalVisible = $("#task-modal").is(":visible");
  url += "?single_task=" + taskModalVisible;
  requestGetJSON(url).done(function (response) {
    if (response.success === true || response.success == "true") {
      reload_tasks_tables();
      if (taskModalVisible) {
        _task_append_html(response.taskHtml);
      }
    }
  });
}

// Non finished timesheet delete, this is available for all staff
// Shared native task component: delete_user_unfinished_timesheet (assets/js/native-tasks.js).

// Reload all tasks possible table where the table data needs to be refreshed after an action is performed on task.
// Shared native task component: reload_tasks_tables (assets/js/native-tasks.js).

// Makes task public with AJAX request
function make_task_public(task_id) {
  requestGetJSON("tasks/make_public/" + task_id).done(function (response) {
    if (response.success === true || response.success == "true") {
      reload_tasks_tables();
      _task_append_html(response.taskHtml);
    }
  });
}

// New task function, various actions performed
// Shared native task component: new_task (assets/js/native-tasks.js).

// Show/hide tags placeholder
// Shared native UI helper: showHideTagsPlaceholder (assets/js/native-ui-tools.js).

// Create new task directly from relation, related options selected after modal is shown
// Shared native task component: new_task_from_relation (assets/js/native-tasks.js).

// Go to edit view
// Shared native task component: edit_task (assets/js/native-tasks.js).

// Handles task add/edit form modal.
// Shared native task component: task_form_handler (assets/js/native-tasks.js).

// Full screen white popup
// Shared native UI helper: system_popup (assets/js/native-ui-tools.js).

// Action for task timer start/stop
// Shared native task component: timer_action (assets/js/native-tasks.js).

// Init task modal and get data from server
// Shared native task component: init_task_modal (assets/js/native-tasks.js).

// General function to append task html returned from request
// Shared native task component: _task_append_html (assets/js/native-tasks.js).

// Tracking stats modal from task single
// Shared native task component: task_tracking_stats (assets/js/native-tasks.js).

// Fetches all staff timers and append to DOM
// Shared native task component: init_timers (assets/js/native-tasks.js).

// Top started timers dropdown init html data with class
// Shared native task component: _init_timers_top_html (assets/js/native-tasks.js).

// Init task edit comment
function edit_task_comment(id) {
  var edit_wrapper = $('[data-edit-comment="' + id + '"]');
  edit_wrapper.next().addClass("hide");
  edit_wrapper.removeClass("hide");

  tinymce.remove("#task_comment_" + id);
  var editorConfig = _simple_editor_config();
  editorConfig.auto_focus = "task_comment_" + id;
  editorConfig.toolbar_sticky  = true;
  init_editor("#task_comment_" + id, editorConfig);
  tinymce.triggerSave();
}

// Cancel editing commment after clicked on edit href
function cancel_edit_comment(id) {
  var edit_wrapper = $('[data-edit-comment="' + id + '"]');
  tinymce.remove('[data-edit-comment="' + id + '"] textarea');
  edit_wrapper.addClass("hide");
  edit_wrapper.next().removeClass("hide");
}

// Save task edited comment
function save_edited_comment(id, task_id) {
  tinymce.triggerSave();
  var data = {};
  data.id = id;
  data.task_id = task_id;
  data.content = $('[data-edit-comment="' + id + '"]')
    .find("textarea")
    .val();
  if (is_ios()) {
    data.no_editor = true;
  }
  $.post(admin_url + "tasks/edit_comment", data).done(function (response) {
    response = JSON.parse(response);
    if (response.success === true || response.success == "true") {
      alert_float("success", response.message);
      _task_append_html(response.taskHtml);
    } else {
      cancel_edit_comment(id);
    }
    tinymce.remove('[data-edit-comment="' + id + '"] textarea');
  });
}

// Fix task single modal height to be on both sides the same
// Shared native task component: fix_task_modal_left_col_height (assets/js/native-tasks.js).

// Updates task when action performed form kan ban area eq status changed.
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

// Init tasks kan ban
function tasks_kanban() {
  init_kanban("tasks/kanban", tasks_kanban_update, ".tasks-status", 240, 360);
}

// Task single edit description with inline editor, used from task single modal
function edit_task_inline_description(e, id) {
  tinyMCE.remove("#task_view_description");

  if ($(e).hasClass("editor-initiated")) {
    $(e).removeClass("editor-initiated");
    return;
  }

  $(e).addClass("editor-initiated");

  $.Shortcuts.stop();

  tinymce.init({
    branding: false,
    toolbar: false,
    menubar: false,
    inline: true,
    cache_suffix: '?v='+app.version,
    selector: "#task_view_description",
    theme: "silver",
    directionality: isRTL == "true" ? "rtl" : "",
    auto_focus: "task_view_description",
    plugins: ['quickbars', 'link', 'table', (isRTL == "true" ? " directionality" : "")],
    contextmenu: "link table paste pastetext",
    quickbars_insert_toolbar: "quicktable",
    quickbars_selection_toolbar: "bold italic | quicklink h2 h3 blockquote",
    table_default_styles: {
      width: "100%",
    },
    setup: function (editor) {
      editor.on("blur", function (e) {
        if (editor.isDirty()) {
          $.post(admin_url + "tasks/update_task_description/" + id, {
            description: editor.getContent(),
          });
        }

        setTimeout(function () {
          editor.remove();
          $.Shortcuts.start();
        }, 500);
      });
    },
  });
}

// Tasks bulk actions action
function tasks_bulk_action(event) {
  if (confirm_delete()) {
    var ids = [],
      data = {},
      mass_delete = $("#mass_delete").prop("checked");
    if (mass_delete == false || typeof mass_delete == "undefined") {
      data.status = $("#move_to_status_tasks_bulk_action").val();

      var assignees = $("#task_bulk_assignees");
      data.assignees = assignees.length ? assignees.selectpicker("val") : "";

      var tags_bulk = $("#tags_bulk");
      data.tags = tags_bulk.length ? tags_bulk.tagit("assignedTags") : "";

      var milestone = $("#task_bulk_milestone");
      data.milestone = milestone.length ? milestone.selectpicker("val") : "";

      data.billable = $("#task_bulk_billable").val();
      data.billable = typeof data.billable == "undefined" ? "" : data.billable;

      data.priority = $("#task_bulk_priority").val();
      data.priority = typeof data.priority == "undefined" ? "" : data.priority;

      if (
        data.status === "" &&
        data.priority === "" &&
        data.tags === "" &&
        data.assignees === "" &&
        data.milestone === "" &&
        data.billable === ""
      ) {
        return;
      }
    } else {
      data.mass_delete = true;
    }
    var rows = $($("#tasks_bulk_actions").attr("data-table")).find("tbody tr");
    $.each(rows, function () {
      var checkbox = $($(this).find("td").eq(0)).find("input");
      if (checkbox.prop("checked") === true) {
        ids.push(checkbox.val());
      }
    });
    data.ids = ids;
    $(event).addClass("disabled");
    setTimeout(function () {
      $.post(admin_url + "tasks/bulk_action", data).done(function () {
        window.location.reload();
      });
    }, 200);
  }
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

// Init single invoice
function init_invoice(id) {
  load_small_table_item(
    id,
    "#invoice",
    "invoiceid",
    "invoices/get_invoice_data_ajax",
    ".table-invoices"
  );
}

// Init single credit note
function init_credit_note(id) {
  load_small_table_item(
    id,
    "#credit_note",
    "credit_note_id",
    "credit_notes/get_credit_note_data_ajax",
    ".table-credit-notes"
  );
}

// Init single estimate
function init_estimate(id) {
  load_small_table_item(
    id,
    "#estimate",
    "estimateid",
    "estimates/get_estimate_data_ajax",
    ".table-estimates"
  );
}

// Init single proposal
function init_proposal(id) {
  load_small_table_item(
    id,
    "#proposal",
    "proposal_id",
    "proposals/get_proposal_data_ajax",
    ".table-proposals"
  );
}

function init_expense(id) {
  load_small_table_item(
    id,
    "#expense",
    "expenseid",
    "expenses/get_expense_data_ajax",
    ".table-expenses"
  );
}

// Clear billing and shipping inputs for invoice,estimate etc...


// Init billing and shipping details for invoice, estimate etc...


// Record payment function
function record_payment(id) {
  if (typeof id == "undefined" || id === "") {
    return;
  }
  $("#invoice").load(admin_url + "invoices/record_invoice_payment_ajax/" + id);
}

function schedule_invoice_send(id) {
  $("#invoice").load(admin_url + "email_schedule_invoice/create/" + id);
}

function schedule_estimate_send(id) {
  $("#estimate").load(admin_url + "email_schedule_estimate/create/" + id);
}

function edit_invoice_scheduled_email(schedule_id) {
  $("#invoice").load(admin_url + "email_schedule_invoice/edit/" + schedule_id);
}

function edit_estimate_scheduled_email(schedule_id) {
  $("#estimate").load(
    admin_url + "email_schedule_estimate/edit/" + schedule_id
  );
}

// Add item to preview



// Add task to preview


// Clear the items added to preview


// Append the added items to the preview to the table as items


// Get taxes dropdown selectpicker template


// Custom function for deselecting selected value from ajax dropdown
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

// Ajax project search but only for specific customer


// Ajax project search select


// Make items sortable with jquery sort plugin


// Save the items from order from the admin preview
// save_ei_items_order is shared from native-admin-item-order.js.

// Reoder the items in table edit for estimate and invoices


// Get the preview main values


// Calculate invoice total - NOT RECOMENDING EDIT THIS FUNCTION BECUASE IS VERY SENSITIVE




// Deletes invoice items


// Format money function


// Set the currency for accounting


// Delete credit note attachment
function delete_credit_note_attachment(id) {
  if (confirm_delete()) {
    requestGet("credit_notes/delete_attachment/" + id)
      .done(function (success) {
        if (success == 1) {
          $("body")
            .find('[data-attachment-id="' + id + '"]')
            .remove();
          init_credit_note(
            $("body").find('input[name="_attachment_sale_id"]').val()
          );
        }
      })
      .fail(function (error) {
        alert_float("danger", error.responseText);
      });
  }
}

// Delete estimate attachment
function delete_estimate_attachment(id) {
  if (confirm_delete()) {
    requestGet("estimates/delete_attachment/" + id)
      .done(function (success) {
        if (success == 1) {
          $("body")
            .find('[data-attachment-id="' + id + '"]')
            .remove();
          var rel_id = $("body")
            .find('input[name="_attachment_sale_id"]')
            .val();
          $("body").hasClass("estimates-pipeline")
            ? estimate_pipeline_open(rel_id)
            : init_estimate(rel_id);
        }
      })
      .fail(function (error) {
        alert_float("danger", error.responseText);
      });
  }
}

// Delete proposal attachment
function delete_proposal_attachment(id) {
  if (confirm_delete()) {
    requestGet("proposals/delete_attachment/" + id)
      .done(function (success) {
        if (success == 1) {
          var rel_id = $("body")
            .find('input[name="_attachment_sale_id"]')
            .val();
          $("body")
            .find('[data-attachment-id="' + id + '"]')
            .remove();
          $("body").hasClass("proposals-pipeline")
            ? proposal_pipeline_open(rel_id)
            : init_proposal(rel_id);
        }
      })
      .fail(function (error) {
        alert_float("danger", error.responseText);
      });
  }
}

// Invoices quick total stats
function init_invoices_total(manual) {
  var _inv_total_wrapper = $("#invoices_total");

  if (_inv_total_wrapper.length === 0) {
    return;
  }
  var _inv_total_inline = $(".invoices-total-inline");
  var _inv_total_href_manual = $(".invoices-total");

  if (
    $("body").hasClass("invoices-total-manual") &&
    typeof manual == "undefined" &&
    !_inv_total_href_manual.hasClass("initialized")
  ) {
    return;
  }

  if (
    _inv_total_inline.length > 0 &&
    _inv_total_href_manual.hasClass("initialized")
  ) {
    // On the next request won't be inline in case of currency change
    // Used on dashboard
    _inv_total_inline.removeClass("invoices-total-inline");
    return;
  }

  _inv_total_href_manual.addClass("initialized");
  var _years = $("body")
    .find('select[name="invoices_total_years"]')
    .selectpicker("val");
  var years = [];
  $.each(_years, function (i, _y) {
    if (_y !== "") {
      years.push(_y);
    }
  });

  var currency = $("body").find('select[name="total_currency"]').val();
  
  var data = {
    currency: currency,
    years: years,
    init_total: true,
    display_type: _inv_total_wrapper.attr('data-type')
  };

  var project_id = $('input[name="project_id"]').val();
  var customer_id = $('.customer_profile input[name="userid"]').val();
  if (typeof project_id != "undefined") {
    data.project_id = project_id;
  } else if (typeof customer_id != "undefined") {
    data.customer_id = customer_id;
  }
  $.post(admin_url + "invoices/get_invoices_total", data).done(function (
    response
  ) {
    _inv_total_wrapper.html(response);
  });
}

// Estimates quick total stats
function init_estimates_total(manual) {
  if ($("#estimates_total").length === 0) {
    return;
  }
  var _est_total_href_manual = $(".estimates-total");
  if (
    $("body").hasClass("estimates-total-manual") &&
    typeof manual == "undefined" &&
    !_est_total_href_manual.hasClass("initialized")
  ) {
    return;
  }
  _est_total_href_manual.addClass("initialized");
  var currency = $("body").find('select[name="total_currency"]').val();
  var _years = $("body")
    .find('select[name="estimates_total_years"]')
    .selectpicker("val");
  var years = [];
  $.each(_years, function (i, _y) {
    if (_y !== "") {
      years.push(_y);
    }
  });

  var customer_id = "";
  var project_id = "";

  var _customer_id = $('.customer_profile input[name="userid"]').val();
  var _project_id = $('input[name="project_id"]').val();
  if (typeof _customer_id != "undefined") {
    customer_id = _customer_id;
  } else if (typeof _project_id != "undefined") {
    project_id = _project_id;
  }

  $.post(admin_url + "estimates/get_estimates_total", {
    currency: currency,
    init_total: true,
    years: years,
    customer_id: customer_id,
    project_id: project_id,
  }).done(function (response) {
    $("#estimates_total").html(response);
  });
}

// Expenses quick total stats
function init_expenses_total() {
  if ($("#expenses_total").length === 0) {
    return;
  }
  var currency = $("body").find('select[name="expenses_total_currency"]').val();
  var _years = $("body")
    .find('select[name="expenses_total_years"]')
    .selectpicker("val");
  var years = [];
  $.each(_years, function (i, _y) {
    if (_y !== "") {
      years.push(_y);
    }
  });

  var customer_id = "";
  var _customer_id = $('.customer_profile input[name="userid"]').val();
  if (typeof customer_id != "undefined") {
    customer_id = _customer_id;
  }

  var project_id = "";
  var _project_id = $('input[name="project_id"]').val();
  if (typeof project_id != "undefined") {
    project_id = _project_id;
  }

  $.post(admin_url + "expenses/get_expenses_total", {
    currency: currency,
    init_total: true,
    years: years,
    customer_id: customer_id,
    project_id: project_id,
  }).done(function (response) {
    $("#expenses_total").html(response);
  });
}

// Validate invoice add/edit form


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

// Validates estimate add/edit form
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

// Sort estimates in the pipeline view / switching sort type by click
function estimates_pipeline_sort(type) {
  kan_ban_sort(type, estimate_pipeline);
}

// Sort proposals in the pipeline view / switching sort type by click
function proposal_pipeline_sort(type) {
  kan_ban_sort(type, proposals_pipeline);
}

// Init estimates pipeline
function estimate_pipeline() {
  init_kanban(
    "estimates/get_pipeline",
    estimates_pipeline_update,
    ".pipeline-status",
    290,
    360
  );
}

// Used when estimate is updated from pipeline. eq changed order or moved to another status
function estimates_pipeline_update(ui, object) {
  if (object === ui.item.parent()[0]) {
    var data = {
      estimateid: $(ui.item).attr("data-estimate-id"),
      status: $(ui.item.parent()[0]).attr("data-status-id"),
      order: [],
    };

    $.each(
      $(ui.item).parents(".pipeline-status").find("li"),
      function (idx, el) {
        var id = $(el).attr("data-estimate-id");
        if (id) {
          data.order.push([id, idx + 1]);
        }
      }
    );

    check_kanban_empty_col("[data-estimate-id]");

    setTimeout(function () {
      $.post(admin_url + "estimates/update_pipeline", data).done(function (
        response
      ) {
        update_kan_ban_total_when_moving(ui, data.status);
        estimate_pipeline();
      });
    }, 200);
  }
}

// Used when proposal is updated from pipeline. eq changed order or moved to another status
function proposals_pipeline_update(ui, object) {
  if (object === ui.item.parent()[0]) {
    var data = {
      order: [],
      status: $(ui.item.parent()[0]).attr("data-status-id"),
      proposalid: $(ui.item).attr("data-proposal-id"),
    };

    $.each(
      $(ui.item).parents(".pipeline-status").find("li"),
      function (idx, el) {
        var id = $(el).attr("data-proposal-id");
        if (id) {
          data.order.push([id, idx + 1]);
        }
      }
    );

    check_kanban_empty_col("[data-proposal-id]");

    setTimeout(function () {
      $.post(admin_url + "proposals/update_pipeline", data).done(function (
        response
      ) {
        update_kan_ban_total_when_moving(ui, data.status);
        proposals_pipeline();
      });
    }, 200);
  }
}

// Init proposals pipeline
function proposals_pipeline() {
  init_kanban(
    "proposals/get_pipeline",
    proposals_pipeline_update,
    ".pipeline-status",
    290,
    360
  );
}

// Open single proposal in pipeline
function proposal_pipeline_open(id) {
  if (id === "") {
    return;
  }
  requestGet("proposals/pipeline_open/" + id).done(function (response) {
    var visible = $(".proposal-pipeline-modal:visible").length > 0;
    $("#proposal").html(response);
    if (!visible) {
      $(".proposal-pipeline-modal").modal({
        show: true,
        backdrop: "static",
        keyboard: false,
      });
    } else {
      $("#proposal")
        .find(".modal.proposal-pipeline-modal")
        .removeClass("fade")
        .addClass("in")
        .css("display", "block");
    }
  });
}

// Estimate single open in pipeline
function estimate_pipeline_open(id) {
  if (id === "") {
    return;
  }
  requestGet("estimates/pipeline_open/" + id).done(function (response) {
    var visible = $(".estimate-pipeline:visible").length > 0;
    $("#estimate").html(response);
    if (!visible) {
      $(".estimate-pipeline").modal({
        show: true,
        backdrop: "static",
        keyboard: false,
      });
    } else {
      $("#estimate")
        .find(".modal.estimate-pipeline")
        .removeClass("fade")
        .addClass("in")
        .css("display", "block");
    }
  });
}

// Proposal merge field into the editor
function insert_proposal_merge_field(field) {
  tinymce.activeEditor.execCommand("mceInsertContent", false, $(field).text());
}

// Toggle full view for small tables like proposals
function small_table_full_view() {
  $("#small-table").toggleClass("hide");
  $(".small-table-right-col").toggleClass("col-md-12 col-md-7");
  $(window).trigger("resize");
}

// Used to update manually the prefix after invoice/estimate is created.
function save_sales_number_settings(e) {
  var data = {};
  data.prefix = $("body").find('input[name="s_prefix"]').val();
  $.post($(e).data("url"), data).done(function (response) {
    response = JSON.parse(response);
    if (response.success && response.message) {
      alert_float("success", response.message);
      $("#prefix").html(data.prefix);
    }
  });
}

// Prefix for invoices/estimates in case there is year.
function do_prefix_year(date) {
  var date_array = _split_formatted_date_by_separator(date);

  if (typeof date_array != "undefined") {
    $.each(date_array, function (i, string) {
      if (string.length == 4) {
        var $pYear = $("#prefix_year");
        if ($pYear.hasClass("format-n-yy")) {
          string = string.substr(-2);
        } else if ($pYear.hasClass("format-mm-yyyy")) {
          var month_index;
          if (
            app.options.date_format == "d-m-Y" ||
            app.options.date_format == "d/m/Y" ||
            app.options.date_format == "Y-m-d" ||
            app.options.date_format == "d.m.Y"
          ) {
            month_index = 1;
          } else if (
            app.options.date_format == "m-d-Y" ||
            app.options.date_format == "m.d.Y" ||
            app.options.date_format == "m/d/Y"
          ) {
            month_index = 0;
          }
          $("#prefix_month").html(date_array[month_index]);
        }
        $pYear.html(string);
      }
    });
  }
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

function init_tabs_scrollable() {
  // Not working fine on RTL
  if (isRTL != "true") {
    if ($(window).width() <= 768) {
      $("body").find(".toggle_view").remove();
    }
    // Horinzontal tabs
    $(".horizontal-scrollable-tabs").horizontalTabs();
  } else {
    $(".arrow-left, .arrow-right").css("display", "none");
    $(".horizontal-scrollable-tabs").removeClass("horizontal-scrollable-tabs");
    $(".nav-tabs-horizontal").removeClass("nav-tabs-horizontal");
  }
}

function view_contact_consent(id) {
  requestGet("clients/consents/" + id).done(function (response) {
    $("#consent_data").html(response);
    initDataTableInline($("#consentHistoryTable"));
    $("#consentModal").modal("show");
  });
}

function view_lead_consent(id) {
  window.location.hash = "gdpr";
  init_lead(id);
}

function set_notification_read_inline(id) { return NativeAdminNotifications.markOne(id); }
function mark_all_notifications_as_read_inline() { return NativeAdminNotifications.markAll(); }

// Deletes activity for sales eq. invoices, estimates.
function delete_sale_activity(id) {
  if (confirm_delete()) {
    requestGet("misc/delete_sale_activity/" + id).done(function () {
      $("body")
        .find('[data-sale-activity-id="' + id + '"]')
        .remove();
    });
  }
}

// Calendar handlers live in shared native-admin-calendar.js.

// Fetches notifications through the shared native interaction.
function fetch_notifications(callback) { return NativeAdminNotifications.fetch(callback); }

// Shared native task component: init_new_task_comment (assets/js/native-tasks.js).

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

// Shared native UI helper: init_ajax_search (assets/js/native-ui-tools.js).

// Used for email template URL
function merge_field_format_url(url, node, on_save, name) {
  // Merge fields url
  if (url && url.indexOf("%7B") > -1 && url.indexOf("%7D") > -1) {
    url = url.replaceAll("%7B", "{").replaceAll("%7D", "}");
  }

  return url;
}

function salesGoogleDriveSave(pickData) {
  salesExtenalFileUpload(pickData, "gdrive");
}

function leadExternalFileUpload(files, externalType, leadId) {
  $.post(admin_url + "leads/add_external_attachment", {
    files: files,
    lead_id: leadId,
    external: externalType,
  }).done(function () {
    init_lead_modal_data(leadId);
  });
}

function taskExternalFileUpload(files, externalType, taskId) {
  $.post(admin_url + "tasks/add_external_attachment", {
    files: files,
    task_id: taskId,
    external: externalType,
  }).done(function () {
    init_task_modal(taskId);
  });
}

function salesExtenalFileUpload(files, externalType) {
  var _data = {};
  _data.rel_id = $("body").find('input[name="_attachment_sale_id"]').val();
  _data.type = $("body").find('input[name="_attachment_sale_type"]').val();
  _data.files = files;
  _data.external = externalType;
  $.post(admin_url + "misc/add_sales_external_attachment", _data).done(
    function () {
      if (_data.type == "estimate") {
        if ($("body").hasClass("estimates-pipeline")) {
          estimate_pipeline_open(_data.rel_id);
        } else {
          init_estimate(_data.rel_id);
        }
      } else if (_data.type == "proposal") {
        if ($("body").hasClass("proposals-pipeline")) {
          proposal_pipeline_open(_data.rel_id);
        } else {
          init_proposal(_data.rel_id);
        }
      } else {
        if (typeof window["init_" + _data.type] == "function") {
          window["init_" + _data.type](_data.rel_id);
        }
      }
      $("#sales_attach_file").modal("hide");
    }
  );
}

function set_search_history(history) { NativeAdminSearch.setHistory(history); }

// General helper function for $.get ajax requests
// Shared native UI helper: requestGet (assets/js/native-ui-tools.js).

// General helper function for $.get ajax requests with dataType JSON
// Shared native UI helper: requestGetJSON (assets/js/native-ui-tools.js).

// Templates Js
function update_templates_count(type) {
  var count = $(".templates-wrapper").length;
  $(".total_templates").text(count);
  if (count === 0) {
    $(".total_templates").addClass("hide");
  } else {
    $(".total_templates").removeClass("hide");
  }
}

function get_templates(rel_type, rel_id) {
  if (rel_type === "proposals") {
    $("#proposal-templates").load(
      admin_url + "templates",
      {
        rel_type: rel_type,
        rel_id: rel_id,
      },
      function () {
        update_templates_count();
      }
    );
  } else if (rel_type === "contracts") {
    $("#contract-templates").load(
      admin_url + "templates",
      {
        rel_type: rel_type,
        rel_id: rel_id,
      },
      function () {
        update_templates_count();
      }
    );
  }
}

function add_template(rel_type, rel_id) {
  $("#modal-wrapper").load(
    admin_url + "templates/modal",
    {
      slug: "new",
      rel_type: rel_type,
      rel_id: rel_id,
    },

    function () {
      if ($("#TemplateModal").is(":hidden")) {
        $("#TemplateModal").modal({
          backdrop: "static",
          show: true,
        });
      }

      appValidateForm($("#template-form"), {
        name: "required",
      });

      tinymce.remove("#content");

      init_editor("#content", {
        pagebreak_split_block: true,
        contextmenu:
          "link image inserttable | cell row column deletetable | paste copy | pagebreak",
        append_plugins: "pagebreak",
      });
    }
  );
}

function edit_template(rel_type, id, rel_id) {
  $("#modal-wrapper").load(
    admin_url + "templates/modal",
    {
      slug: "edit",
      id: id,
      rel_type: rel_type,
      rel_id: rel_id,
    },

    function () {
      if ($("#TemplateModal").is(":hidden")) {
        $("#TemplateModal").modal({
          backdrop: "static",
          show: true,
        });
      }

      appValidateForm($("#template-form"), {
        name: "required",
      });

      tinymce.remove("#content");

      init_editor("#content", {
        pagebreak_split_block: true,
        contextmenu:
          "link image inserttable | cell row column deletetable | paste copy | pagebreak",
        append_plugins: "pagebreak",
      });
    }
  );
}

function delete_template(wrapper, rel_type, id) {
  if (confirm_delete()) {
    $.post(admin_url + "templates/delete/" + id).done(function (response) {
      response = JSON.parse(response);

      if (response.success === true || response.success == "true") {
        if (rel_type === "proposals") {
          $(wrapper).parents(".proposal-templates-wrapper").html("");
        } else if (rel_type === "contracts") {
          $(wrapper).parents(".contract-templates-wrapper").html("");
        }

        get_templates(rel_type);
      }
    });
  }
}

function insert_template(wrapper, rel_type, id) {
  requestGetJSON(admin_url + "templates/index/" + id).done(function (response) {
    var data = response.data;
    tinymce.activeEditor.execCommand("mceInsertContent", false, data.content);
    if (rel_type == "proposals") {
      $('a[aria-controls="tab_proposal"]').click();
    } else if (rel_type == "contracts") {
      $('a[aria-controls="tab_content"]').click();
    }
    tinymce.activeEditor.focus();
  });
}


function add_batch_payment() {
  $("#modal-wrapper").load(
    admin_url + "payments/batch_payment_modal",
    function () {
      if ($("#batch-payment-modal").is(":hidden")) {
        $("#batch-payment-modal").modal({
          backdrop: "static",
          show: true,
        });
      }
      init_selectpicker();
      init_datepicker();

      var $filterByClientSelect = $("#batch-payment-filter");
      $filterByClientSelect.on("changed.bs.select", function () {
        if ($filterByClientSelect.val() !== "") {
          $(".batch_payment_item").each(function () {
            if ($(this).data("clientid") == $filterByClientSelect.val()) {
              $(this).find("input, select").prop("disabled", false);
              $(this).removeClass("hide");
            } else {
              $(this).addClass("hide");
              $(this).find("input, select").prop("disabled", true);
            }
          });
        } else {
          $(".batch_payment_item").each(function () {
            $(this).removeClass("hide");
            $(this).find("input, select").prop("disabled", false);
          });
        }
      });
      appValidateForm($("#batch-payment-form"), {});
      $(".batch_payment_item").each(function () {
        var invoiceLine = $(this).find('[name^="invoice"]');

        invoiceLine
          .filter('select[name$="[paymentmode]"],input[name$="[amount]"]')
          .each(function () {
            var field = $(this);
            field.rules("add", {
              required: function () {
                var isRequired = false;
                var rowFields = field
                  .closest(".batch_payment_item")
                  .find("input, select");
                rowFields
                  .filter(
                    'select[name$="[paymentmode]"],input[name$="[transactionid]"],input[name$="[amount]"]'
                  )
                  .each(function () {
                    if ($(this).val() != "") {
                      isRequired = true;
                    }

                    if ($(this).hasClass("selectpicker") && isRequired) {
                      field.prop("required", true);
                      $(this).selectpicker("refresh");
                    }
                  });
                return isRequired;
              },
            });
          });
      });
    }
  );
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

/**
 * @DEPRECATED FUNCTIONS
 */

/**
 * @deprecated
 */
function initDatatableOffline(dt_table) {
  console.warn(
    '"initDatatableOffline" is deprecated, use "initDataTableInline" instead.'
  );
  initDataTableInline(dt_table);
}

/**
 * @deprecated
 * @since  2.3.2
 */
