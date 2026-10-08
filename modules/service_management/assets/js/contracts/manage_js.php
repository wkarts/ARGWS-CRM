<script type="text/javascript">
	$(function(){
		"use strict"; 
		

		var ContractsServerParams = {};
		$.each($('._hidden_inputs._filters input'),function(){
			ContractsServerParams[$(this).attr('name')] = '[name="'+$(this).attr('name')+'"]';
		});

		initDataTable('.table-contracts', admin_url+'service_management/table', undefined, undefined, ContractsServerParams,<?php echo hooks()->apply_filters('contracts_table_default_order', json_encode(array(6,'asc'))); ?>);

		new Chart($('#contracts-by-type-chart'), {
			type: 'bar',
			data: <?php echo  new_html_entity_decode($chart_types); ?>,
			options: {
				legend: {
					display: false,
				},
				responsive: true,
				maintainAspectRatio:false,
				scales: {
					yAxes: [{
						display: true,
						ticks: {
							suggestedMin: 0,
						}
					}]
				}
			}
		});
		new Chart($('#contracts-value-by-type-chart'), {
			type: 'line',
			data: <?php echo   new_html_entity_decode($chart_types_values); ?>,
			options: {
				responsive: true,
				legend: {
					display: false,
				},
				maintainAspectRatio:false,
				scales: {
					yAxes: [{
						display: true,
						ticks: {
							suggestedMin: 0,
						}
					}]
				}
			}
		});
	});
</script>