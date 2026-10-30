/* Native admin navigation extracted from main.js: one implementation for all issuers. */
(function(global,$){
  'use strict';
  var mounted=false;
  function is_mobile(){return global.NativeDevice.isMobile();}
  function environment(options){return {isMobile:options && options.isMobile || is_mobile, rtl:options && options.isRTL || (document.documentElement.dir==='rtl'?'true':'false')};}
  function mainWrapperHeightFix(options){
    var env=environment(options),is_mobile=env.isMobile,isRTL=env.rtl,side_bar=$('#side-menu'),setup_menu=$('#setup-menu-wrapper'),content_wrapper=$('#wrapper');
  // Get and set current height
  var headerH = $("#header").outerHeight() || 57;
  var navigationH = side_bar.height();
  var contentH = $("#wrapper").find(".content").height();
  setup_menu.css(
    "min-height",
    $(document).outerHeight(true) - headerH * 2 + "px"
  );
  $("#menu").css("min-height", $(document).outerHeight(true) + "px");

  content_wrapper.css(
    "min-height",
    $(document).outerHeight(true) - headerH + "px"
  );
  content_wrapper.css("height", '100%')
  // Set new height when content height is less then navigation
  if (contentH < navigationH) {
    content_wrapper.css("min-height", navigationH + "px");
  }

  // Set new height when content height is less then navigation and navigation is less then window
  if (contentH < navigationH && navigationH < $(window).height()) {
    content_wrapper.css("min-height", $(window).height() - headerH + "px");
  }
  // Set new height when content is higher then navigation but less then window
  if (contentH > navigationH && contentH < $(window).height()) {
    content_wrapper.css("min-height", $(window).height() - headerH + "px");
  }
  // Fix for RTL main admin menu height
  if (is_mobile() && isRTL == "true") {
    side_bar.css("min-height", $(document).outerHeight(true) - headerH + "px");
  }
}
  function set_body_small(options){
    var is_mobile=environment(options).isMobile;
  var isIpad = navigator.userAgent.match(/iPad/i) != null;

  if ($(window).width() < 769 || isIpad || is_mobile()) {
    $("body").addClass("page-small");
  } else {
    $("body").removeClass("page-small show-sidebar");
  }
}
  function init(options){
    if(mounted)return;
    mounted=true; options=options||{};
    var env=environment(options),is_mobile=env.isMobile,isRTL=env.rtl,side_bar=$('#side-menu'),setup_menu=$('#setup-menu-wrapper'),content_wrapper=$('#wrapper');
    function mainWrapperHeightFix(){global.NativeAdminNavigation.height(options);}
    function set_body_small(){global.NativeAdminNavigation.bodySmall(options);}
$(window).on("load resize", function (e) {
  // Reconcile both directions: resizing back to desktop must clear page-small.
  set_body_small();
  // Wait until metsiMenu, collapse and other effect finish and set wrapper height
  setTimeout(
    function () {
      mainWrapperHeightFix();
    },
    e.type == "load" ? 150 : 0
  );
});

$(document).on("mousemove", function (e) {
  if (!is_mobile() && $("body").hasClass("hide-sidebar")) {
    if (
      (isRTL == "true" && $(window).width() - e.pageX <= 10) ||
      (isRTL == "false" && e.pageX <= 10)
    ) {
      $(".hide-menu").click();
    }
  }
});


    $(function(){
      set_body_small();
  // Check for active class in sidebar links
  var $linkSidebarActive = side_bar.find('li > a[href="' + location + '"]');
  if ($linkSidebarActive.length) {
    $linkSidebarActive.parents("li").not(".quick-links").addClass("active");
    // Set aria expanded to true
    $linkSidebarActive.prop("aria-expanded", true);
    $linkSidebarActive
      .parents("ul.nav-second-level")
      .prop("aria-expanded", true);
    $linkSidebarActive
      .parents("li")
      .find("a:first-child")
      .prop("aria-expanded", true);
  }

  // Check for setu menu active class
  if (setup_menu.hasClass("display-block")) {
    var $linkSetupSidebarActive = setup_menu.find(
      'li > a[href="' + location + '"]'
    );
    if ($linkSetupSidebarActive.length) {
      $linkSetupSidebarActive.parents("li").addClass("active");
      $linkSetupSidebarActive.prev("active");
      $linkSetupSidebarActive
        .parents("ul.nav-second-level")
        .prop("aria-expanded", true);
      $linkSetupSidebarActive
        .parents("li")
        .find("a:first-child")
        .prop("aria-expanded", true);
    }
  }

  // Init now metisMenu for the main admin sidebar
  side_bar.metisMenu();
  // Init setup menu
  setup_menu.metisMenu();

  // Handle minimalize sidebar menu
  $(".hide-menu").click(function (e) {
    e.preventDefault();
    if (!$("body").hasClass("show-sidebar") && ($("body").hasClass("hide-sidebar") || $("body").hasClass("page-small"))) {
      $("body").removeClass("hide-sidebar").addClass("show-sidebar");
    } else {
      $("body").removeClass("show-sidebar").addClass("hide-sidebar");
    }
    // $("body").toggleClass(
    //   $(window).width() < 769 ? "show-sidebar" : "hide-sidebar"
    // );
    if (setup_menu.hasClass("display-block")) {
      $(".close-customizer").click();
    }
  });

  // Hide sidebar on content click on mobile
  if (is_mobile()) {
    content_wrapper.on("click", function () {
      if ($("body").hasClass("show-sidebar")) {
        $(".hide-menu").click();
      }
      if (setup_menu.hasClass("display-block")) {
        $(".close-customizer").click();
      }
    });
  }

  // Customizer close and remove open from session
  $(".close-customizer").on("click", function (e) {
    e.preventDefault();

    setup_menu.addClass(isRTL == "true" ? "fadeOutRight" : "fadeOutLeft");
    // Clear the session for setup menu so in reload wont be closed
    if (options.persistSetup) options.persistSetup("closed");
  });

  // Open customizer and add that is open to session
  $(".open-customizer").on("click", function (e) {
    e.preventDefault();

    if (setup_menu.hasClass(isRTL == "true" ? "fadeOutRight" : "fadeOutLeft")) {
      setup_menu.removeClass(isRTL == "true" ? "fadeOutRight" : "fadeOutLeft");
    }

    setup_menu.addClass(
      "display-block " + (isRTL == "true" ? "fadeInRight" : "fadeInLeft")
    );
    // Set session that the setup menu is open in case of reload
    if (!is_mobile()) {
      if (options.persistSetup) options.persistSetup("open");
    }
    mainWrapperHeightFix();
  });


      // Accessibility is shared by primary and contextual pages, not a second scoped implementation.
      var menuToggle=$("#header > .hide-menu");
      function syncMenuAria(){menuToggle.attr("aria-controls","menu").attr("aria-label","Alternar menu").attr("aria-expanded",String($("body").hasClass("show-sidebar") || (!$("body").hasClass("page-small") && !$("body").hasClass("hide-sidebar"))));}
      menuToggle.on("click.nativeMenuAria",syncMenuAria);
      $(window).on("resize.nativeMenuAria",syncMenuAria);
      $(document).on("keydown.nativeMenuEscape",function(event){if(event.key==="Escape" && $("body").hasClass("show-sidebar")){menuToggle.click();menuToggle.trigger("focus");}});
      var wrapper=document.getElementById("wrapper");
      if(wrapper)wrapper.addEventListener("click",function(event){if($("body").hasClass("page-small") && $("body").hasClass("show-sidebar")){event.preventDefault();event.stopImmediatePropagation();menuToggle.click();menuToggle.trigger("focus");}},true);
      syncMenuAria();
      mainWrapperHeightFix();
    });
  }
  global.NativeAdminNavigation={init:init,height:mainWrapperHeightFix,bodySmall:set_body_small,isMobile:is_mobile};
})(window,window.jQuery);
