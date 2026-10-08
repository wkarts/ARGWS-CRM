<script>
	var expenseDropzone;

	(function($) {
		"use strict";

		if($('#dropzoneDragArea').length > 0){
			expenseDropzone = new Dropzone("#add_update_product", appCreateDropzoneOptions({
				autoProcessQueue: false,
				clickable: '#dropzoneDragArea',
				previewsContainer: '.dropzone-previews',
				addRemoveLinks: true,
				maxFiles: 10,

				success:function(file,response){
					response = JSON.parse(response);
					if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
						window.location.assign(response.url);
					}else{
						expenseDropzone.processQueue();

					}

				},

			}));
		}

	})(jQuery); 

	Dropzone.options.expenseForm = false;

	//variation
	var addMoreVendorsInputKey;
	addMoreVendorsInputKey = $('.list_approve').length;

	<?php if(isset($total_billing_plan)){ ?>
		addMoreVendorsInputKey = <?php echo new_html_entity_decode($total_billing_plan) ?>;
	<?php } ?>

	$("body").on('click', '.new_wh_approval', function() {
		'use strict';

		if ($(this).hasClass('disabled')) { return false; }

		var newattachment = $('.list_approve').find('#item_approve').eq(0).clone().appendTo('.list_approve');
		newattachment.find('button[data-toggle="dropdown"]').remove();
		newattachment.find('select').selectpicker('refresh');

		newattachment.find('button[data-id="unit_id[0]"]').attr('data-id', 'unit_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="unit_id[0]"]').attr('for', 'unit_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('select[unit_id="unit_id[0]"]').attr('unit_id', 'unit_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('select[id="unit_id[0]"]').attr('id', 'unit_id[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('select[name="unit_id[0]"]').attr('name', 'unit_id[' + addMoreVendorsInputKey + ']').val('').change();

		newattachment.find('button[data-id="item_rate[0]"]').attr('data-id', 'item_rate[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="item_rate[0]"]').attr('for', 'item_rate[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[item_rate="item_rate[0]"]').attr('item_rate', 'item_rate[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="item_rate[0]"]').attr('id', 'item_rate[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="item_rate[0]"]').attr('name', 'item_rate[' + addMoreVendorsInputKey + ']').val('');

		newattachment.find('button[data-id="extend_value[0]"]').attr('data-id', 'extend_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="extend_value[0]"]').attr('for', 'extend_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('select[extend_value="extend_value[0]"]').attr('extend_value', 'extend_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('select[id="extend_value[0]"]').attr('id', 'extend_value[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('select[name="extend_value[0]"]').attr('name', 'extend_value[' + addMoreVendorsInputKey + ']').val('').change();

		newattachment.find('button[data-id="promotion_extended_percent[0]"]').attr('data-id', 'promotion_extended_percent[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="promotion_extended_percent[0]"]').attr('for', 'promotion_extended_percent[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[promotion_extended_percent="promotion_extended_percent[0]"]').attr('promotion_extended_percent', 'promotion_extended_percent[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="promotion_extended_percent[0]"]').attr('id', 'promotion_extended_percent[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="promotion_extended_percent[0]"]').attr('name', 'promotion_extended_percent[' + addMoreVendorsInputKey + ']').val('');

		newattachment.find('button[data-id="status_cycles[0]"]').attr('data-id', 'status_cycles[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="status_cycles[0]"]').attr('for', 'status_cycles[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[status_cycles="status_cycles[0]"]').attr('status_cycles', 'status_cycles[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="status_cycles[0]"]').attr('id', 'status_cycles[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[name="status_cycles[0]"]').attr('name', 'status_cycles[' + addMoreVendorsInputKey + ']').prop('checked', false);


		newattachment.find('button[data-id="cycle_id[0]"]').attr('data-id', 'cycle_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="cycle_id[0]"]').attr('for', 'cycle_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[cycle_id="cycle_id[0]"]').attr('cycle_id', 'cycle_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="cycle_id[0]"]').attr('id', 'cycle_id[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="cycle_id[0]"]').attr('name', 'cycle_id[' + addMoreVendorsInputKey + ']').val('');
		
		newattachment.find('button[data-id="item_id[0]"]').attr('data-id', 'item_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="item_id[0]"]').attr('for', 'item_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[item_id="item_id[0]"]').attr('item_id', 'item_id[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="item_id[0]"]').attr('id', 'item_id[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="item_id[0]"]').attr('name', 'item_id[' + addMoreVendorsInputKey + ']').val('');

		newattachment.find('button[data-id="unit_value[0]"]').attr('data-id', 'unit_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="unit_value[0]"]').attr('for', 'unit_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[unit_value="unit_value[0]"]').attr('unit_value', 'unit_value[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="unit_value[0]"]').attr('id', 'unit_value[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="unit_value[0]"]').attr('name', 'unit_value[' + addMoreVendorsInputKey + ']').val('');
		
		newattachment.find('button[data-id="unit_type[0]"]').attr('data-id', 'unit_type[' + addMoreVendorsInputKey + ']');
		newattachment.find('label[for="unit_type[0]"]').attr('for', 'unit_type[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[unit_type="unit_type[0]"]').attr('unit_type', 'unit_type[' + addMoreVendorsInputKey + ']');
		newattachment.find('input[id="unit_type[0]"]').attr('id', 'unit_type[' + addMoreVendorsInputKey + ']').val('');
		newattachment.find('input[name="unit_type[0]"]').attr('name', 'unit_type[' + addMoreVendorsInputKey + ']').val('');
		
		

		newattachment.find('button[name="add"] i').removeClass('fa-plus').addClass('fa-minus');
		newattachment.find('button[name="add"]').removeClass('new_wh_approval').addClass('remove_wh_approval').removeClass('btn-success').addClass('btn-danger');
		addMoreVendorsInputKey++;

	});

	$("body").on('click', '.remove_wh_approval', function() {
		'use strict';

		$(this).parents('#item_approve').remove();
	});

	 appValidateForm($("body").find('#add_update_product'), {
	  	'description': 'required',
	  	'commodity_code': 'required',
	  }, productSubmitHandler); 


	$('input[name="can_be_sold"]').on('click', function() {
		'use strict';

		var can_be_sold =$('#can_be_sold').is(':checked');
		if(can_be_sold == true){
			$('.tab_sales_hide').removeClass('hide');
		}else{
			$('.tab_sales_hide').addClass('hide');
		}
	});


	$('input[name="can_be_purchased"]').on('click', function() {
		'use strict';

		var can_be_purchased =$('#can_be_purchased').is(':checked');
		if(can_be_purchased == true){
			$('.tab_purchase_hide').removeClass('hide');
		}else{
			$('.tab_purchase_hide').addClass('hide');
		}
	});


	function productSubmitHandler(form) {
		'use strict';
		
		var data={};
		data.formdata = $( form ).serializeArray();

		var service_policy = tinymce.get("service_policy").getContent();
		data.service_policy = JSON.stringify(service_policy);

		var sku_data ={};
		sku_data.sku_code =  $('input[name="sku_code"]').val();
		if($('input[name="id"]').val() != '' && $('input[name="id"]').val() != 0){
			sku_data.item_id =  $('input[name="id"]').val();
		}else{
			sku_data.item_id = '';
		}

		$.post(admin_url + 'service_management/check_sku_duplicate', sku_data).done(function(response) {
			response = JSON.parse(response);

			if(response.message == 'false' || response.message ==  false){

				alert_float('warning', "<?php echo _l('sku_code_already_exists') ?>");

			}else{

				//show box loading
				var html = '';
				html += '<div class="Box">';
				html += '<span>';
				html += '<span></span>';
				html += '</span>';
				html += '</div>';
				$('#box-loading').html(html);

				$('.submit_button').attr( "disabled", "disabled" );

				$.post(form.action, data).done(function(response) {
					var response = JSON.parse(response);
					if (response.commodityid) {
						if(typeof(expenseDropzone) !== 'undefined'){
							if (expenseDropzone.getQueuedFiles().length > 0) {
								
								expenseDropzone.options.url = admin_url + 'service_management/add_product_attachment/' + response.commodityid;
								expenseDropzone.processQueue();

							} else {
								window.location.assign(response.url);
							}
						} else {
							window.location.assign(response.url);
						}
					} else {
						window.location.assign(response.url);
					}
				});
			}

		});

		return false;

	}


	function delete_product_attachment(wrapper, attachment_id, rel_type) {
	 	"use strict";  
		
		if (confirm_delete()) {
			$.get(admin_url + 'service_management/delete_product_attachment/' +attachment_id+'/'+rel_type, function (response) {
				if (response.success == true) {
					$(wrapper).parents('.dz-preview').remove();

					var totalAttachmentsIndicator = $('.dz-preview'+attachment_id);
					var totalAttachments = totalAttachmentsIndicator.text().trim();

					if(totalAttachments == 1) {
						totalAttachmentsIndicator.remove();
					} else {
						totalAttachmentsIndicator.text(totalAttachments-1);
					}
					alert_float('success', "<?php echo _l('deleted_product_image_successfully') ?>");

				} else {
					alert_float('danger', "<?php echo _l('deleted_product_image_failed') ?>");
				}
			}, 'json');
		}
		return false;
	}
	
</script>