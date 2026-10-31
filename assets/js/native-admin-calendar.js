/* Single source extracted from native main.js: same FullCalendar and modal handlers. */
window.NativeAdminCalendar={init:function(){
  var calendar_selector=$("#calendar"),native=$("body").hasClass("native-company-admin");
  if(!calendar_selector.length)return;
  if(native&&(!window.NativeCompanyTransport||!window.nativeCalendarConfig))throw new Error("native_calendar_configuration_required");
  var calendarOptions=native?window.nativeCalendarConfig.options:app.options;
  if (calendar_selector.length > 0) {
    if(calendar_selector.attr("data-native-calendar-initialized")==="1")return;
    calendar_selector.attr("data-native-calendar-initialized","1");
    validate_calendar_form();

    var calendar_settings = {
      customButtons: {},
      locale: app.locale,
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,timeGridDay",
      },
      editable: false,
      dayMaxEventRows: parseInt(calendarOptions.calendar_events_limit) + 1,

      views: {
        day: {
          dayMaxEventRows: false,
        },
      },

      direction: isRTL == "true" ? "rtl" : "ltr",
      eventStartEditable: false,
      firstDay: parseInt(calendarOptions.calendar_first_day),
      initialView: calendarOptions.default_view_calendar,
      timeZone: calendarOptions.timezone,

      loading: function (isLoading, view) {
        !isLoading
          ? $(".dt-loader").addClass("hide")
          : $(".dt-loader").removeClass("hide");
      },

      eventSources: [
        function (info, successCallback, failureCallback) {
          var params = {};
          $("#calendar_filters")
            .find("input:checkbox:checked")
            .map(function () {
              params[$(this).attr("name")] = true;
            })
            .get();

          if (!jQuery.isEmptyObject(params)) {
            params["calendar_filters"] = true;
          }

          // This endpoint reads calendar days, with an exclusive end. The
          // shipped FullCalendar can emit offset-free timestamps for named
          // zones (UTC-coercion); never reinterpret those as server instants.
          var query=$.extend({},params,{start:info.startStr.slice(0,10),end:info.endStr.slice(0,10)});
          var pending=native?NativeCompanyTransport.request("utilities/get_calendar_data","GET",query,{dataType:"json"}):$.getJSON(admin_url+"utilities/get_calendar_data",query);
          return pending.then(function (data) {
            successCallback(
              data.map(function (e) {
                var mapped = $.extend({}, e, {
                  start: e.start || e.date,
                  end: e.end || e.date,
                });
                // FullCalendar refines an own `url: undefined` to the literal
                // string "undefined", making personal events false links.
                if (e.native_path) mapped.url = admin_url + e.native_path;
                else if (typeof e.url === "string" && e.url) mapped.url = e.url;
                else delete mapped.url;
                return mapped;
              })
            );
          }, failureCallback);
        },
      ],

      moreLinkClick: function (info) {
        calendar.gotoDate(info.date);
        calendar.changeView("dayGridDay");

        setTimeout(function () {
          $(".fc-popover-close").click();
        }, 250);
      },

      eventDidMount: function (data) {
        var $el = $(data.el);
        $el.attr("title", data.event.extendedProps._tooltip);
        if(!native)$el.attr("onclick", data.event.extendedProps.onclick);
        $el.attr("data-toggle", "tooltip");
        if (!data.event.url && !data.event.extendedProps.url) {
          $el.on("click", function () {
            view_event(data.event.extendedProps.eventid);
          });
        }
      },

      dateClick: function (info) {
        if (info.dateStr.length <= 10) {
          // has not time
          info.dateStr += "T00:00:00";
        }

        var fmt = new DateFormatter();

        var d1 = fmt.formatDate(
          new Date(info.dateStr),
          (vformat =
            calendarOptions.time_format == 24
              ? calendarOptions.date_format + " H:i"
              : calendarOptions.date_format + " g:i A")
        );

        $("input[name='start'].datetimepicker").val(d1);
        $("#newEventModal").modal("show");

        return false;
      },
    };

    if ($("body").hasClass("dashboard")) {
      calendar_settings.customButtons.viewFullCalendar = {
        text: app.lang.calendar_expand,
        click: function () {
          window.location.href = admin_url + "utilities/calendar";
        },
      };

      calendar_settings.headerToolbar.left += ",viewFullCalendar";
    }

    calendar_settings.customButtons.calendarFilter = {
      text: app.lang.filter_by.toLowerCase(),
      click: function () {
        slideToggle("#calendar_filters");
      },
    };

    calendar_settings.headerToolbar.right += ",calendarFilter";

    if (!native && app.user_is_staff_member == 1) {
      if (calendarOptions.google_api !== "") {
        calendar_settings.googleCalendarApiKey = calendarOptions.google_api;
      }

      if (app.calendarIDs !== "") {
        app.calendarIDs = JSON.parse(app.calendarIDs);
        if (app.calendarIDs.length != 0) {
          if (calendarOptions.google_api !== "") {
            for (var i = 0; i < app.calendarIDs.length; i++) {
              var _gcal = {};
              _gcal.googleCalendarId = app.calendarIDs[i];
              calendar_settings.eventSources.push(_gcal);
            }
          } else {
            console.error(
              "You have setup Google Calendar IDs but you dont have specified Google API key. To setup Google API key navigate to Setup->Settings->Google"
            );
          }
        }
      }
    }

    if(native){
      var nativeIds=window.nativeCalendarConfig.calendar_ids;
      if(!Array.isArray(nativeIds)||nativeIds.some(function(id){return typeof id!=="string"||!id;}))throw new Error("native_calendar_configuration_invalid");
      if(calendarOptions.google_api!==""){
        calendar_settings.googleCalendarApiKey=calendarOptions.google_api;
        nativeIds.forEach(function(id){calendar_settings.eventSources.push({googleCalendarId:id});});
      }
    }

    var calendar = new FullCalendar.Calendar(
      calendar_selector[0],
      calendar_settings
    );
    calendar.render();

    var new_event = get_url_param("new_event");

    if (new_event) {
      $("input[name='start'].datetimepicker").val(get_url_param("date"));
      $("#newEventModal").modal("show");
    }
  }

}};
$(function(){if($("body").hasClass("native-company-admin"))NativeAdminCalendar.init();});

