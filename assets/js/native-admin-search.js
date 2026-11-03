/* Extracted native top-search interactions. One implementation for every admin context. */
(function(root,$){'use strict';
 var mounted=false,historyRows=[];
 function setHistory(history){
   historyRows=Array.isArray(history)?history:[];
   var list=$('#search-history').empty();
   historyRows.forEach(function(row,index){
     var text=typeof row==='string'?row:row.query_text;
     var li=$('<li>').attr('data-index',index),link=$('<a>').attr('href','#').addClass('history').text(text);
     link.append(' ').append($('<span>').addClass('remove-history pointer pull-right').css('z-index',1500).append($('<i>').addClass('fa fa-remove')));
     list.append(li.append(link));
   });
 }
 function init(options){
   if(mounted)return; if(!options||typeof options.search!=='function'||typeof options.remove!=='function')throw Error('native_search_transport_required');
   mounted=true;var pending,original_top_search_val='',content_wrapper=$('#wrapper');
  setHistory(options.history);

  $("#search-history").on("click", ".remove-history", function (e) {
    e.stopImmediatePropagation();
    e.preventDefault();
    var index = $(this).parents("li").index();
    options.remove(historyRows[index],index).done(function (history) {
      var $searchHistory = $("#search-history");
      historyRows.splice(index,1);setHistory(historyRows);
      if ($searchHistory.find("li").length == 0) {
        $searchHistory.removeClass("display-block");
      }
    });
  });

  $("#search_input").on("click focus", function () {
    if ($(this).val() == "") {
      var $searchHistory = $("#search-history");
      if ($searchHistory.find("li").length > 0) {
        $searchHistory.css("width", $(this).outerWidth() + "px");
        $searchHistory.addClass("display-block");
      } else {
        // $searchHistory.addClass('display-block');
      }
    }
  });

  $("#search-history").on("click", "a.history", function (e) {
    e.preventDefault();
    var recentSearch = $(this).text().trim();
    $("#search_input").val(recentSearch);
    $("#search_input").trigger("paste");
  });

  // Top search input fetch results
  $("#search_input").on(
    "keyup paste" + (options.browser == "safari" ? " input" : ""),
    function () {
      var $searchHistory = $("#search-history");
      $searchHistory.removeClass("display-block");

      var q = $(this).val().trim();
      var search_results = $("#search_results");
      var top_search_button = $("#top_search_button button");

      if (q === "") {
        content_wrapper.unhighlight();
        search_results.html("");
        original_top_search_val = "";
        top_search_button
          .html('<i class="fa fa-search"></i>')
          .removeClass("search_remove");
        $searchHistory.addClass("display-block");
        return;
      }

      if (
        q.length < 2 &&
        options.language.indexOf("chinese") === -1 &&
        options.language.indexOf("japanese") === -1
      ) {
        return;
      }

      top_search_button
        .html('<i class="fa fa-remove"></i>')
        .addClass("search_remove");

      clearTimeout(pending); pending=setTimeout(function () {
        if (q == original_top_search_val) {
          return;
        }
        options.search(q).done(function (data) {
          data = typeof data === "string" ? JSON.parse(data) : data;
          if($("#search_input").val().trim()!==q)return;
          content_wrapper.unhighlight();
          search_results.html(data.results);
          content_wrapper.highlight(q);
          original_top_search_val = q;
          setHistory(data.history);
        });
      }, 700);
    }
  );

  var qAdminSearchURL = new URL(window.location.href).searchParams.get("q");
  if (qAdminSearchURL) {
    $("#search_input").val(qAdminSearchURL).trigger("keyup");
  }

  // Focus search input on click
  $("#top_search_button button").on("click", function () {
    var $searchInput = $("#search_input");
    if ($(this).hasClass("search_remove")) {
      $(this).html('<i class="fa fa-search"></i>').removeClass("search_remove");
      original_top_search_val = "";
      $("#search_results").html("");
      $searchInput.val("");
    }
    $searchInput.focus();
  });

  // Fix for dropdown search to close if user click anyhere on html except on dropdown
  $("body").click(function (e) {
    if (
      !$(e.target).parents("#top_search_dropdown").hasClass("search-results")
    ) {
      $("#top_search_dropdown").remove();
    }
  });


 }
 root.NativeAdminSearch={init:init,setHistory:setHistory};
})(window,jQuery);
