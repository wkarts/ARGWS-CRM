// Single original native Tickets DataTable initializer; used by principal and scoped pages.
function init_table_tickets(manual) {
  // Single ticket is for other tickets from user
  if (
    typeof manual == "undefined" &&
    ($("body").hasClass("dashboard") || $("body").hasClass("single-ticket"))
  ) {
    return false;
  }

  if ($("body").find(".tickets-table").length === 0) {
    return;
  }

  var TicketServerParams = {},
    Tickets_Filters = $("._hidden_inputs._filters.tickets_filters input");
  var tickets_date_created_index = $(
    "table.tickets-table thead .ticket_created_column"
  ).index();
  $.each(Tickets_Filters, function () {
    TicketServerParams[$(this).attr("name")] =
      '[name="' + $(this).attr("name") + '"]';
  });

  TicketServerParams["project_id"] = '[name="project_id"]';

  var ticketsTableNotSortable = [0]; // bulk actions
  var _tickets_table_url = admin_url + "tickets";

  if ($("body").hasClass("tickets-page")) {
    _tickets_table_url += "?bulk_actions=true";
  }

  _table_api = initDataTable(
    ".tickets-table",
    _tickets_table_url,
    ticketsTableNotSortable,
    ticketsTableNotSortable,
    TicketServerParams,
    [tickets_date_created_index, "desc"]
  );

  if (_table_api && $("body").hasClass("dashboard")) {
    var notVisibleDashboardDefault = [4, tickets_date_created_index, 5, 6];
    for (var i in notVisibleDashboardDefault) {
      _table_api.column(notVisibleDashboardDefault[i]).visible(false, false);
    }
    _table_api.columns.adjust();
  }
}

