<script>
	$(function(){
		'use strict';
		var ProposalServerParams = {
			"client_filter": "[name='client_filter[]']",
		};

		var service_management_table = $('table.table-service_management_table');
		var _table_api = initDataTable(service_management_table, admin_url+'service_management/service_management_table', [0], [0], ProposalServerParams,  [4, 'desc']);
		$.each(ProposalServerParams, function(i, obj) {
			$('select' + obj).on('change', function() {  
				service_management_table.DataTable().ajax.reload();
			});
		});
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

				var av_tasks_tables = ['.table-service_management_table'];
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