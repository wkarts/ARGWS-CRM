<script>
	var purchase;
	var warehouses;
	var lastAddedItemKey = null;
	(function($) {
		"use strict";  

		init_goods_delivery_currency(<?php echo new_html_entity_decode($base_currency_id) ?>);
		
		appValidateForm($('#add_order'), {
			datecreated: 'required',
			client_id: 'required',
			created_id: 'required',
			
		});

		/*Maybe items ajax search*/
		init_ajax_search('items','#item_select.ajax-search',undefined,admin_url+'warehouse/wh_commodity_code_search/rate');

		wh_calculate_total(); 


	})(jQuery);



	(function($) {
		"use strict";

		/*Add item to preview from the dropdown for invoices estimates*/
		$("body").on('change', 'select[name="item_select"]', function () {
			var itemid = $(this).selectpicker('val');
			if (itemid != '') {
				var client_id = $('select[name="client_id"]').val();
				if(client_id!= ''){
					wh_add_item_to_preview(itemid);

				}else{
					$('.main input[name="billing_plan_unit_id"]').val('');
					alert('You need to select Customer');
					$('html,body').animate({
						scrollTop: 0
					}, 'slow');
				}

			}
		});

		/*Recaulciate total on these changes*/
		$("body").on('change', 'select.taxes', function () {
			wh_calculate_total();
		});

		$("body").on('click', '.add_order', function () {
			submit_form(false);
		});

		$('.add_order_send').on('click', function() {
			submit_form(true);
		});


		$("body").on('change', 'select[name="billing_plan_unit_id"]', function() {
			"use strict"; 
			
			var data = {};
			data.commodity_id = $('.main input[name="item_id"]').val();
			data.billing_plan_unit_id = $('.main select[name="billing_plan_unit_id"]').val();
			data.client_id = $('select[name="client_id"]').val();

			var quantities = $('.main input[name="quantities"]').val();

			if(data.client_id != ''){
				if(data.commodity_id != '' && data.billing_plan_unit_id != ''){
					$.post(admin_url + 'service_management/get_billing_unit', data).done(function(response){
						response = JSON.parse(response);
						$('.main input[name="billing_plan_rate"]').val(response.value);
						$('.main input[name="discount"]').val(response.promotion_extended_percent);
						$('.main input[name="billing_plan_value"]').val(response.billing_plan_value);
						$('.main input[name="billing_plan_type"]').val(response.billing_plan_type);

					});
				}else{
					$('.main input[name="billing_plan_rate"]').val(0);
						$('.main input[name="discount"]').val(0);
					$('.main input[name="billing_plan_value"]').val(0);
					$('.main input[name="billing_plan_type"]').val('');
				}
			}else{
				$('.main input[name="billing_plan_unit_id"]').val('');
				alert('You need to select Customer');
				$('html,body').animate({
					scrollTop: 0
				}, 'slow');
			}

		});

		$('input[name="quantities"]').on('change', function() {
			"use strict"; 

			var available_quantity = $('.main input[name="available_quantity"]').val();
			var quantities = $('.main input[name="quantities"]').val();
			if(parseFloat(available_quantity) < parseFloat(quantities)){
				alert_float('warning', '<?php echo _l('inventory_quantity_is_not_enough') ?>');
				$('.main input[name="quantities"]').val(available_quantity);

			}
		});

		$("body").on('change', 'input[name="shipping_fee"]', function () {
			wh_calculate_total();
		});

	})(jQuery);


	/*Add item to preview*/
	function wh_add_item_to_preview(id) {
		"use strict";

		requestGetJSON('service_management/get_item_by_id/' + id +'/'+true).done(function (response) {
			clear_item_preview_values();

			$('.main input[name="item_id"]').val(response.itemid);
			$('.main textarea[name="item_name"]').val(response.code_description);
			$('.main input[name="unit_price"]').val(response.rate);
			$('.main input[name="unit_name"]').val(response.unit_name);
			$('.main input[name="unit_id"]').val(response.unit_id);
			$('.main input[name="quantity"]').val(1);
			$('.main select[name="billing_plan_unit_id"]').html(response.billing_plan_html);
			$('.selectpicker').selectpicker('refresh');

			var taxSelectedArray = [];
			if (response.taxname && response.taxrate) {
				taxSelectedArray.push(response.taxname + '|' + response.taxrate);
			}
			if (response.taxname_2 && response.taxrate_2) {
				taxSelectedArray.push(response.taxname_2 + '|' + response.taxrate_2);
			}

			$('.main select.taxes').selectpicker('val', taxSelectedArray);

			var $currency = $("body").find('.accounting-template select[name="currency"]');
			var baseCurency = $currency.attr('data-base');
			var selectedCurrency = $currency.find('option:selected').val();
			var $rateInputPreview = $('.main input[name="rate"]');

			if (baseCurency == selectedCurrency) {
				$rateInputPreview.val(response.rate);
			} else {
				var itemCurrencyRate = response['rate_currency_' + selectedCurrency];
				if (!itemCurrencyRate || parseFloat(itemCurrencyRate) === 0) {
					$rateInputPreview.val(response.rate);
				} else {
					$rateInputPreview.val(itemCurrencyRate);
				}
			}

			$(document).trigger({
				type: "item-added-to-preview",
				item: response,
				item_type: 'item',
			});
		});
	}


	function sm_get_item_preview_values() {
		"use strict";

		var response = {};
		response.item_name = $('.invoice-item .main textarea[name="item_name"]').val();
		response.billing_plan_unit_id = $('.invoice-item .main select[name="billing_plan_unit_id"]').val();
		response.quantity = $('.invoice-item .main input[name="quantity"]').val();
		response.billing_plan_value = $('.invoice-item .main input[name="billing_plan_value"]').val();
		response.billing_plan_type = $('.invoice-item .main input[name="billing_plan_type"]').val();
		response.taxname = $('.main select.taxes').selectpicker('val');
		response.tax_rate = $('.invoice-item .main input[name="tax_rate"]').val();
		response.item_id = $('.invoice-item .main input[name="item_id"]').val();
		response.discount = $('.invoice-item .main input[name="discount"]').val();
		response.billing_plan_rate = $('.invoice-item .main input[name="billing_plan_rate"]').val();

		return response;
	}

	function wh_clear_item_preview_values(parent) {
		"use strict";

		var previewArea = $(parent + ' .main');
		previewArea.find('input').val('');
		previewArea.find('textarea').val('');
		previewArea.find('select').val('').selectpicker('refresh');
	}

	function wh_get_item_row_template(name, item_name, billing_plan_unit_id, quantity, billing_plan_value, billing_plan_type, billing_plan_rate, taxname, tax_rate, item_id, discount, itemid, item_key)  {
		"use strict";

		jQuery.ajaxSetup({
			async: false
		});

		var d = $.post(admin_url + 'service_management/get_service_row_template', {

			name: name,
			item_name : item_name,
			billing_plan_unit_id : billing_plan_unit_id,
			quantity : quantity,
			billing_plan_value : billing_plan_value,
			billing_plan_type : billing_plan_type,
			billing_plan_rate : billing_plan_rate,
			taxname : taxname,
			tax_rate : tax_rate,
			item_id : item_id,
			discount : discount,
			itemid : itemid,
			item_key : item_key,
		});
		jQuery.ajaxSetup({
			async: true
		});
		return d;
	}

	function wh_delete_item(row, itemid,parent) {
		"use strict";

		$(row).parents('tr').addClass('animated fadeOut', function () {
			setTimeout(function () {
				$(row).parents('tr').remove();
				wh_calculate_total();
			}, 50);
		});
		if (itemid && $('input[name="isedit"]').length > 0) {
			$(parent+' #removed-items').append(hidden_input('removed_items[]', itemid));
		}
	}

	function wh_reorder_items(parent) {
		"use strict";

		var rows = $(parent + ' .table.has-calculations tbody tr.item');
		var i = 1;
		$.each(rows, function () {
			$(this).find('input.order').val(i);
			i++;
		});
	}

	function wh_calculate_total(){
		"use strict";
		if ($('body').hasClass('no-calculate-total')) {
			return false;
		}

		var calculated_tax,
		taxrate,
		item_taxes,
		row,
		_amount,
		_tax_name,
		taxes = {},
		taxes_rows = [],
		subtotal = 0,
		total = 0,
		total_money = 0,
		total_tax_money = 0,
		quantity = 1,
		total_discount_calculated = 0,
		item_discount_percent = 0,
		item_discount = 0,
		item_total_payment,
		rows = $('.table.has-calculations tbody tr.item'),
		subtotal_area = $('#subtotal'),
		discount_area = $('#discount_area'),
		adjustment = $('input[name="adjustment"]').val(),
		// discount_percent = $('input[name="discount_percent"]').val(),
		discount_percent = 'before_tax',
		discount_fixed = $('input[name="discount_total"]').val(),
		discount_total_type = $('.discount-total-type.selected'),
		discount_type = $('select[name="discount_type"]').val(),
		shipping_fee = $('input[name="shipping_fee"]').val();


		$('.wh-tax-area').remove();

		$.each(rows, function () {

			var item_tax = 0,
			item_amount  = 0;

			quantity = $(this).find('[data-quantity]').val();
			if (quantity === '') {
				quantity = 1;
				$(this).find('[data-quantity]').val(1);
			}
			item_discount_percent = $(this).find('td.discount input').val();

			if (isNaN(item_discount_percent) || item_discount_percent == '') {
				item_discount_percent = 0;
			}

			_amount = accounting.toFixed($(this).find('td.rate input').val() * quantity, app.options.decimal_places);
			item_amount = _amount;
			_amount = parseFloat(_amount);

			$(this).find('td.amount').html(format_money(_amount));

			subtotal += _amount;
			row = $(this);
			item_taxes = $(this).find('select.taxes').val();

			if (item_taxes) {
				$.each(item_taxes, function (i, taxname) {
					taxrate = row.find('select.taxes [value="' + taxname + '"]').data('taxrate');
					calculated_tax = (_amount / 100 * taxrate);
					item_tax += calculated_tax;
					if (!taxes.hasOwnProperty(taxname)) {
						if (taxrate != 0) {
							_tax_name = taxname.split('|');
							var tax_row = '<tr class="wh-tax-area"><td>' + _tax_name[0] + '(' + taxrate + '%)</td><td id="tax_id_' + slugify(taxname) + '"></td></tr>';
							$(subtotal_area).after(tax_row);
							taxes[taxname] = calculated_tax;
						}
					} else {
										// Increment total from this tax
										taxes[taxname] = taxes[taxname] += calculated_tax;
									}
								});
			}
			//Discount of item
			item_discount = (parseFloat(item_amount) + parseFloat(item_tax) ) * parseFloat(item_discount_percent) / 100;
			item_total_payment = parseFloat(item_amount) + parseFloat(item_tax) - parseFloat(item_discount);

			// Append value to item
			total_discount_calculated += item_discount;
			$(this).find('td.discount_money input').val(item_discount);
			$(this).find('td.total_after_discount input').val(item_total_payment);

			$(this).find('td.label_discount_money').html(format_money(item_discount));
			$(this).find('td.label_total_after_discount').html(format_money(item_total_payment));

		});

	// Discount by percent
	if ((discount_percent !== '' && discount_percent != 0) && discount_type == 'before_tax' && discount_total_type.hasClass('discount-type-percent')) {
		total_discount_calculated = (subtotal * discount_percent) / 100;
	} else if ((discount_fixed !== '' && discount_fixed != 0) && discount_type == 'before_tax' && discount_total_type.hasClass('discount-type-fixed')) {
		total_discount_calculated = discount_fixed;
	}

	$.each(taxes, function (taxname, total_tax) {
		if ((discount_percent !== '' && discount_percent != 0) && discount_type == 'before_tax' && discount_total_type.hasClass('discount-type-percent')) {
			total_tax_calculated = (total_tax * discount_percent) / 100;
			total_tax = (total_tax - total_tax_calculated);
		} else if ((discount_fixed !== '' && discount_fixed != 0) && discount_type == 'before_tax' && discount_total_type.hasClass('discount-type-fixed')) {
			var t = (discount_fixed / subtotal) * 100;
			total_tax = (total_tax - (total_tax * t) / 100);
		}

		total += total_tax;
		total_tax_money += total_tax;
		total_tax = format_money(total_tax);
		$('#tax_id_' + slugify(taxname)).html(total_tax);
	});


	total = (total + subtotal);
	total_money = total;
	// Discount by percent
	if ((discount_percent !== '' && discount_percent != 0) && discount_type == 'after_tax' && discount_total_type.hasClass('discount-type-percent')) {
		total_discount_calculated = (total * discount_percent) / 100;
	} else if ((discount_fixed !== '' && discount_fixed != 0) && discount_type == 'after_tax' && discount_total_type.hasClass('discount-type-fixed')) {
		total_discount_calculated = discount_fixed;
	}

	total = total - total_discount_calculated;
	adjustment = parseFloat(adjustment);

	// Check if adjustment not empty
	if (!isNaN(adjustment)) {
		total = total + adjustment;
	}

	if (!isNaN(shipping_fee)) {
		total = total + parseFloat(shipping_fee);
	}

	var discount_html = '-' + format_money(parseFloat(total_discount_calculated));
	$('input[name="discount_total"]').val(accounting.toFixed(total_discount_calculated, app.options.decimal_places));
	
	// Append, format to html and display
	$('.wh-total_discount').html(discount_html + hidden_input('discount_total', accounting.toFixed(total_discount_calculated, app.options.decimal_places))  );
	$('.adjustment').html(format_money(adjustment));
	$('.wh-subtotal').html(format_money(subtotal) + hidden_input('sub_total', accounting.toFixed(subtotal, app.options.decimal_places)));
	$('.wh-total').html(format_money(total) + hidden_input('total', accounting.toFixed(total, app.options.decimal_places))+ hidden_input('total_tax', accounting.toFixed(total_tax_money, app.options.decimal_places)));

	$(document).trigger('sm-order-total-calculated');

}