// View calendar custom single event
function view_event(id) {
  if (typeof id == "undefined") {
    return;
  }
  var native=$("body").hasClass("native-company-admin");
  if(native&&!window.NativeCompanyTransport)throw new Error("native_calendar_transport_required");
  var pending=native?NativeCompanyTransport.request("utilities/view_event/"+id,"GET",{}, {dataType:"html"}):$.get(admin_url+"utilities/view_event/"+id);
  return pending.done(function (response) {
    $("#event").html(response);
    $("#viewEvent").modal("show");
    init_datepicker();
    init_selectpicker();
    validate_calendar_form();
  });
}

// Delete calendar event form
function delete_event(id) {
  if (confirm_delete()) {
    var native=$("body").hasClass("native-company-admin");
    if(native&&!window.NativeCompanyTransport)throw new Error("native_calendar_transport_required");
    var revision={revision:$("#viewEvent input[name=revision]").val()};
    var pending=native?NativeCompanyTransport.request("utilities/delete_event/"+id,"POST",revision,{dataType:"json"}):$.post(admin_url+"utilities/delete_event/"+id,revision);
    return pending.done(function (response) {
      if (response.success === true || response.success == "true") {
        // The original filter form may have produced this document by POST.
        // Follow deletion with GET, without replaying that form or eventid.
        window.location.href = window.location.href.split("?")[0];
      }
    });
  }
}

// Validate calendar event form
function validate_calendar_form() {
  appValidateForm(
    $("body").find("._event form"),
    {
      title: "required",
      start: "required",
      reminder_before: "required",
    },
    calendar_form_handler
  );

  appValidateForm(
    $("body").find("#viewEvent form"),
    {
      title: "required",
      start: "required",
      reminder_before: "required",
    },
    calendar_form_handler
  );
}

// Handles calendar event saving
function calendar_form_handler(form) {
  var native=$("body").hasClass("native-company-admin");
  if(native&&!window.NativeCompanyTransport)throw new Error("native_calendar_transport_required");
  var pending=native?NativeCompanyTransport.request("utilities/calendar","POST",$(form).serialize(),{dataType:"json"}):$.post(form.action,$(form).serialize());
  pending.done(function (response) {
    if(typeof response==="string")response = JSON.parse(response);
    if (response.success === true || response.success == "true") {
      alert_float("success", response.message);
      setTimeout(function () {
        var location = window.location.href;
        location = location.split("?");
        window.location.href = location[0];
      }, 500);
    }
  });

  return false;
}


$(function(){
  // Change live the colors for colorpicker in kanban/pipeline
  $("body").on("click", ".cpicker", function () {
    var color = $(this).data("color");
    // Clicked on the same selected color
    if ($(this).hasClass("cpicker-big")) {
      return false;
    }

    $(this)
      .parents(".cpicker-wrapper")
      .find(".cpicker-big")
      .removeClass("cpicker-big")
      .addClass("cpicker-small");
    $(this)
      .removeClass("cpicker-small", "fast")
      .addClass("cpicker-big", "fast");
    if ($(this).hasClass("kanban-cpicker")) {
      $(this).parents(".panel-heading").css("background", color);
      $(this)
        .parents(".panel-heading")
        .css("border", "1px solid " + color);
    } else if ($(this).hasClass("calendar-cpicker")) {
      $(this).closest(".modal").find('input[name="color"]').val(color);
    }
  });

});
