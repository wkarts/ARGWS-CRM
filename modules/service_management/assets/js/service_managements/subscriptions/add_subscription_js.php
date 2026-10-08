<script>
	$(function(){
		"use strict";  

				// Project ajax search
		appValidateForm('#subscriptionForm',{
			product_id:'required',
			name:'required',
			clientid:'required',
			stripe_plan_id:'required',
			currency:'required',
			quantity: {
				required:true,
				min:1,
			}
		});

		<?php if(!isset($subscription) || (isset($subscription) && empty($subscription->stripe_subscription_id))) { ?>

			checkFirstBillingDate($('#stripe_plan_id').selectpicker('val'));

			$('#stripe_plan_id').on('change', function () {
				var selectedPlan = $(this).val();
				checkFirstBillingDate(selectedPlan);
				var selectedOption = $('#stripe_plan_id').find('option[value="'+selectedPlan+'"]');
				var interval = selectedOption.data('interval');
				var $firstBillingDate = $('#date');
				var firstBillingDate = $firstBillingDate.val();
				if(interval == 'month') {
					var currentDate = moment().add(1, 'day').format('YYYY-MM-DD');
					var futureMonth = moment(currentDate).add(selectedOption.data('interval-count'), 'M');
					$firstBillingDate.attr('data-date-end-date', futureMonth.format('YYYY-MM-DD'));
					$firstBillingDate.datetimepicker('destroy');
					init_datepicker($firstBillingDate);
				}
			});
		<?php } ?>

		$('#subscriptionForm').on('dirty.areYouSure', function() {
			$('#prorateWrapper').removeClass('hide');
		});

		$('#subscriptionForm').on('clean.areYouSure', function() {
			$('#prorateWrapper').addClass('hide');
		});

		

		$('select[name="product_id"]').on('change', function () {
			var product_id = $('select[name="product_id"]').val();
			var data = {};
			data.product_id = product_id;
			if(product_id != undefined && product_id.length > 0){
				$.post(admin_url + 'service_management/get_product_plan', data).done(function(response){
					response = JSON.parse(response);
					$('select[name="stripe_plan_id"]').val(response.stripe_plan_id).change();
					$('input[name="name"]').val(response.subscription_name);
				});

			}else{
				$('select[name="stripe_plan_id"]').val('').change();
				$('input[name="name"]').val('');
			}
		});

	});
	function checkFirstBillingDate(selectedPlan) {
		"use strict";  
		
		if(selectedPlan == '') {
			return;
		}
		var interval = $('#stripe_plan_id').find('option[value="'+selectedPlan+'"]').data('interval');
		if(interval == 'week' || interval == 'day') {
			$('#first_billing_date_wrapper').addClass('hide');
			$('#date').val('');
		} else {
			$('#first_billing_date_wrapper').removeClass('hide');
		}
	}
</script>