function get_billing_unit(commodity_code_name, billing_plan_unit_id, name_discount, name_billing_plan_rate, name_billing_plan_value, name_billing_plan_type){
	"use strict"; 
	
	var data = {};
	data.commodity_id = $('input[name="'+commodity_code_name+'"]').val();
	data.billing_plan_unit_id = $('select[name="'+billing_plan_unit_id+'"]').val();
	data.client_id = $('select[name="client_id"]').val();

	if(data.client_id != ''){

		if(data.commodity_id != '' && data.warehouse_id != ''){
			$.post(admin_url + 'service_management/get_billing_unit', data).done(function(response){
				response = JSON.parse(response);
				$('input[name="'+name_discount+'"]').val(response.promotion_extended_percent);
				$('input[name="'+name_billing_plan_rate+'"]').val(response.value);
				$('input[name="'+name_billing_plan_value+'"]').val(response.billing_plan_value);
				$('input[name="'+name_billing_plan_type+'"]').val(response.billing_plan_type);
			});
		}else{
			$('input[name="'+name_discount+'"]').val(0);
			$('input[name="'+name_billing_plan_rate+'"]').val(0);
			$('input[name="'+name_billing_plan_value+'"]').val(0);
			$('input[name="'+name_billing_plan_type+'"]').val('');
		}
	}else{
		alert('You need to select Customer');
		$('html,body').animate({
			scrollTop: 0
		}, 'slow');
	}

	setTimeout(function () {
		wh_calculate_total();
	}, 15);

}


