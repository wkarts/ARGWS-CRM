/* Shared ORIGINAL staff-table interaction, loaded in both native shells. */
function init_table_staff_projects(manual) {
  if (typeof manual == "undefined" && $("body").hasClass("dashboard")) {
    return false;
  }
  if ($("body").find(".table-staff-projects").length === 0) {
    return;
  }

  var staffProjectsParams = {},
    Staff_Projects_Filters = $(
      "._hidden_inputs._filters.staff_projects_filter input"
    );

  $.each(Staff_Projects_Filters, function () {
    staffProjectsParams[$(this).attr("name")] =
      '[name="' + $(this).attr("name") + '"]';
  });

  initDataTable(
    ".table-staff-projects",
    admin_url + "projects/staff_projects",
    "undefined",
    "undefined",
    staffProjectsParams,
    [2, "asc"]
  );
}

function init_roles_permissions(roleid, user_changed) {
  roleid =
    typeof roleid == "undefined" ? $('select[name="role"]').val() : roleid;
  var isedit = $('.member > input[name="isedit"]');

  // Check if user is edit view and user has changed the dropdown permission if not only return
  if (
    isedit.length > 0 &&
    typeof roleid !== "undefined" &&
    typeof user_changed == "undefined"
  ) {
    return;
  }

  // Administrators does not have permissions
  if ($('input[name="administrator"]').prop("checked") === true) {
    return;
  }

  // Last if the roleid is blank return
  if (roleid === "") {
    return;
  }

  // Get all permissions
  var permissions = $("table.roles").find("tr");
  requestGetJSON("staff/role_changed/" + roleid).done(function (response) {
    permissions
      .find(".capability")
      .not('[data-not-applicable="true"]')
      .prop("checked", false)
      .trigger("change");

    $.each(permissions, function () {
      var row = $(this);
      $.each(response, function (feature, obj) {
        if (row.data("name") == feature) {
          $.each(obj, function (i, capability) {
            row
              .find('input[id="' + feature + "_" + capability + '"]')
              .prop("checked", true);
            if (capability == "view") {
              row.find("[data-can-view]").change();
            } else if (capability == "view_own") {
              row.find("[data-can-view-own]").change();
            }
          });
        }
      });
    });
  });
}
