/* Shared native todo implementation; one source for principal and selected companies. */
// Update todo items when drop happen
function update_todo_items() {
  var unfinished_items = $(".unfinished-todos li:not(.no-todos)");
  var finished = $(".finished-todos li:not(.no-todos)");
  var i = 1;
  // Refresh orders
  $.each(unfinished_items, function () {
    $(this).find('input[name="todo_order"]').val(i);
    $(this).find('input[name="finished"]').val(0);
    i++;
  });
  if (unfinished_items.length === 0) {
    $(".nav-total-todos").addClass("hide");
    $(".unfinished-todos li.no-todos").removeClass("hide");
  } else if (unfinished_items.length > 0) {
    if (!$(".unfinished-todos li.no-todos").hasClass("hide")) {
      $(".unfinished-todos li.no-todos").addClass("hide");
    }
    $(".nav-total-todos").removeClass("hide").html(unfinished_items.length);
  }
  var x = 1;
  $.each(finished, function () {
    $(this).find('input[name="todo_order"]').val(x);
    $(this).find('input[name="finished"]').val(1);
    $(this).find('input[type="checkbox"]').prop("checked", true);
    i++;
    x++;
  });
  if (finished.length === 0) {
    $(".finished-todos li.no-todos").removeClass("hide");
  } else if (finished.length > 0) {
    if (!$(".finished-todos li.no-todos").hasClass("hide")) {
      $(".finished-todos li.no-todos").addClass("hide");
    }
  }
  var update = [];
  $.each(unfinished_items, function () {
    var description = $(this).find(".todo-description");
    if (description.hasClass("line-throught")) {
      description.removeClass("line-throught");
    }
    $(this).find('input[type="checkbox"]').prop("checked", false);
    var item = [
      $(this).find('input[name="todo_id"]').val(),
      $(this).find('input[name="todo_order"]').val(),
      $(this).find('input[name="finished"]').val(),
    ];
    var revision = $(this).attr("data-todo-revision");
    if (revision !== undefined) item.push(revision);
    update.push(item);
  });
  $.each(finished, function () {
    var description = $(this).find(".todo-description");
    if (!description.hasClass("line-throught")) {
      description.addClass("line-throught");
    }
    var item = [
      $(this).find('input[name="todo_id"]').val(),
      $(this).find('input[name="todo_order"]').val(),
      $(this).find('input[name="finished"]').val(),
    ];
    var revision = $(this).attr("data-todo-revision");
    if (revision !== undefined) item.push(revision);
    update.push(item);
  });
  var data = {};
  data.data = update;
  $.post(admin_url + "todo/update_todo_items_order", data).done(function (response) {
    if (typeof response === "string" && response) response = JSON.parse(response);
    if (response && response.revisions) {
      Object.keys(response.revisions).forEach(function (id) {
        $('.todo li[data-todo-id="' + id + '"]').attr("data-todo-revision", response.revisions[id]);
      });
    }
  }).fail(function () { window.location.reload(); });
}

// Delete single todo item
function delete_todo_item(list, id) {
  var revision = $(list).closest("li").attr("data-todo-revision");
  var request = revision === undefined
    ? requestGetJSON("todo/delete_todo_item/" + id)
    : $.ajax({url: admin_url + "todo/delete_todo_item/" + id, method: "POST", dataType: "json", data: {revision: revision}});
  request.done(function (response) {
    if (response.success === true || response.success == "true") {
      $(list).parents("li").remove();
      update_todo_items();
    }
  });
}

// Edit todo item
function edit_todo_item(id) {
  requestGetJSON("todo/get_by_id/" + id).done(function (response) {
    var todo_modal = $("#__todo");
    todo_modal.find('input[name="todoid"]').val(response.todoid);
    todo_modal.find('input[name="revision"]').val(response.revision || '');
    todo_modal.find('textarea[name="description"]').val(response.description);
    todo_modal.modal("show");
  });
}


jQuery(function () {
  // Clear todo modal values when modal is hidden
  $("body").on("hidden.bs.modal", "#__todo", function () {
    var $toDo = $("#__todo");
    $toDo.find('input[name="todoid"]').val("");
    $toDo.find('input[name="revision"]').val("");
    $toDo.find('textarea[name="description"]').val("");
    $toDo.find(".add-title").addClass("hide");
    $toDo.find(".edit-title").addClass("hide");
  });

  // Focus staff todo description
  $("body").on("shown.bs.modal", "#__todo", function () {
    var $toDo = $("#__todo");
    $toDo.find('textarea[name="description"]').focus();
    if ($toDo.find('input[name="todoid"]').val() !== "") {
      $toDo.find(".add-title").addClass("hide");
      $toDo.find(".edit-title").removeClass("hide");
    } else {
      $toDo.find(".add-title").removeClass("hide");
      $toDo.find(".edit-title").addClass("hide");
    }
  });

  // Todo status change checkbox click
  $("body").on("change", '.todo input[type="checkbox"]', function () {
    var finished = $(this).prop("checked") === true ? 1 : 0;
    var id = $(this).val();
    var revision = $(this).closest('li').attr('data-todo-revision');
    if ($('body').hasClass('native-company-admin')) {
      if (revision === undefined) throw new Error('native_todo_revision_missing');
      $(this).prop('disabled', true);
      $.post(admin_url + "todo/change_todo_status/" + id + "/" + finished, {revision: revision})
        .done(function () { window.location.reload(); })
        .fail(function () { window.location.reload(); });
    } else {
      window.location.href = admin_url + "todo/change_todo_status/" + id + "/" + finished;
    }
  });

  var todos_sortable = $(".todos-sortable");
  if (todos_sortable.length > 0) {
    // Makes todos sortable
    todos_sortable = todos_sortable.sortable({
      connectWith: ".todo",
      items: "li",
      handle: ".dragger",
      appendTo: "body",
      update: function (event, ui) {
        if (this === ui.item.parent()[0]) {
          update_todo_items();
        }
      },
    });
  }

});
