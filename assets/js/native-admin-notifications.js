/* Actual native notification interactions, shared by principal and named contexts. */
(function(root,$){'use strict';
 var mounted=false,transport,options,notifications_wrapper,doc_initial_title;
 function init(config){
   if(mounted)return;
   if(!config||!config.transport||typeof config.transport.mark!=='function'||typeof config.transport.read!=='function')throw Error('native_notifications_transport_required');
   mounted=true;options=config;transport=config.transport;notifications_wrapper=$('#header').find('li.notifications-wrapper');doc_initial_title=config.initialTitle||document.title;
  // Notification profile link click
  $("body").on("click", ".notification_link", function () {
    var link = $(this).data("link");
    var not_href;
    not_href = link.split("#");
    if (!not_href[1]) {
      window.location.href = link;
    }
  });

  /* Custom notifications links, NOTE: touchstart listener is for iOS davices */
  $("body").on(
    "click" + ("ontouchstart" in window ? " touchstart" : ""),
    ".notifications a.notification-top, .notification_link",
    function (e) {
      e.preventDefault();
      var $notLink = $(this);
      var not_href_id;

      var not_href = $notLink.hasClass("notification_link")
        ? $notLink.data("link")
        : e.currentTarget.href;

      var not_href_array = not_href.split("#");
      var notRedirect = true;
      if (not_href_array[1] && not_href_array[1].indexOf("=") > -1) {
        notRedirect = false;
        not_href_id = not_href_array[1].split("=")[1];
        if (not_href_array[1].indexOf("postid") > -1) {
          options.openPost(not_href_id);
          if ($(window).width() > 769) {
            $(".open_newsfeed.desktop").click();
          } else {
            $(".open_newsfeed.mobile").click();
          }
        } else if (not_href_array[1].indexOf("taskid") > -1) {
          var comment_id = undefined;
          if (not_href.indexOf("#comment_") > -1) {
            var task_comment_id = not_href.split("#comment_");
            comment_id = task_comment_id[task_comment_id.length - 1];
          }
          options.openTask(not_href_id, comment_id);
        } else if (not_href_array[1].indexOf("leadid") > -1) {
          options.openLead(not_href_id);
        } else if (not_href_array[1].indexOf("eventid") > -1) {
          options.openEvent(not_href_id);
        }
      }
      if (!$notLink.hasClass("desktopClick")) {
        $notLink.parent("li").find(".not-mark-as-read-inline").click();
      }
      if (notRedirect) {
        setTimeout(function () {
          window.location.href = not_href_array;
        }, 50);
      }
    }
  );

  // Set notifications to read when notifictions dropdown is opened
  $(".notifications-wrapper").on("show.bs.dropdown", function () {
    var total = notifications_wrapper
      .find(".notifications")
      .attr("data-total-unread");
    if (total > 0) {
      transport.mark(null,"seen").done(function (
        response
      ) {
        response = typeof response === "string" ? JSON.parse(response) : response;
        if (response.success === true || response.success == "true") {
          document.title = doc_initial_title;
          $(".icon-notifications").addClass("hide");
        }
      });
    }
  });


 }
// Set single notification as read INLINE
function set_notification_read_inline(id) {
  transport.mark(id,"inline").done(function () {
    var notification = $("body").find(
      '.notification-wrapper[data-notification-id="' + id + '"]'
    );
    notification
      .find(".notification-handler")
      .removeClass("unread-notification");
    notification.find(".not-mark-as-read-inline").tooltip("destroy").remove();
  });
}

// Marks all notifications as read INLINE
function mark_all_notifications_as_read_inline() {
  transport.mark(null,"all").done(function () {
    var notification = $("body").find(".notification-wrapper");
    notification
      .find(".notification-handler")
      .removeClass("unread-notification");
    notification.find(".not-mark-as-read-inline").tooltip("destroy").remove();
  });
}

// Fetches notifications
function fetch_notifications(callback) {
  transport.read().done(function (response) {
    var nw = notifications_wrapper;
    nw.html(response.html);
    var total = nw.find("ul.notifications").attr("data-total-unread");
    document.title =
      total > 0 ? "(" + total + ") " + doc_initial_title : doc_initial_title;
    var nIds = response.notificationsIds;
    if (options.browser == "firefox" && nIds.length > 1) {
      var lastNotification = nIds[0];
      nIds = [];
      nIds.push(lastNotification);
    }
    setTimeout(function () {
      if (nIds.length > 0) {
        $.each(nIds, function (i, notId) {
          var nSelector = 'li[data-notification-id="' + notId + '"]';
          var $not = nw.find(nSelector);
          $.notify("", {
            title: options.label,
            body: $not.find(".notification-title").text(),
            requireInteraction: true,
            icon: $not.find(".notification-image").attr("src"),
            tag: notId,
            closeTime:
              options.dismissAfter != "0"
                ? options.dismissAfter * 1000
                : null,
          })
            .close(function () {
              transport.mark(notId,"all").done(
                function (response) {
                  var $totalIndicator = nw.find(".icon-total-indicator");
                  nw.find(
                    'li[data-notification-id="' + notId + '"] .notification-box'
                  ).removeClass("unread");
                  var currentTotalNotifications = $totalIndicator.text();
                  currentTotalNotifications = currentTotalNotifications.trim();
                  currentTotalNotifications = currentTotalNotifications - 1;
                  if (currentTotalNotifications > 0) {
                    document.title =
                      "(" +
                      currentTotalNotifications +
                      ") " +
                      doc_initial_title;
                    $totalIndicator.html(currentTotalNotifications);
                  } else {
                    document.title = doc_initial_title;
                    $totalIndicator.addClass("hide");
                  }
                }
              );
            })
            .click(function (e) {
              parent.focus();
              window.focus();
              setTimeout(function () {
                nw.find(nSelector + " .notification-link")
                  .addClass("desktopClick")
                  .click();
                e.target.close();
              }, 70);
            });
        });
      }
    }, 10);
  });
}


 root.NativeAdminNotifications={init:init,markOne:set_notification_read_inline,markAll:mark_all_notifications_as_read_inline,fetch:fetch_notifications};
})(window,jQuery);