function submit_form(save_and_send_request) {
	"use strict";

	wh_calculate_total();

	var $itemsTable = $('.invoice-items-table');
	var $previewItem = $itemsTable.find('.main');
	var check_billing_plan_status = true,
	check_quantity_status = true;

	if ( $itemsTable.length && $itemsTable.find('.item').length === 0) {
		alert_float('warning', '<?php echo _l('sm_enter_at_least_one_product'); ?>', 3000);
		return false;
	}

	$('input[name="save_and_send_request"]').val(save_and_send_request);

	var rows = $('.table.has-calculations tbody tr.item');
	$.each(rows, function () {

		var billing_plan_id = $(this).find('td.warehouse_select select').val();
		var quantity_value = $(this).find('td.quantity input').val();

		if((billing_plan_id == '' || billing_plan_id == undefined)){
			check_billing_plan_status = false;
		}
		if(parseFloat(quantity_value) == 0){
			check_quantity_status = false;
		}
		
	})

	if(check_billing_plan_status == true && check_quantity_status == true){
		// Add disabled to submit buttons
		$(this).find('.add_order').prop('disabled', true);
		$('#add_order').submit();
	}else{
		if(check_billing_plan_status == false){
			alert_float('warning', '<?php echo _l('sm_please_select_a_billing_plan_unit') ?>');
		}else if(check_quantity_status == false){
			alert_float('warning', '<?php echo _l('sm_please_choose_quantity') ?>');
		}

	}

	return true;
}

