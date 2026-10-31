/* Shared native device detection; no application bootstrap or transport. */
(function(global){'use strict';global.NativeDevice={isMobile:function(){
  if (typeof app != "undefined" && typeof app.is_mobile != "undefined") {
    return app.is_mobile;
  }

  try {
    document.createEvent("TouchEvent");
    return true;
  } catch (e) {
    return false;
  }
}};})(window);
