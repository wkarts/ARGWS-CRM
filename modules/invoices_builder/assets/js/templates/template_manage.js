var table_templates = $('.table-table_templates');
(function($) {
      "use strict";
      var Params = {
        "vendor": "[name='vendor[]']",
        "department": "[name='department[]']",
        "project": "[name='project[]']",
    };
    initDataTable(table_templates, admin_url+'invoices_builder/table_templates',[0], [], Params);
    $.each(Params, function(i, obj) {
        $('select' + obj).on('change', function() {  
            table_templates.DataTable().ajax.reload()
            .columns.adjust()
            .responsive.recalc();
        });
    });
    $(document).on("click",'.image-contain .base64-image',function() {
        var modal = $('#preview_modal');
        modal.modal();
        var obj = $(this);
        var src = obj.attr('src');
        var img_obj = $('#preview_modal img');
        img_obj.attr('src', src);
        var template_name = obj.closest('tr').find('span.template-name').text();
        modal.find('.modal-header .add-title').text(template_name);
    });

    $(document).on("change",'.onoffswitch-checkbox',function() {
        var obj  = $(this);
        var id = obj.data('id');
        if(!obj.is(':checked')){
            if(confirm($('[name="confirm_text"]').val())){
                change_template_status(id, 0);
            }else{
                obj.prop('checked', true);
                return false;
            }
        }
        else{
            change_template_status(id, 1);
        }
    });

    
})(jQuery);

function change_template_status(id, status){
    "use strict";
    $.get(admin_url+'invoices_builder/change_template_active/'+id+'/'+status, function(response){
    });
}
function clone_template(id){
    "use strict";
    $.get(admin_url+'invoices_builder/clone_template/'+id, function(response){
        response = JSON.parse(response);
        if(response.success){
            alert_float('success', response.message);
             table_templates.DataTable().ajax.reload()
            .columns.adjust()
            .responsive.recalc();
        }
        else{
            alert_float('danger', response.message);
        }
    });
}
