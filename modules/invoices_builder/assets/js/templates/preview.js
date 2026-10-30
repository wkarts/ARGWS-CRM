(function($) {
    "use strict";
    $('.preview-form').removeClass('preview-form-overflow');
    $(window).on('load', function () {
       init_page_rotation();
    });
})(jQuery);
var page_width = 0;
async function init_page_rotation(){
    "use strict";
    var page_list = $('.page');
    var page_obj = page_list.eq(0);
    if(page_width == 0){
        page_width = $('.preview-fr').width() - 60;
    }
    var page_height = 0;
    if($('#page_rotation').val() == 'portrait'){
        page_height = page_width * 1.41428571429;
        page_obj.width(page_width);
    }
    else{
        var landcape_width = page_width + (page_width / 2);
        page_height = landcape_width * 0.70707070707;  
        page_obj.width(landcape_width);
        $('.preview-fr').removeClass('col-md-6');
        $('.preview-fr').addClass('col-md-12');
    }
}