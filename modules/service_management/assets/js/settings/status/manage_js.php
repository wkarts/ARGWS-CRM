<script>
	(function($) {
		"use strict";  

		appValidateForm($("body").find('#add_edit_category'), {
			'status_name': 'required',
			'status_code': 'required',
		});  
	})(jQuery);

	function new_status(){
		"use strict";

		$('#item_status').modal('show');
		$('.edit-title').addClass('hide');
		$('.add-title').removeClass('hide');
		$('#status_id').html('');

		$('#item_status input[name="status_name"]').val('');
		$('#item_status input[name="status_code"]').val('');
		$('#item_status textarea[name="note"]').val('');
		$('#item_status input[name="display"]').prop("checked", true);

	}

	function edit_status(invoker,id){
		"use strict";

		$('#item_status').modal('show');
		$('.edit-title').removeClass('hide');
		$('.add-title').addClass('hide');

		$('#status_id').html('');
		$('#status_id').append(hidden_input('id',id));

		$('#item_status input[name="status_name"]').val($(invoker).data('status_name'));
		$('#item_status input[name="status_code"]').val($(invoker).data('status_code'));
		$('#item_status textarea[name="note"]').val($(invoker).data('note'));

		if($(invoker).data('display') == 1){
			$('#item_status input[name="display"]').prop("checked", true);
		}else{
			$('#item_status input[name="display"]').prop("checked", false);
		}
	}
</script>