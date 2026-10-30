<script>
	(function($) {
		"use strict";  

		appValidateForm($("body").find('#add_edit_category'), {
			'name': 'required',
			'commodity_group_code': 'required',
		});  
	})(jQuery);

	function new_category(){
		"use strict";

		$('#measure_category').modal('show');
		$('.edit-title').addClass('hide');
		$('.add-title').removeClass('hide');
		$('#categories_id').html('');

		$('#measure_category input[name="name"]').val('');
		$('#measure_category input[name="commodity_group_code"]').val('');
		$('#measure_category textarea[name="note"]').val('');
		$('#measure_category input[name="display"]').prop("checked", true);

	}

	function edit_category(invoker,id){
		"use strict";

		$('#measure_category').modal('show');
		$('.edit-title').removeClass('hide');
		$('.add-title').addClass('hide');

		$('#categories_id').html('');
		$('#categories_id').append(hidden_input('id',id));

		$('#measure_category input[name="name"]').val($(invoker).data('name'));
		$('#measure_category input[name="commodity_group_code"]').val($(invoker).data('commodity_group_code'));
		$('#measure_category textarea[name="note"]').val($(invoker).data('note'));

		if($(invoker).data('display') == 1){
			$('#measure_category input[name="display"]').prop("checked", true);
		}else{
			$('#measure_category input[name="display"]').prop("checked", false);
		}
	}
</script>