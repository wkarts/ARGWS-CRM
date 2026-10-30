<script>
  window.addEventListener('load',function(){
    "use strict";
  	
      appValidateForm($('#contract-type-form'),{name:'required'},manage_contract_types);
      $('#type').on('hidden.bs.modal', function(event) {
        $('#additional').html('');
        $('#type input[name="name"]').val('');
        $('.add-title').removeClass('hide');
        $('.edit-title').removeClass('hide');
    });
  });
  function manage_contract_types(form) {
    "use strict";
    
    var data = $(form).serialize();
    var url = form.action;
    $.post(url, data).done(function(response) {
        response = JSON.parse(response);
        if(response.success == true){
            alert_float('success',response.message);
            if($('body').hasClass('contract') && typeof(response.id) != 'undefined') {
                var ctype = $('#contract_type');
                ctype.find('option:first').after('<option value="'+response.id+'">'+response.name+'</option>');
                ctype.selectpicker('val',response.id);
                ctype.selectpicker('refresh');
            }
        }
        if($.fn.DataTable.isDataTable('.table-contract-types')){
            $('.table-contract-types').DataTable().ajax.reload();
        }
        $('#type').modal('hide');
    });
    return false;
}
function new_type(){
    "use strict";

    $('#type').modal('show');
    $('.edit-title').addClass('hide');
}
function edit_type(invoker,id){
    "use strict";

    var name = $(invoker).data('name');
    $('#additional').append(hidden_input('id',id));
    $('#type input[name="name"]').val(name);
    $('#type').modal('show');
    $('.add-title').addClass('hide');
}
</script>