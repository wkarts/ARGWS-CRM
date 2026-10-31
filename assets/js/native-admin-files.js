/* Single native file visibility handler, shared by original and issuer-scoped views. */
function toggle_file_visibility(attachment_id, rel_id, invoker) {
  if ($("body").hasClass("native-company-admin")) {
    var node = $(invoker);
    var source = node.attr("data-native-file-source");
    var revision = node.attr("data-native-file-revision");
    var visible = node.attr("data-native-file-visible");
    if (!window.NativeCompanyTransport || !/^[1-9][0-9]*$/.test(String(attachment_id)) || !/^[1-9][0-9]*$/.test(revision || "") || ["invoice", "customer"].indexOf(source) === -1 || ["0", "1"].indexOf(visible) === -1) throw new Error("native_file_identity_required");
    return NativeCompanyTransport.request("misc/toggle_file_visibility/" + attachment_id, "POST", {
      native_file_source: source, native_file_revision: revision, visible: visible === "1" ? "0" : "1"
    }, {dataType: "json"}).done(function (response) {
      if (!response || [0, 1].indexOf(response.visible) === -1 || response.revision !== Number(revision) + 1) throw new Error("native_file_receipt_invalid");
      node.attr("data-native-file-revision", String(response.revision)).attr("data-native-file-visible", String(response.visible));
      node.closest("[data-attachment-id]").attr("data-native-file-revision", String(response.revision));
      node.find("i").removeClass("fa fa-toggle-off fa-toggle-on").addClass(response.visible ? "fa fa-toggle-on" : "fa fa-toggle-off");
    });
  }
  requestGet("misc/toggle_file_visibility/" + attachment_id).done(function (
    response
  ) {
    if (response == 1) {
      $(invoker)
        .find("i")
        .removeClass("fa fa-toggle-off")
        .addClass("fa fa-toggle-on");
    } else {
      $(invoker)
        .find("i")
        .removeClass("fa fa-toggle-on")
        .addClass("fa fa-toggle-off");
    }
  });
}

// Delete invoice attachment
function delete_invoice_attachment(id) {
  if (confirm_delete()) {
    var request;
    if ($("body").hasClass("native-company-admin")) {
      if (!/^[1-9][0-9]*$/.test(String(id))) throw new Error("native_file_identity_required");
      var element = $("body").find('[data-attachment-id="' + id + '"]');
      var revision = element.attr("data-native-file-revision");
      if (element.length !== 1 || !/^[1-9][0-9]*$/.test(revision || "") || !window.NativeCompanyTransport) throw new Error("native_file_identity_required");
      if (element.attr("data-native-file-busy") === "1") return;
      element.attr("data-native-file-busy", "1");
      request = NativeCompanyTransport.request("invoices/delete_attachment/" + id, "POST", {revision: revision}, {dataType: "json"});
      request.always(function () { element.removeAttr("data-native-file-busy"); });
    } else {
      request = requestGet("invoices/delete_attachment/" + id);
    }
    return request
      .done(function (success) {
        if (success == 1) {
          $("body")
            .find('[data-attachment-id="' + id + '"]')
            .remove();
          init_invoice(
            $("body").find('input[name="_attachment_sale_id"]').val()
          );
        }
      })
      .fail(function (error) {
        alert_float("danger", error.responseText);
      });
  }
}
