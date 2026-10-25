/* Shared native UI definitions. No bootstrapping or automatic network operations. */

function requestGet(uri, params) {
  params = typeof params == "undefined" ? {} : params;
  var options = {
    type: "GET",
    url: uri.indexOf(admin_url) > -1 ? uri : admin_url + uri,
  };
  return $.ajax($.extend({}, options, params));
}

function requestGetJSON(uri, params) {
  params = typeof params == "undefined" ? {} : params;
  params.dataType = "json";
  return requestGet(uri, params);
}

function init_tags_inputs() {
  appTagsInput();
}

function init_editor(selector, settings) {
  selector = typeof selector == "undefined" ? ".tinymce" : selector;
  var _editor_selector_check = $(selector);

  if (_editor_selector_check.length === 0) {
    return;
  }

  $.each(_editor_selector_check, function () {
    if ($(this).hasClass("tinymce-manual")) {
      $(this).removeClass("tinymce");
    }
  });

  // Original settings
  var _settings = {
    branding: false,
    promotion: false,
    selector: selector,
    browser_spellcheck: true,
    cache_suffix: '?v='+app.version,
    height: 250,
    min_height: 250,
    statusbar: false,
    theme: "silver",
    paste_block_drop: true,
    language: app.tinymce_lang || 'en',
    relative_urls: false,
    entity_encoding: "raw",
    autoresize_bottom_margin: 25,
    valid_elements: "+*[*]",
    valid_children: "+body[style], +style[type]",
    remove_script_host: false,
    removed_menuitems: "newdocument restoredraft",
    forced_root_block: "p",
    autosave_restore_when_empty: false,
    font_size_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt",
    table_default_styles: {
      width: "100%",
    },
    plugins: [
      "advlist", "autoresize", "autosave", "lists", "link", "image", "codesample",
      "visualblocks", "code", "fullscreen",
      "media", "save", "table",
    ],
    toolbar: "fontfamily fontsize | forecolor backcolor | bold italic | alignleft aligncenter alignright alignjustify | image link | bullist numlist | restoredraft",
    contextmenu: "link image | paste copy",
    file_picker_callback : elFinderBrowser,
    setup: function (ed) {
      // Default fontsize is 12
      ed.on("init", function () {
        this.getDoc().body.style.fontSize = "12pt";
      });
    },
  };

  // Add the rtl to the settings if is true
  if(isRTL == "true") {
    _settings.directionality = "rtl"
    _settings.plugins.push('directionality')
  }

  // Possible settings passed to be overwrited or added
  if (typeof settings != "undefined") {
    for (var key in settings) {
      if (key != "append_plugins") {
        _settings[key] = settings[key];
      } else {
        _settings.plugins.push(settings[key]);
      }
    }
  }

  // Init the editor
  var editor = tinymce.init(_settings);

  $(document).trigger("app.editor.initialized");

  return editor;
}

function elFinderBrowser(callback, value, meta) {
  tinymce.activeEditor.windowManager.elfinderCallback = callback
  
  tinymce.activeEditor.windowManager.openUrl({
      url: admin_url + "misc/tinymce_file_browser",
      title: app.lang.media_files,
      width: 900,
      height: 450,
  });
  
  return false;
}

