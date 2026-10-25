/* Shared native switch interaction, used by both presentation contexts. */
$(function () {
  // Bootstrap switch active or inactive global function
  $("body").on("change", ".onoffswitch input", function (event, state) {
    var switch_url = $(this).data("switch-url");
    if (!switch_url) {
      return;
    }
    switch_field(this);
  });

});
// Switch field make request
function switch_field(field) {
  var status, url, id;
  status = 0;
  if ($(field).prop("checked") === true) {
    status = 1;
  }
  url = $(field).data("switch-url");
  id = $(field).data("id");
  if (window.NativeCompanyTransport) {
    var revision = $(field).attr("data-native-revision");
    if (!/^[a-f0-9]{64}$/.test(revision || "")) throw new Error("native_revision_required");
    return NativeCompanyTransport.request(url + "/" + id + "/" + status, "POST", {revision: revision}, {dataType: "json"})
      .done(function (response) {
        if (!response || response.success !== true || !/^[a-f0-9]{64}$/.test(response.revision || "")) throw new Error("native_mutation_unconfirmed");
        $(field).attr("data-native-revision", response.revision);
      }).fail(function () { $(field).prop("checked", status !== 1); });
  }
  return requestGet(url + "/" + id + "/" + status);
}

