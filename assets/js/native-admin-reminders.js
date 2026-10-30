/* Shared native reminder handlers, extracted unchanged from main.js. */
// Validate the form reminder
function init_form_reminder(rel_type) {
  var forms = !rel_type
    ? $('[id^="form-reminder-"]')
    : $("#form-reminder-" + rel_type);

  $.each(forms, function (i, form) {
    $(form).appFormValidator({
      rules: {
        date: "required",
        staff: "required",
        description: "required",
      },
      submitHandler: reminderFormHandler,
    });
  });
}

// New task reminder custom function
function new_task_reminder(id) {
  var $container = $("#newTaskReminderToggle");
  if (
    !$container.is(":visible") ||
    ($container.is(":visible") && $container.attr("data-edit") != undefined)
  ) {
    $container.slideDown(400, function () {
      fix_task_modal_left_col_height();
    });

    $("#taskReminderFormSubmit").html(app.lang.create_reminder);
    $container
      .find("form")
      .attr("action", admin_url + "tasks/add_reminder/" + id);

    $container.find("#description").val("");
    $container.find("#date").val("");
    $container
      .find("#staff")
      .selectpicker(
        "val",
        $container.find("#staff").attr("data-current-staff")
      );
    $container.find("#notify_by_email").prop("checked", false);
    if ($container.attr("data-edit") != undefined) {
      $container.removeAttr("data-edit");
    }
    if (!$container.isInViewport()) {
      $("#task-modal").animate(
        {
          scrollTop: $container.offset().top + "px",
        },
        "fast"
      );
    }
  } else {
    $container.slideUp();
  }
}

// Edit reminder function
function edit_reminder(id, e) {
  requestGetJSON("misc/get_reminder/" + id).done(function (response) {
    var $container = $(
      ".reminder-modal-" + response.rel_type + "-" + response.rel_id
    );
    var actionURL = admin_url + "misc/edit_reminder/" + id;
    if ($container.length === 0 && $("body").hasClass("all-reminders")) {
      // maybe from view all reminders?
      $container = $(".reminder-modal--");
      $container.find('input[name="rel_type"]').val(response.rel_type);
      $container.find('input[name="rel_id"]').val(response.rel_id);
    } else if ($("#task-modal").is(":visible")) {
      $container = $("#newTaskReminderToggle");

      if ($container.attr("data-edit") && $container.attr("data-edit") == id) {
        $container.slideUp();
        $container.removeAttr("data-edit");
      } else {
        $container.slideDown(400, function () {
          fix_task_modal_left_col_height();
        });
        $container.attr("data-edit", id);
        if (!$container.isInViewport()) {
          $("#task-modal").animate(
            {
              scrollTop: $container.offset().top + "px",
            },
            "fast"
          );
        }
      }
      actionURL = admin_url + "tasks/edit_reminder/" + id;
      $("#taskReminderFormSubmit").html(app.lang.save);
    }

    $container.find("form").attr("action", actionURL);
    if (response.revision !== undefined) {
      var revisionInput = $container.find('input[name="revision"]');
      if (!revisionInput.length) revisionInput = $('<input type="hidden" name="revision">').appendTo($container.find("form"));
      revisionInput.val(response.revision);
    }
    // For focusing the date field
    $container.find("form").attr("data-edit", true);
    $container.find("#description").val(response.description);
    $container.find("#date").val(response.date);
    $container.find("#staff").selectpicker("val", response.staff);
    $container
      .find("#notify_by_email")
      .prop("checked", response.notify_by_email == 1 ? true : false);
    if ($container.hasClass("modal")) {
      $container.modal("show");
    }
  });
}

// Handles reminder modal form
function reminderFormHandler(form) {
  form = $(form);
  var data = form.serialize();
  $.post(form.attr("action"), data).done(function (data) {
    data = JSON.parse(data);
    if (data.message !== "") {
      alert_float(data.alert_type, data.message);
    }
    form.trigger("reinitialize.areYouSure");
    if ($("#task-modal").is(":visible")) {
      _task_append_html(data.taskHtml);
    }
    reload_reminders_tables();
  });

  if ($("body").hasClass("all-reminders")) {
    $(".reminder-modal--").modal("hide");
  } else {
    $(
      ".reminder-modal-" +
        form.find('[name="rel_type"]').val() +
        "-" +
        form.find('[name="rel_id"]').val()
    ).modal("hide");
  }

  return false;
}

// Reloads reminders table eq when reminder is deleted
function reload_reminders_tables() {
  var available_reminders_table = [
    ".table-reminders",
    ".table-reminders-leads",
    ".table-my-reminders",
  ];

  $.each(available_reminders_table, function (i, table) {
    if ($.fn.DataTable.isDataTable(table)) {
      $("body").find(table).DataTable().ajax.reload();
    }
  });
}


jQuery(function () {
  // On hidden modal reminder set all values to empty and set the form action to ADD in case edit was clicked
  $("body").on("hidden.bs.modal", ".modal-reminder", function (e) {
    var $this = $(this);
    var rel_id = $this.find('input[name="rel_id"]').val();
    var rel_type = $this.find('input[name="rel_type"]').val();
    $this
      .find("form")
      .attr(
        "action",
        admin_url + "misc/add_reminder/" + rel_id + "/" + rel_type
      );
    $this.find("form").removeAttr("data-edit");
    $this.find(":input:not([type=hidden]), textarea").val("");
    $this.find('input[type="checkbox"]').prop("checked", false);
    $this.find("select").selectpicker("val", "");
  });

  // Focus the date field on reminder modal shown
  $("body").on("shown.bs.modal", ".modal-reminder", function (e) {
    if ($(this).find('form[data-edit="true"]').length == 0) {
      $(this).find("#date").focus();
    }
  });

  // On delete reminder reload the tables
  $("body").on("click", ".delete-reminder", function () {
    if (confirm_delete()) {
      var revision = $(this).attr("data-reminder-revision");
      var request = revision === undefined ? requestGetJSON($(this).attr("href"))
        : $.ajax({url: $(this).attr("href"), method: "POST", dataType: "json", data: {revision: revision}});
      request.done(function (response) {
        alert_float(response.alert_type, response.message);
        if ($("#task-modal").is(":visible")) {
          _task_append_html(response.taskHtml);
        }
        reload_reminders_tables();
      });
    }
    return false;
  });

});
