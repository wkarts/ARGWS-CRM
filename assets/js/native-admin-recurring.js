/* Owned schedule table and original split view. Never route a schedule ID into an invoice reader. */
(function (root, $) {
  'use strict';
  root.init_recurring_schedule = function (id) {
    return root.load_small_table_item(id, '#invoice', 'schedule_id', 'invoices/recurring_preview', '.table-recurring-invoices');
  };
  $(function () {
    var table = $('table.table-recurring-invoices');
    if (!table.length) return;
    var params = {};
    $('._hidden_inputs._filters input').each(function () {
      params[$(this).attr('name')] = '[name="' + $(this).attr('name') + '"]';
    });
    root.table_recurring_invoices = table;
    initDataTable(table, admin_url + 'invoices/table?recurring=1', 'undefined', 'undefined', params, [[7, 'asc'], [0, 'asc']]);
  });
}(window, window.jQuery));
