<script>
(function($) {
    "use strict";
    $('#template_id').on('change', function(){
    	var id = $(this).val();
    	get_exam_template(id);
    });
})(jQuery);

function filter_invoice(){
	"use strict";
	var data = {};
	data.customer = $('select[name="customer"]').val();
	data.project = $('select[name="project"]').val();
	data.sale_agent = $('select[name="sale_agent"]').val();
	data.template_id = $('select[name="template_id"]').val();

	$.post(admin_url + 'invoices_builder/filter_invoice', data ).done(function(response){
		response = JSON.parse(response);
		$('select[name="invoice_id[]"]').html(response.html);
		$('select[name="invoice_id[]"]').selectpicker('refresh');
	});
}

function customer_change(el){
	"use strict";
	var customer = $(el).val();
	requestGet('invoices_builder/filter_project_by_customer/' + customer  ).done(function (response) { 
		response = JSON.parse(response);
		$('select[name="project"]').html(response.html);
		$('select[name="project"]').selectpicker('refresh');
	});

	filter_invoice();
}

function project_change(el){
	"use strict";
	filter_invoice();
}

function sale_agent_change(el){
	"use strict";
	filter_invoice();
}

function template_change(el){
	"use strict";
	filter_invoice();
}
var page_width = 0;
function init_page_rotation(){
    "use strict";
    $('.preview-form').removeClass('preview-form-overflow');
    var page_list = $('.page');
    var page_obj = page_list.eq(0);
        page_width = page_obj.width();
    var page_content_height = page_obj.find('.page-content').height();
    var page_height = 0;
    if($('#page_rotation').val() == 'portrait'){
        page_height = page_width * 1.41428571429;
        page_obj.width(page_width);
    }
    else{
        let landcape_width = page_width + (page_width / 2);
        page_height = landcape_width * 0.70707070707;  
        page_obj.width(landcape_width);
        $('.preview-form').addClass('overflow-x');
    }
}
function get_exam_template(id){
    "use strict";
    $('#preview_area').html("");
	$.get(admin_url+'invoices_builder/get_preview/'+id, function(reponses){
    	$('#preview_area').html(reponses);
    	init_page_rotation();
    });
}
</script>