function init_ajax_search(type, selector, server_data, url) {
  var ajaxSelector = $("body").find(selector);

  if (ajaxSelector.length) {
    var options = {
      ajax: {
        url:
          typeof url == "undefined"
            ? admin_url + "misc/get_relation_data"
            : url,
        data: function () {
          var data = {};
          data.type = type;
          data.rel_id = "";
          data.q = "{{{q}}}";
          if (typeof server_data != "undefined") {
            jQuery.extend(data, server_data);
          }
          return data;
        },
      },
      locale: {
        emptyTitle: app.lang.search_ajax_empty,
        statusInitialized: app.lang.search_ajax_initialized,
        statusSearching: app.lang.search_ajax_searching,
        statusNoResults: app.lang.not_results_found,
        searchPlaceholder: app.lang.search_ajax_placeholder,
        currentlySelected: app.lang.currently_selected,
      },
      requestDelay: 500,
      cache: false,
      preprocessData: function (processData) {
        var bs_data = [];
        var len = processData.length;
        for (var i = 0; i < len; i++) {
          var tmp_data = {
            value: processData[i].id,
            text: processData[i].name,
          };
          if (processData[i].subtext) {
            tmp_data.data = {
              subtext: processData[i].subtext,
            };
          }
          if (Object.prototype.hasOwnProperty.call(processData[i], "native_revision")) {
            if (!Number.isInteger(processData[i].native_revision) || processData[i].native_revision < 1 || processData[i].native_revision > 2147483646) throw new Error("native_task_revision_invalid");
            tmp_data.data = tmp_data.data || {};
            tmp_data.data["native-task-revision"] = String(processData[i].native_revision);
          }
          bs_data.push(tmp_data);
        }
        return bs_data;
      },
      preserveSelectedPosition: "after",
      preserveSelected: true,
    };
    if (ajaxSelector.data("empty-title")) {
      options.locale.emptyTitle = ajaxSelector.data("empty-title");
    }
    ajaxSelector.selectpicker().ajaxSelectPicker(options);
  }
}

function system_popup(data) {
  data.content = typeof data.content == "undefined" ? "" : data.content;

  var $popupHTML = $("<div/>", {
    id: "system-popup",
    class: "system-popup",
  }).appendTo("body");

  var overlayHTML = "";
  overlayHTML += '<div class="popup-wrapper fadeIn animated">';
  overlayHTML += '<h2 class="popup-message">';
  overlayHTML += data.message;
  overlayHTML += "</h2>";
  overlayHTML += '<div class="popup-content">';
  overlayHTML += data.content;
  overlayHTML += '<button type="button" class="system-popup-close"> </button>';
  overlayHTML += "</div>";
  overlayHTML += "</div>";

  $popupHTML.html(overlayHTML).removeClass("hide");
  $("body").addClass("system-popup");
  $popupHTML.find(".system-popup-close").on("click", function () {
    var that = this;
    // This native contextual endpoint is a mutation: never issue it as GET.
    var clearRequest;
    if (document.body.classList.contains("native-company-admin")) {
      if (typeof NativeCompanyTransport === "undefined") throw new Error("native_transport_unavailable");
      clearRequest = NativeCompanyTransport.request("misc/clear_system_popup", "POST", {});
    } else {
      clearRequest = requestGet("misc/clear_system_popup");
    }
    clearRequest.done(function (response) {
      setTimeout(function () {
        $("body").removeClass("system-popup");
        $popupHTML.fadeOut(400, function () {
          $popupHTML.remove();
        });
        $(that).off("click");
      }, 50);
    });
  });

  return $popupHTML;
}

function showHideTagsPlaceholder($tagit) {
  var $input = $tagit.data("ui-tagit").tagInput,
    placeholderText = $tagit.data("ui-tagit").options.placeholderText;
  $tagit.tagit("assignedTags").length > 0
    ? $input.removeAttr("placeholder")
    : $input.attr("placeholder", placeholderText);
}

/* Same invoice menu actions; only explicit user clicks mutate, and GET is never retried. */
function native_invoice_communication(element) {
  if (!window.NativeCompanyTransport) throw Error('native_invoice_transport_required');
  var $link = $(element), revision = $link.attr('data-native-invoice-communication-revision');
  if (!/^[a-f0-9]{64}$/.test(revision || '')) throw Error('native_invoice_revision_required');
  if ($link.attr('aria-busy') === 'true') return false;
  var key = $link.attr('data-native-request-key');
  if (!key) {
    var bytes = new Uint8Array(32);
    window.crypto.getRandomValues(bytes);
    key = Array.from(bytes, function (value) { return value.toString(16).padStart(2, '0'); }).join('');
    $link.attr('data-native-request-key', key);
  }
  $link.attr('aria-busy', 'true');
  window.NativeCompanyTransport.request($link.attr('href'), 'POST', {revision: revision, request_key: key}, {dataType: 'json'})
    .done(function (response) {
      if (!response || response.success !== true || typeof response.redirect !== 'string') throw Error('native_invoice_response_invalid');
      window.NativeCompanyTransport.navigate(response.redirect);
    })
    .always(function () { $link.attr('aria-busy', 'false'); });
  return false;
}