function sm_add_item_to_table(data, itemid) {
	"use strict";

	data = typeof (data) == 'undefined' || data == 'undefined' ? sm_get_item_preview_values() : data;

	if ((data.item_id == "" ||  data.billing_plan_unit_id == "" ) ) {
		if(data.item_id == ""){
			alert_float('warning', '<?php echo _l('sm_please_select_a_service_name') ?>');
		}
		if(parseFloat(data.quantity) < 1){
			/*check_available_quantity*/
			alert_float('warning', '<?php echo _l('sm_please_choose_quantity') ?>');
		}
		if(parseFloat(data.billing_plan_unit_id) < 1){
			/*check_available_quantity*/
			alert_float('warning', '<?php echo _l('sm_please_select_a_billing_plan_unit') ?>');
		}

		return;
	}

	var table_row = '';
	var item_key = lastAddedItemKey ? lastAddedItemKey += 1 : $("body").find('.invoice-items-table tbody .item').length + 1;
	lastAddedItemKey = item_key;
	$("body").append('<div class="dt-loader"></div>');

	wh_get_item_row_template('newitems[' + item_key + ']',data.item_name,data.billing_plan_unit_id, data.quantity, data.billing_plan_value, data.billing_plan_type, data.billing_plan_rate, data.taxname,data.tax_rate, data.item_id, data.discount,  itemid, item_key).done(function(output){

		table_row += output;

		lastAddedItemKey = parseInt(lastAddedItemKey) + parseInt(data.quantities);
		$('.invoice-item table.invoice-items-table.items tbody').append(table_row);

		setTimeout(function () {
			wh_calculate_total();
		}, 15);
		init_selectpicker();
		init_datepicker();
		wh_reorder_items('.invoice-item');
		wh_clear_item_preview_values('.invoice-item');
		$('body').find('#items-warning').remove();
		$("body").find('.dt-loader').remove();
		$('#item_select').selectpicker('val', '');

		return true;
	});
	return false;

}


	// Set the currency for accounting
	function init_goods_delivery_currency(id, callback) {
		"use strict"; 

		var $accountingTemplate = $("body").find('.accounting-template');

		if ($accountingTemplate.length || id) {
			var selectedCurrencyId = !id ? $accountingTemplate.find('select[name="currency"]').val() : id;

			requestGetJSON('misc/get_currency/' + selectedCurrencyId)
			.done(function (currency) {
								// Used for formatting money
								accounting.settings.currency.decimal = currency.decimal_separator;
								accounting.settings.currency.thousand = currency.thousand_separator;
								accounting.settings.currency.symbol = currency.symbol;
								accounting.settings.currency.format = currency.placement == 'after' ? '%v %s' : '%s%v';

								wh_calculate_total();

								if(callback) {
									callback();
								}
							});
		}
	}

	$("body").on('change', '#client_id', function () {
		"use strict"; 
		
		var val = $(this).val();
		clear_billing_and_shipping_details();
		if (!val) {
			$('#expenses_to_bill').empty();
			return false;
		}

		requestGetJSON('service_management/sm_client_change_data/' + val).done(function (response) {


			for (var f in billingAndShippingFields) {
				if (billingAndShippingFields[f].indexOf('billing') > -1) {
					if (billingAndShippingFields[f].indexOf('country') > -1) {
						$('select[name="' + billingAndShippingFields[f] + '"]').selectpicker('val', response['billing_shipping'][0][billingAndShippingFields[f]]);
					} else {
						if (billingAndShippingFields[f].indexOf('billing_street') > -1) {
							$('textarea[name="' + billingAndShippingFields[f] + '"]').val(response['billing_shipping'][0][billingAndShippingFields[f]]);
						} else {
							$('input[name="' + billingAndShippingFields[f] + '"]').val(response['billing_shipping'][0][billingAndShippingFields[f]]);
						}
					}
				}
			}

			if (!empty(response['billing_shipping'][0]['shipping_street'])) {
				$('input[name="include_shipping"]').prop("checked", true).change();
			}

			for (var fsd in billingAndShippingFields) {
				if (billingAndShippingFields[fsd].indexOf('shipping') > -1) {
					if (billingAndShippingFields[fsd].indexOf('country') > -1) {
						$('select[name="' + billingAndShippingFields[fsd] + '"]').selectpicker('val', response['billing_shipping'][0][billingAndShippingFields[fsd]]);
					} else {
						if (billingAndShippingFields[fsd].indexOf('shipping_street') > -1) {
							$('textarea[name="' + billingAndShippingFields[fsd] + '"]').val(response['billing_shipping'][0][billingAndShippingFields[fsd]]);
						} else {
							$('input[name="' + billingAndShippingFields[fsd] + '"]').val(response['billing_shipping'][0][billingAndShippingFields[fsd]]);
						}
					}
				}
			}

			init_billing_and_shipping_details();

		});

	});


</script>