/* Shared native note implementation; one source for all native sales routes. */
/* Global function for editing notes */
function toggle_edit_note(id) {
  $("body")
    .find('[data-note-edit-textarea="' + id + '"]')
    .toggleClass("hide");
  $("body")
    .find('[data-note-description="' + id + '"]')
    .toggleClass("hide");
}

// Global function to edit note
function edit_note(id) {
  var description = $("body")
    .find('[data-note-edit-textarea="' + id + '"] textarea')
    .val();
  if (description !== "") {
    $.post(admin_url + "misc/edit_note/" + id, {
      description: description,
      revision: $('[data-note-edit-textarea="' + id + '"]').closest('.sales-note-wrapper').attr('data-note-revision'),
    }).done(function (response) {
      response = JSON.parse(response);
      if (response.success === true || response.success == "true") {
        alert_float("success", response.message);
        if (response.revision !== undefined) $('[data-note-edit-textarea="' + id + '"]').closest('.sales-note-wrapper').attr('data-note-revision', response.revision);
        $("body")
          .find('[data-note-description="' + id + '"]')
          .html(response.description);
      }
    });
    toggle_edit_note(id);
  }
}

// Delete estimate note
function delete_sales_note(wrapper, id) {
  if (confirm_delete()) {
    var revision = $(wrapper).closest('.sales-note-wrapper').attr('data-note-revision');
    var request = revision === undefined ? requestGetJSON("misc/delete_note/" + id)
      : $.ajax({url: admin_url + "misc/delete_note/" + id, method: "POST", dataType: "json", data: {revision: revision}});
    request.done(function (response) {
      if (response.success === true || response.success == "true") {
        $(wrapper).parents(".sales-note-wrapper").remove();
        var salesNotesWrapper = $("#sales-notes-wrapper");
        var totalNotesNow = salesNotesWrapper.attr("data-total") - 1;
        var notesTotal = $(".notes-total");
        salesNotesWrapper.attr("data-total", totalNotesNow);
        if (totalNotesNow <= 0) {
          notesTotal.addClass("hide");
        } else {
          notesTotal.html('<span class="badge">' + totalNotesNow + "</span>");
        }
      }
    });
  }
}

// Get all estimate notes
function get_sales_notes(id, controller) {
  requestGet(controller + "/get_notes/" + id).done(function (response) {
    $("#sales_notes_area").html(response);
    var totalNotesNow = $("#sales-notes-wrapper").attr("data-total");
    if (totalNotesNow > 0) {
      $(".notes-total")
        .html('<span class="badge">' + totalNotesNow + "</span>")
        .removeClass("hide");
    }
  });
}

