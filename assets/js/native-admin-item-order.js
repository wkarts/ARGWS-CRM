/* Original preview ordering handler; scoped transport is explicit and receipt-bound. */
function save_ei_items_order() {
  if ($("body").hasClass("native-company-admin")) {
    var nativeTable = $(".table.items-preview");
    var nativeId = nativeTable.attr("data-native-invoice-id");
    var revision = nativeTable.attr("data-native-invoice-revision");
    var pairs = [], seen = {};
    if (nativeTable.attr("data-type") !== "invoice" || !/^[1-9][0-9]*$/.test(nativeId || "") || !/^[a-f0-9]{64}$/.test(revision || "") || !window.NativeCompanyTransport) throw new Error("native_invoice_order_identity_required");
    nativeTable.find("tbody tr[data-item-id]").each(function () {
      var id = String($(this).attr("data-item-id") || "");
      if (!/^[1-9][0-9]*$/.test(id) || seen[id]) throw new Error("native_invoice_order_invalid");
      seen[id] = true; pairs.push([id, pairs.length + 1]);
    });
    if (!pairs.length) throw new Error("native_invoice_order_invalid");
    return NativeCompanyTransport.request("misc/update_ei_items_order/invoice", "POST", {data:pairs, invoice_id:nativeId, revision:revision}, {dataType:"json"}).done(function (response) {
      if (!response || typeof response.revision !== "string" || !/^[a-f0-9]{64}$/.test(response.revision)) { window.location.reload(); throw new Error("native_invoice_order_receipt_invalid"); }
      nativeTable.attr("data-native-invoice-revision", response.revision);
      nativeTable.find("tbody tr[data-item-id]").each(function (index) { $(this).find("td.item_no").text(index + 1); });
    }).fail(function () {
      // Re-read the current authorized route; never repeat the ordering POST on failure.
      window.location.reload();
    });
  }

  var table = $(".table.items-preview");
  var rows = table.find("tbody tr");
  var i = 1,
    type = table.attr("data-type"),
    order = [],
    _order_id,
    item_id;

  if (!type) {
    return false;
  }

  $.each(rows, function () {
    order.push([$(this).data("item-id"), i]);
    // update item number when reordering
    $(this).find("td.item_no").html(i);
    i++;
  });

  setTimeout(function () {
    $.post(admin_url + "misc/update_ei_items_order/" + type, {
      data: order,
    });
  }, 200);
}

