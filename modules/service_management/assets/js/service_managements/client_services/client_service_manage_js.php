<script>
	$(function(){
		'use strict';
		var ProposalServerParams = {
			"client_filter": "[name='client_filter[]']",
			"order_filter": "[name='order_filter[]']",
			"product_filter": "[name='product_filter[]']",
			"service_status_filter": "[name='service_status_filter[]']",
		};

		var client_service_table = $('table.table-client_service_table');
		var _table_api = initDataTable(client_service_table, admin_url+'service_management/client_service_table', [0], [0], ProposalServerParams,  [0, 'desc']);
		$.each(ProposalServerParams, function(i, obj) {
			$('select' + obj).on('change', function() {  
				client_service_table.DataTable().ajax.reload();
			});
		});

		var hidden_columns = [0,8];
		client_service_table.DataTable().columns(hidden_columns).visible(false, false);

	});

	function order_status_mark_as(status, task_id, type) {
		"use strict"; 
		
		var url = 'service_management/order_status_mark_as/' + status + '/' + task_id + '/' + type;
		var taskModalVisible = $('#task-modal').is(':visible');
		url += '?single_task=' + taskModalVisible;
		$("body").append('<div class="dt-loader"></div>');

		requestGetJSON(url).done(function (response) {
			$("body").find('.dt-loader').remove();
			if (response.success === true || response.success == 'true') {

				var av_tasks_tables = ['.table-client_service_table'];
				$.each(av_tasks_tables, function (i, selector) {
					if ($.fn.DataTable.isDataTable(selector)) {
						$(selector).DataTable().ajax.reload(null, false);
					}
				});
				alert_float('success', response.message);
			}
		});
	}

</script>