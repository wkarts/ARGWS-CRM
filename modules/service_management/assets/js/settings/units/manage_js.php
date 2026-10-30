<script>
	(function($) {
		"use strict";  

		appValidateForm($("body").find('#add_edit_unit'), {
			'unit_code': 'required',
			'unit_name': 'required',
			'unit_value': 'required',
			'unit_type': 'required',
		});  
	})(jQuery);

	function new_unit(){
		"use strict";

		$('#item_unit').modal('show');
		$('.edit-title').addClass('hide');
		$('.add-title').removeClass('hide');
		$('#unit_id').html('');

		$('#item_unit input[name="unit_code"]').val('');
		$('#item_unit input[name="unit_name"]').val('');
		$('#item_unit input[name="unit_value"]').val('');
		$('#item_unit select[name="unit_type"]').val('').change();
		$('#item_unit textarea[name="note"]').val('');
		$('#item_unit input[name="display"]').prop("checked", true);

	}

	function edit_unit(invoker,id){
		"use strict";

		$('#item_unit').modal('show');
		$('.edit-title').removeClass('hide');
		$('.add-title').addClass('hide');

		$('#unit_id').html('');
		$('#unit_id').append(hidden_input('id',id));

		$('#item_unit input[name="unit_code"]').val($(invoker).data('unit_code'));
		$('#item_unit input[name="unit_name"]').val($(invoker).data('unit_name'));
		$('#item_unit input[name="unit_value"]').val($(invoker).data('unit_value'));
		$('#item_unit select[name="unit_type"]').val($(invoker).data('unit_type')).change();
		$('#item_unit textarea[name="note"]').val($(invoker).data('note'));

		if($(invoker).data('display') == 1){
			$('#item_unit input[name="display"]').prop("checked", true);
		}else{
			$('#item_unit input[name="display"]').prop("checked", false);
		}
	}
</script>