/* Same native IMAP folder interaction, shared by principal and scoped forms. */
function retrieve_imap_folders(url, params) {
  var dfd = $.Deferred();
  $("#folders-loader").addClass("spinning").removeClass("hidden");

  $.post(url, params)
    .done(function (response) {
      response = typeof response === 'string' ? JSON.parse(response) : response;
      if (response.hasOwnProperty("alert_type")) {
        alert_float(response.alert_type, response.message);
      } else {
        var $folder = $("#folder");
        var currentFolder = $folder.selectpicker("val");
        $folder.empty();
        response.forEach(function (folderName) {
          $folder.append($("<option>").val(folderName).text(folderName).prop("selected", folderName == currentFolder));
        });
        $folder.selectpicker("refresh");

        if (!currentFolder && $folder.find("option:eq(0)").length) {
          $folder.selectpicker("val", $folder.find("option:eq(0)")[0].value);
        }
      }
      dfd.resolve(response);
    })
    .fail(function (error) {
      dfd.reject(error);
    })
    .always(function () {
      $("#folders-loader").removeClass("spinning").addClass("hidden");
    });

  return dfd.promise();
}
