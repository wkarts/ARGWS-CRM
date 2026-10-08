<script>
	$(function(){
		"use strict";

		var ContractsServerParams = {};
		$.each($('._hidden_inputs._filters input'),function(){
			ContractsServerParams[$(this).attr('name')] = '[name="'+$(this).attr('name')+'"]';
		});

		initDataTable('.table-contracts', admin_url+'service_management/contract_addendums_table',[0], [0], ContractsServerParams,  [0, 'desc']);

		var hidden_columns = [0];
		$('.table-contracts').DataTable().columns(hidden_columns).visible(false, false);

	});
</script>