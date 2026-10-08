<script>

(function($) {
  "use strict";
  	var invoice_id = $('input[name="invoice_id"]').val();
  	var template_id = $('select[name="template_id"]').val();
  	var built_invoice_id = '<?php echo html_entity_decode($built_invoice->id); ?>';

  	requestGet('invoices_builder/get_additional_infor/' + invoice_id +'/'+ template_id + '/' + built_invoice_id).done(function (response) { 
		response = JSON.parse(response);
		$('#additional_infor').html(response.html);
	});
})(jQuery);


function template_change(el) {
	"use strict";
	var template_id = $(el).val();
	var invoice_id = $('input[name="invoice_id"]').val();
	var built_invoice_id = '<?php echo html_entity_decode($built_invoice->id); ?>';

	requestGet('invoices_builder/get_additional_infor/' + invoice_id +'/'+ template_id + '/' + built_invoice_id).done(function (response) { 
		response = JSON.parse(response);
		$('#additional_infor').html(response.html);
	});
}
</script>