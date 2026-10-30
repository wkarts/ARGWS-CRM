<script>
    var item_table_custom_field_value = <?php echo html_entity_decode($item_table_custom_field_value); ?>;
    var invoice_total_custom_field_value = <?php echo html_entity_decode($invoice_total_custom_field_value); ?>;
    let page_width = 0;
    var logo_obj = $('#logo img');
    var logo_img_width = logo_obj.width();
    var logo_img_height = logo_obj.height();
    (function($) {
      "use strict";
      var width = $('#preview_area .panel-body').width();   
      var preview_area_obj = $('#preview_area .panel-body');
      var stickyTop = 60;
      var preview_init_class = 'sticky-preview';
      if(is_mobile() == 1){
        preview_init_class = 'mobile-sticky-preview';
      }

      var template_width = $('.template_builder_content .row').width();
      if(template_width < 1400){
        $('.build-section').removeClass('col-md-6').addClass('col-md-12 pright-0');
        $('.preview-form').removeClass('preview-form-overflow');
      }
      else{
          preview_area_obj.width(width).addClass(preview_init_class); 
          $(window).scroll(function() {
            var windowTop = $(window).scrollTop();
            if (stickyTop < windowTop) {
                $('.'+preview_init_class).addClass('sticky-preview-1');
            } else {
                $('.'+preview_init_class).removeClass('sticky-preview-1');
            }
        });
      }

      $(window).on('load', function () {
        var list_checkbox = $('input[type="checkbox"]');
        $.each(list_checkbox, function () {
            var obj = $(this);
            init_validate_child_element(obj);
        });
        trigger_order_column();  
        $(window).trigger('scroll');
    });



      $('input[name="header[col_number]"]').on('change', function(){
        var column_number = $('input[name="header[col_number]"]').val(); 
        init_column_validate(column_number, '.header_panel');
    });
      $('input[name="sender_receiver[col_number]"]').on('change', function(){
        var column_number = $('input[name="sender_receiver[col_number]"]').val(); 
        init_column_validate(column_number, '.sender_receiver_panel');
    });
      $('input[name="invoice_total[col_number]"]').on('change', function(){
        var column_number = $('input[name="invoice_total[col_number]"]').val(); 
        init_column_validate(column_number, '.invoice_total_panel');
    });
      $('input[name="bottom_information[col_number]"]').on('change', function(){
        var column_number = $('input[name="bottom_information[col_number]"]').val(); 
        init_column_validate(column_number, '.bottom_information_panel');
    });
      $('input[name="footer[col_number]"]').on('change', function(){
        var column_number = $('input[name="footer[col_number]"]').val(); 
        init_column_validate(column_number, '.footer_panel');
    });


      $('[name="select_an_available_field"]').on('change', function(){
        var obj = $(this);
        $('.error_add_customfield').addClass('hide').text('');
        var is_checked = obj.is(':checked');
        var parent = obj.closest('li');
        if(is_checked){
            parent.find('.fr2').removeClass('hide');
            parent.find('.fr1').addClass('hide');
        }
        else{
            parent.find('.fr1').removeClass('hide');
            parent.find('.fr2').addClass('hide');
        }
    });

      $(document).on("change",'[name="add_expression_slug"]',function() {
        var obj = $(this);
        var parent = obj.closest('.formula');
        var value = obj.val();
        var input = parent.find('textarea'); 
        var text = input.val();
        input.val(text+''+value);
    });

      $(document).on("change",'.formula [name="add_expression_slug"]',function() {
        var parent = $(this).closest('.formula');
        parent.find('textarea').trigger("keyup");
    });

      $(document).on("change",'input[type="checkbox"]',function() {
        var obj = $(this);
        init_validate_child_element(obj);
    });



        $('.save_template').on('click', function(){
            // Clear error warning on input
            $.each(list_id, function (e, value) {
                $('[name="'+value+'"]').removeClass('border-danger');
            });
            list_id = [];
            var has_error = false;
            has_error = check_form();
            if(!has_error){
                $('#design_area .save_template').attr('disabled', true);
                $('#design_area .save_template').text('<?php echo _l('ib_processing'); ?>');
                html2canvas(document.getElementById("main_page"),
                {
                    allowTaint: true,
                    useCORS: true,
                    taintTest: false,
                    letterRendering: true
                }).then(function (canvas) {
                    // document.getElementById("previewImg").appendChild(canvas);
                    $('[name="capture_image"]').val(htmlEncode(canvas.toDataURL().replace('data:image/png;base64,','')));
                    $('[name="capture_html"]').val($('.preview-form').html());
                    $('#design_area #save_template_btn').click();
                });
            }
            else{
                // Show error warning on input
                $.each(list_id, function (e, value) {
                        $('[name="'+value+'"]').addClass('border-danger');
                });
            }
        });
        $(document).on("change",'input, select, textarea',function() {
            var name = $(this).attr('name');
            change_preview(name);
        });
        $(document).on("click",'.colorpicker-saturation',function() {        
            change_preview(current_control);
        });
        $(document).on("click",'.colorpicker-input',function() {
            current_control = $(this).find('input[type="text"]').attr('name');
        });
 })(jQuery);
var current_control;
var list_id = [];
var timeout = null;
function keyup_formula(el){
    "use strict";
    clearTimeout(timeout);
    timeout = setTimeout(function ()
    {
        var obj = $(el);
        var parent = obj.closest('.formula');
        var type = parent.data('type');
        var formula = obj.val();
        parent.find('.formula-error').addClass('hide').text('');
        $('.save_template').attr('disabled', true);
        if(formula != ''){
            var data = {};
            data.formula = formula;
            data.type = type;
            $.get(admin_url + 'invoices_builder/check_fomula', data).done(function (response) {  
                $('.save_template').removeAttr('disabled');
                if(response != 1){
                    parent.find('.formula-error').removeClass('hide').text('<?php echo _l('ib_formula_not_valid'); ?>');
                    return false;
                }
                else{
                    parent.find('.formula-error').addClass('hide').text('');
                }
            });
        }
        else{
            $('.save_template').removeAttr('disabled');
        }
    }, 1000);
}

function init_validate_child_element(obj){
    "use strict";
    var parent = obj.closest('.checkbox_row');
    var first_checkbox = parent.find('input[type="checkbox"]:eq(0)');
    if(first_checkbox.is(':checked')){
        parent.find('input[type="number"], input[type="text"], select').attr('required', true);
    }
    else{
        parent.find('input[type="number"], input[type="texeqt"], select').removeAttr('required');
    }
    $('select[name="add_expression_slug"]').removeAttr('required');
    $('.custom_field_value').removeAttr('required');
}


function trigger_order_column(){
    "use strict";
 $('input[name="header[col_number]"]').trigger('change');
 $('input[name="sender_receiver[col_number]"]').trigger('change');
 $('input[name="invoice_total[col_number]"]').trigger('change');
 $('input[name="bottom_information[col_number]"]').trigger('change');
 $('input[name="footer[col_number]"]').trigger('change');
}

function init_column_validate(column_number, panel_element){
    "use strict";
    if(column_number != ''){
        $(panel_element).find('.order_column').attr('min', 1).attr('max', column_number);
    }
}


/**
 * { view list }
 */
 function view_list(){
    "use strict";
    $('#table_view').removeClass('hide');
    $('#grid_view').addClass('hie');
}

/**
 * { view list }
 */
 function view_grid(){
    "use strict";

    $('#table_view').addClass('hide');
    $('#grid_view').removeClass('hide');
}


function header_logo_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('#logo_setting').removeClass('hide');
    }else{
        $('#logo_setting').addClass('hide');
    }
}

function header_qr_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('#qrcode_setting').removeClass('hide');
    }else{
        $('#qrcode_setting').addClass('hide');
    }
}

function header_title_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('#title_setting').removeClass('hide');
    }else{
        $('#title_setting').addClass('hide');
    }
}

function header_invoice_infor_setting(el){
    "use strict";
    if($(el).is(':checked')){ 
        $('#invoice_infor_setting').removeClass('hide');
        $('#invoice_infor_setting input[type="checkbox"]').prop('checked', true);
    }else{
        $('#invoice_infor_setting input[type="checkbox"]').prop('checked', false);
        $('#invoice_infor_setting').addClass('hide');
    }
}

function sender_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('#'+$(el).data('div-name')).removeClass('hide');
    }else{
        $('#'+$(el).data('div-name')).addClass('hide');
    }
}

function receiver_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('#'+$(el).data('div-name')).removeClass('hide');
    }else{
        $('#'+$(el).data('div-name')).addClass('hide');
    }
}

function sender_infor_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('input[name="sender_receiver[sender_infor][show_on_column]"]').attr('required', true);
        $('input[name="sender_receiver[sender_infor][column_color]"]').attr('required', true);
        $('#sender_infor_add_field_setting').removeClass('hide');
        $('#sender_infor_setting').removeClass('hide');
        $('#sender_infor_add_field_btn').removeClass('hide');
    }else{
       $('input[name="sender_receiver[sender_infor][show_on_column]"]').removeAttr('required');
       $('input[name="sender_receiver[sender_infor][column_color]"]').removeAttr('required');
       $('#sender_infor_add_field_setting').addClass('hide');
       $('#sender_infor_setting').addClass('hide');
       $('#sender_name_setting').addClass('hide');
       $('#sender_phone_setting').addClass('hide');
       $('#sender_email_setting').addClass('hide');
       $('#sender_website_setting').addClass('hide');
       $('#sender_street_setting').addClass('hide');
       $('#sender_city_setting').addClass('hide');
       $('#sender_state_setting').addClass('hide');
       $('#sender_zipcode_setting').addClass('hide');
       $('#sender_country_setting').addClass('hide');
       $('#sender_vat_setting').addClass('hide');
       $('#sender_sales_person_setting').addClass('hide');
   }
}

function receiver_infor_setting(el){
    "use strict";
    if($(el).is(':checked')){
      $('input[name="sender_receiver[receiver_infor][show_on_column]"]').attr('required', true);
      $('input[name="sender_receiver[receiver_infor][column_color]"]').attr('required', true);
      $('#receiver_infor_add_field_setting').removeClass('hide');
      $('#receiver_infor_setting').removeClass('hide');
      $('#receiver_infor_add_field_btn').removeClass('hide');
  }else{
      $('input[name="sender_receiver[receiver_infor][show_on_column]"]').removeAttr('required');
      $('input[name="sender_receiver[receiver_infor][column_color]"]').removeAttr('required');
      $('#receiver_infor_add_field_setting').addClass('hide');
      $('#receiver_infor_setting').addClass('hide');
      $('#receiver_name_setting').addClass('hide');
      $('#receiver_phone_setting').addClass('hide');
      $('#receiver_email_setting').addClass('hide');
      $('#receiver_website_setting').addClass('hide');
      $('#receiver_street_setting').addClass('hide');
      $('#receiver_city_setting').addClass('hide');
      $('#receiver_state_setting').addClass('hide');
      $('#receiver_zipcode_setting').addClass('hide');
      $('#receiver_country_setting').addClass('hide');
      $('#receiver_vat_setting').addClass('hide');
  }
}

function striped_row_option(el){
    "use strict";
    if($(el).is(':checked')){
        $('#'+$(el).data('div-name')).removeClass('hide');
    }else{
        $('#'+$(el).data('div-name')).addClass('hide');
    }
}

function column_setting(el){
    "use strict";
    if($(el).is(':checked')){
        $('button[id="'+$(el).data('btn-name')+'"]').removeClass('hide');
    }else{
        $('#'+$(el).data('div-name')).addClass('hide');
        $('ul[id="'+$(el).data('div-name')+'"]').addClass('hide');
        $('button[id="'+$(el).data('btn-name')+'"]').addClass('hide');
    }
}

function close_setting(el){
    "use strict";
    $('ul[id="'+$(el).data('dismiss')+'"]').addClass('hide');
}

function edit_setting(el){
    "use strict";
    var name = $(el).data('div-name');
    show_popup('ul[id="'+name+'"]');
}

function sender_infor_add_field(){
    "use strict";
    show_popup('#sender_add_field_setting');
}

function receiver_infor_add_field(){
    "use strict";
    show_popup('#receiver_add_field_setting');
}
function header_invoice_info_add_field(){
    "use strict";
    show_popup('#header_invoice_add_field_setting');
}
function add_header_invoice_field(el){
    "use strict";
    var select_available = $('#header_invoice_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_header_invoice_error_formula').addClass('hide').text('');
    var formula = '';
    var field_name = $('#cf_header_invoice_field_name').val();
    var field_label = $('#cf_header_invoice_field_label').val();
    var item_id = $('#cf_header_invoice_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_header_invoice_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'header[invoice_infor]', '4', '#header_invoice_setting', '#header_invoice_add_field_setting', el);
        }
    }
    else{
        if(item_id == ''){
            $('#cf_header_invoice_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'header[invoice_infor]', '4', '#header_invoice_setting', '#header_invoice_add_field_setting', el);
        }
    }
}
function add_sender_field(el){
    "use strict";
    var select_available = $('#sender_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_sender_error_formula').addClass('hide').text('');
    var formula = '';
    var field_name = $('#cf_sender_field_name').val();
    var field_label = $('#cf_sender_field_label').val();
    var item_id = $('#cf_sender_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_sender_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'sender_receiver[sender_infor]', '4', '#sender_infor_setting', '#sender_add_field_setting', el);
        }
    }
    else{
        if(item_id == ''){
            $('#cf_sender_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'sender_receiver[sender_infor]', '4', '#sender_infor_setting', '#sender_add_field_setting', el);
        }
    }
}

function add_receiver_field(el){
 "use strict";
 var select_available = $('#receiver_add_field_setting [name="select_an_available_field"]').is(':checked');
 $('#cf_receiver_error_formula').addClass('hide').text('');
 var formula = '';
 var field_name = $('#cf_receiver_field_name').val();
 var field_label = $('#cf_receiver_field_label').val();
 var item_id = $('#cf_receiver_field_available').val();
 if(select_available == false){
    if(field_name == '' || field_label == ''){
        $('#cf_receiver_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
        return false;
    }
    else{
        add_customfield(select_available, item_id, field_name, field_label, formula, '', 'sender_receiver[receiver_infor]', '4', '#receiver_infor_setting', '#receiver_add_field_setting', el);
    }
}
else{
    if(item_id == ''){
        $('#cf_receiver_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
        return false;
    }
    else{
        add_customfield(select_available, item_id, field_name, field_label, formula, '', 'sender_receiver[receiver_infor]', '4', '#receiver_infor_setting', '#receiver_add_field_setting', el);
    }
}
}

function item_table_add_field(){
    "use strict";
    show_popup('#item_table_add_field_setting');
}

function add_item_table_field(el){
    "use strict";
    var select_available = $('#item_table_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_item_table_error_formula').addClass('hide').text('');

    var formula = $('#cf_item_table_field_formula').val();
    var value = $('#cf_item_table_field_value').val();
    var field_name = $('#cf_item_table_field_name').val();
    var field_label = $('#cf_item_table_field_label').val();
    var item_id = $('#cf_item_table_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_item_table_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        if(formula != ''){
         $(el).prop( "disabled", true );
         var data = {};
         data.formula = formula;
         data.type = 'item_table';
         $.get(admin_url + 'invoices_builder/check_fomula', data).done(function (response) {  
            if(response == false){
               $(el).prop( "disabled", false );
               $('#cf_item_table_error_formula').removeClass('hide').text('<?php echo _l('ib_formula_not_valid'); ?>');
               return false;
           }
           else{
            add_customfield(select_available, item_id, field_name, field_label, formula, value, 'item_table', '6', '#item_table_field', '#item_table_add_field_setting', el);
        }
    });
     }
     else{
        add_customfield(select_available, item_id, field_name, field_label, formula, value, 'item_table', '6', '#item_table_field', '#item_table_add_field_setting', el);            
    }
}
else{
    if(item_id == ''){
        $('#cf_item_table_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
        return false;
    }
    else{
        add_customfield(select_available, item_id, field_name, field_label, formula, value, 'item_table', '6', '#item_table_field', '#item_table_add_field_setting', el);
    }
}
}

function invoice_total_add_field(){
    "use strict";
    show_popup('#invoice_total_add_field_setting');
}

function show_popup(name){
  "use strict";
  var popup = $(name);
  $(document).mouseup(function(e) 
  {
    if (!popup.is(e.target) && popup.has(e.target).length === 0) 
    {
        popup.addClass("hide");
    }
});
  if(popup.hasClass('hide')){
    popup.find('[name="select_an_available_field"]').prop('checked',false).trigger('change');

    popup.removeClass('hide');
}else{
    popup.addClass('hide');
}
}

function add_invoice_total_field(el){
    "use strict";
    var select_available = $('#invoice_total_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_invoice_total_error_formula').addClass('hide').text('');

    var formula = $('#cf_invoice_total_field_formula').val();
    var value = $('#cf_invoice_total_field_value').val();
    var field_name = $('#cf_invoice_total_field_name').val();
    var field_label = $('#cf_invoice_total_field_label').val();
    var item_id = $('#cf_invoice_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_invoice_total_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        if(formula != ''){
         $(el).prop( "disabled", true );
         var data = {};
         data.formula = formula;
         data.type = 'invoice_total';
         $.get(admin_url + 'invoices_builder/check_fomula', data).done(function (response) {  
            if(response == false){
               $(el).prop( "disabled", false );
               $('#cf_invoice_total_error_formula').removeClass('hide').text('<?php echo _l('ib_formula_not_valid'); ?>');
               return false;
           }
           else{
            add_customfield(select_available, item_id, field_name, field_label, formula, value, 'invoice_total', '6', '#invoice_total_field_setting', '#invoice_total_add_field_setting', el);
        }
    });
     }
     else{
        add_customfield(select_available, item_id, field_name, field_label, formula, value, 'invoice_total', '6', '#invoice_total_field_setting', '#invoice_total_add_field_setting', el);        
    }
}
else{
    if(item_id == ''){
        $('#cf_invoice_total_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
        return false;
    }
    else{
        add_customfield(select_available, item_id, field_name, field_label, formula, value, 'invoice_total', '6', '#invoice_total_field_setting', '#invoice_total_add_field_setting', el);
    }
}
}



function add_customfield(select_available, item_id, field_name, field_label, formula, value, type, col_md, append_element, popup_element, el){
  "use strict";
  var check_name = false;
  var check_label = false;
  var data1 = {};
  data1.type = type;
  data1.check_name_value = field_name;
  data1.check_label_value = field_label;
  $(el).prop( "disabled", true );
  $(popup_element+' .error_add_customfield').addClass('hide').text('');
  $.post(admin_url + 'invoices_builder/check_duplicate_field', data1).done(function (response) {  
    response = JSON.parse(response); 
    $(el).prop( "disabled", false );
    var has_error = false;
    if(response.name_result == true || response.name_result == 'true'){
        $(popup_element+' .error_add_customfield').removeClass('hide').text('<?php echo _l('ib_field_name_already_exists'); ?>');
        has_error = true;
        return false;
    }
    if(response.label_result == true || response.label_result == 'true'){
        $(popup_element+' .error_add_customfield').removeClass('hide').text('<?php echo _l('ib_field_label_already_exists'); ?>');
        has_error = true;
        return false;
    }

    if(!has_error){
      var data = {};
      data.select_available = select_available;
      data.item_id = item_id;
      data.field_name = field_name;
      data.field_label = field_label;
      data.formula = formula;
      data.value = value;
      data.type = type;
      data.col_md = col_md;
      $(el).prop( "disabled", true );
      $.post(admin_url + 'invoices_builder/add_field', data).done(function (response) {  
        response = JSON.parse(response);
        if(response.success == true || response.success == 'true'){
            $(append_element).append(response.html);
            $(popup_element).addClass('hide');
            $('select.field_formula.add_expression_slug.'+type).html(response.expression_slug_option);
            $('select.field_formula.add_expression_slug.'+type).selectpicker('refresh');
            init_color_pickers();
            trigger_order_column();
            add_field_to_preview(response.preview_html, type);
            init_selectpicker();
        }else{
            set_alert('warning', '<?php echo _l('ib_add_field_fail'); ?>');
        }
        $(el).prop( "disabled", false );
    });
  }
});
}

function bottom_add_field(){
    "use strict";
    show_popup('#bottom_add_field_setting');
}

function add_bottom_field(el){
    "use strict";
    var select_available = $('#bottom_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_bottom_error_formula').addClass('hide').text('');
    var formula = '';
    var field_name = $('#cf_bottom_field_name').val();
    var field_label = $('#cf_bottom_field_label').val();
    var item_id = $('#cf_bottom_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_bottom_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'bottom_information', '4', '#bottom_field_setting', '#bottom_add_field_setting', el);
        }
    }
    else{
        if(item_id == ''){
            $('#cf_bottom_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'bottom_information', '4', '#bottom_field_setting', '#bottom_add_field_setting', el);
        }
    }
}

function footer_add_field(){
    "use strict";
    show_popup('#footer_add_field_setting');
}

function add_footer_field(el){
    "use strict";
    var select_available = $('#footer_add_field_setting [name="select_an_available_field"]').is(':checked');
    $('#cf_footer_error_formula').addClass('hide').text('');
    var formula = '';
    var field_name = $('#cf_footer_field_name').val();
    var field_label = $('#cf_footer_field_label').val();
    var item_id = $('#cf_footer_field_available').val();
    if(select_available == false){
        if(field_name == '' || field_label == ''){
            $('#cf_footer_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'footer', '4', '#footer_field_setting', '#footer_add_field_setting', el);
        }
    }
    else{
        if(item_id == ''){
            $('#cf_footer_error_formula').removeClass('hide').text('<?php echo _l('ib_please_enter_full_information'); ?>');
            return false;
        }
        else{
            add_customfield(select_available, item_id, field_name, field_label, formula, '', 'footer', '4', '#footer_field_setting', '#footer_add_field_setting', el);
        }
    }
}

function remove_setting(el){
    "use strict";
    if(confirm("<?php echo _l('ib_are_you_sure_you_want_to_delete_this'); ?>")){
        var obj = $(el);
        obj.closest('.custom_field_item').remove();
        var type = obj.data('type');
        var id = obj.data('id');
        if(type == 'header[invoice_infor]'){
            $('.header_panel .invoice_infor .control-element-2.'+id).remove();
        }
        if(type == 'sender_receiver[sender_infor]'){
            $('.sender_receiver_panel .sender_infor .control-element-2.'+id).remove();
        }
        if(type == 'sender_receiver[receiver_infor]'){
            $('.sender_receiver_panel .receiver_infor .control-element-2.'+id).remove();
        }
        if(type == 'item_table'){
            $('.item_table_panel .control-element.'+id).remove();
        }
        if(type == 'invoice_total'){
            $('.invoice_total_panel .flex-item .control-element.'+id).remove();
        }
        if(type == 'bottom_information'){
            $('.bottom_information_panel .flex-item .control-element.'+id).remove();
        }
        if(type == 'footer'){
            $('.footer_panel .flex-item .control-element.'+id).remove();
        }
    }
    else{
        return false;
    }
}

function change_preview(name){
  "use strict";
  var obj = $('[name="'+name+'"]');
  var parent = obj.closest('.panel-info');
  var panel_name = parent.data('panel');
  switch(panel_name){
    case 'general_setting_panel':
    general_setting_panel_render_preview();
    break;
    case 'header_panel':
    header_panel_render_preview(name);
    break;
    case 'sender_receiver_panel':
    sender_receiver_panel_render_preview();
    break;
    case 'item_table_panel':
    item_table_panel_render_preview();
    calculation_all_formula();
    break;
    case 'invoice_total_panel':
    invoice_total_panel_render_preview();
    calculation_all_formula();
    break;
    case 'bottom_information_panel':
    bottom_information_panel_render_preview();
    break;
    case 'footer_panel':
    footer_panel_render_preview();
    break;
}
init_page_rotation();
order_item_by_column();
}
async function general_setting_panel_render_preview(){
  "use strict";
  var row_color = $('[name="background_color"]').val();
  var page_rotation = $('[name="page_rotation"]').val();
  $('.preview-form .page').css('background-color', row_color);
  init_page_rotation();
}
async function header_panel_render_preview(control_name){
  "use strict";
  var row_color = $('[name="header[row_color]"]').val();
  var column_number = $('[name="header[col_number]"]').val();
  var panel = $('.header_panel');
  var preview_panel = $('.preview-form .header_panel');

  preview_panel.css('background-color', row_color);
  var checkbox_row_list = panel.find('.checkbox_row');
  preview_panel.find('.control-element').addClass('d-none');
  $.each(checkbox_row_list, function () {
    var obj = $(this);
    var checkbox = obj.find('input[type="checkbox"]:eq(0)');
    if(checkbox.is(':checked')){
        var parent_type = checkbox.attr('name').split(/[[\]]+/)[1]; 
        var row_control = preview_panel.find('.control-element.'+parent_type);
        row_control.removeClass('d-none');
        var input_list = obj.find('input[required="required"], select[required="required"]');
            // Re style
            var style_data = get_style_string(input_list, 2, column_number);
            if(style_data.second_style != ''){
                row_control.find('img').attr('style',style_data.second_style);
                row_control.find('img').css('height','auto');
            }
            if(style_data.main_style != ''){
                row_control.attr('style',style_data.main_style);
            }
            // End re style
            var checkbox_list = obj.find('input[type="checkbox"]');
            var i;
            for(i = 0; i < checkbox_list.length; i++){
                var checkbox_obj = checkbox_list.eq(i);
                var checkbox_info = get_object_info(checkbox_obj, 2);  
                var row_element2_control = preview_panel.find('.control-element-2.'+checkbox_info.name);
                if(checkbox_info.type == 'custom_field'){
                    var field_value = $('input[name="'+checkbox_obj.attr('name').replace('[active]', '')+'[value]"]').val();
                    row_element2_control.text(field_value);
                }
                if(checkbox_obj.is(':checked')){
                    row_element2_control.removeClass('d-none');
                }
                else{
                    row_element2_control.addClass('d-none');
                }
            }

            // Update sort column
            var i;
            for(i = 0; i < input_list.length; i++){
                var input_obj = input_list.eq(i);
                var arr_name = input_obj.attr('name').split(/[[\]]+/);
                var child_name = arr_name[2]; 
                if(child_name == 'show_on_column'){
                    var row_control = preview_panel.find('.control-element.'+arr_name[1]);
                    var value = input_obj.val(); 
                    if(parseFloat(value) > parseFloat(column_number)){
                        value = column_number;
                    }
                    if(value == '' || parseFloat(value).toFixed(0) <= 0){
                        value = 1;
                    }
                    row_control.attr('data-sort', value);
                    input_obj.val(value);
                    break;
                }
            }
            // End update sort column
        }
    });
  sort_item(preview_panel, column_number);
  return false;
}
async function sender_receiver_panel_render_preview(){
  "use strict";
  var row_color = $('[name="sender_receiver[row_color]"]').val();
  var column_number = $('[name="sender_receiver[col_number]').val();
  var panel = $('.sender_receiver_panel');
  var preview_panel = $('.preview-form .sender_receiver_panel');
  preview_panel.css('background-color', row_color);
  preview_panel.find('.control-element').addClass('d-none');
  preview_panel.find('.control-element-2').addClass('d-none');
  var sender_receiver_row_item_list = panel.find('.sender_receiver_row_item');
  $.each(sender_receiver_row_item_list, function () {
    var obj = $(this);
    var checkbox = obj.find('input[type="checkbox"]:eq(0)');
    if(checkbox.is(':checked')){
        var parent_type = get_object_name(checkbox, 1);
        var column_color = obj.find('.colorpicker-input input').val();
        var row_control = preview_panel.find('.control-element.'+parent_type);
        row_control.removeClass('d-none').attr('style', 'background-color:'+column_color);
            // Hide show by checkbox
            var checkbox_row_list = obj.find('.checkbox_row');
            var i;
            for(i = 0; i < checkbox_row_list.length; i++){
                var checkbox_row_obj = checkbox_row_list.eq(i);
                var checkbox_obj = checkbox_row_obj.find('input[type="checkbox"]:eq(0)');
                var checkbox_type = get_object_name(checkbox_obj, 2);
                var row_element2_control = preview_panel.find('.control-element-2.'+parent_type+'.'+checkbox_type);
                if(checkbox_obj.is(':checked')){
                    var input_list = checkbox_row_obj.find('input[required="required"], select[required="required"], .custom_field_value, .custom_field_formula');
                    var style_data = get_style_string(input_list, 3, column_number);
                    // Re style 
                    if(style_data.main_style != ''){
                        row_element2_control.attr('style',style_data.main_style);
                    }
                    // Set value
                    if(style_data.value != ''){
                        row_element2_control.text(style_data.value);
                    }
                    row_element2_control.removeClass('d-none');
                }
                else{
                    row_element2_control.addClass('d-none');
                }
            }
            // End hide show by checkbox
            // Update sort column
            var show_on_column_obj = obj.find('[name="sender_receiver['+parent_type+'][show_on_column]"]');
            var value = show_on_column_obj.val();
            value = adjust_value(value, column_number);
            preview_panel.find('.control-element.'+parent_type).attr('data-sort', value);
            show_on_column_obj.val(value);
            // End update sort column
        }
    });
  sort_item(preview_panel, column_number);
  return false;
}
async function item_table_panel_render_preview(){
    "use strict";
    var row_color = $('[name="item_table[thead_color]"]').val();
    var text_align = $('[name="item_table[text_align]"]').val();
    var font_style = $('[name="item_table[font_style]"]').val();
    var font_size = $('[name="item_table[font_size]"]').val();
    var color = $('[name="item_table[text_color]"]').val();
    var panel = $('.item_table_panel');
    var preview_panel_list = $('.preview-form .item_table_panel');
    panel.find('thead').css('background-color', row_color);
    panel.find('thead th').css('text-align', text_align);
    panel.find('thead th').css('font-style', font_style);
    panel.find('thead th').css('font-size', font_size+'px');
    panel.find('thead th').css('color', color);
    let i = 0;
    for(i = 0; i < preview_panel_list.length; i++){
      var preview_panel = preview_panel_list.eq(i);
      var table_obj = preview_panel.find('table');
      table_obj.find('.control-element').addClass('d-none');
      if(panel.find('input[name="item_table[striped_row][active]"]').is(':checked')){
        table_obj.find('tbody tr').eq(0).attr('style', 'background-color:'+panel.find('input[name="item_table[striped_row][odd_row_color]"]').val());
        table_obj.find('tbody tr').eq(1).attr('style', 'background-color:'+panel.find('input[name="item_table[striped_row][even_row_color]"]').val());
    }
    else{
        table_obj.find('tbody tr').removeAttr('style');
    }
    var item_table_checkbox_row_list = panel.find('#item_table_field .checkbox_row');
    $.each(item_table_checkbox_row_list, function () {
        var obj = $(this);
        var checkbox = obj.find('input[type="checkbox"]:eq(0)');
            //Show column
            if(checkbox.is(':checked')){
             var parent_type = get_object_name(checkbox, 1);
             var row_control = table_obj.find('.control-element.'+parent_type);
             row_control.removeClass('d-none');
             var row_control_data = table_obj.find('td.control-element.'+parent_type);
                // Re style
                var input_list = obj.find('input[required="required"], select[required="required"], .custom_field_value, .custom_field_formula');
                var style_data = get_style_string(input_list, 2, 1000);
                if(style_data.second_style != ''){
                    row_control_data.find('img').attr('style',style_data.second_style);
                }
                if(style_data.main_style != ''){
                    row_control_data.attr('style',style_data.main_style);
                }
                // Update sort column
                if(style_data.sort != ''){
                    row_control.attr('data-sort',style_data.sort);
                }
                // Update value
                if(style_data.value != '' || style_data.formula != ''){
                    add_value(row_control_data, style_data.value, style_data.formula);
                }
            }
        });
    $.each(table_obj, function () {
        var obj = $(this);
        var sort_header = obj.find('th.control-element');
        sort_column(sort_header);
        var sort_row1 = obj.find('tbody tr').eq(0).find('td.control-element');
        sort_column(sort_row1);
        var sort_row2 = obj.find('tbody tr').eq(1).find('td.control-element');
        sort_column(sort_row2);
    });
}
return false;
}
async function invoice_total_panel_render_preview(){
  "use strict";
  var row_color = $('[name="invoice_total[row_color]"]').val();
  var column_number = $('[name="invoice_total[col_number]').val();
  var panel = $('.invoice_total_panel');
  var invoice_total_row_item_list = panel.find('.checkbox_row');
  var preview_panel_list = $('.preview-form .invoice_total_panel');
  let i = 0;
  for(i = 0; i < preview_panel_list.length; i++){
    var preview_panel = preview_panel_list.eq(i);
    preview_panel.css('background-color', row_color);
    preview_panel.find('.control-element').addClass('d-none');
    $.each(invoice_total_row_item_list, function () {
        var obj = $(this);
        var checkbox = obj.find('input[type="checkbox"]:eq(0)');
            // Hide show by checkbox
            if(checkbox.is(':checked')){
                var parent_type = get_object_name(checkbox, 1);
                var row_control = preview_panel.find('.control-element.'+parent_type);
                row_control.removeClass('d-none');
                var input_list = obj.find('input[required="required"], select[required="required"], .custom_field_value, .custom_field_formula');
                var style_data = get_style_string(input_list, 2, column_number);
                // Re style 
                if(style_data.main_style != ''){
                    row_control.attr('style',style_data.main_style);
                }
                // Update sort column
                if(style_data.sort != ''){
                    row_control.attr('data-sort',style_data.sort);
                }
                // Update value
                if(style_data.value != '' || style_data.formula != ''){
                    add_value(row_control, style_data.value, style_data.formula);
                }
            }
            // End hide show by checkbox
        });
    sort_item(preview_panel, column_number);
}
return false;
}
async function bottom_information_panel_render_preview(){
  "use strict";
  var row_color = $('[name="bottom_information[row_color]"]').val();
  var column_number = $('[name="bottom_information[col_number]').val();
  var panel = $('.bottom_information_panel');
  panel.css('background-color', row_color);
  var preview_panel = $('.preview-form .bottom_information_panel');
  var bottom_information_row_item_list = panel.find('.checkbox_row');
  panel.find('.control-element').addClass('d-none');
  $.each(bottom_information_row_item_list, function () {
    var obj = $(this);
    var checkbox = obj.find('input[type="checkbox"]:eq(0)');
            // Hide show by checkbox
            if(checkbox.is(':checked')){
                var parent_type = get_object_name(checkbox, 1);
                var row_control = preview_panel.find('.control-element.'+parent_type);
                row_control.removeClass('d-none');
                // Re style 
                var input_list = obj.find('input[required="required"], select[required="required"], .custom_field_value, .custom_field_formula');
                var style_data = get_style_string(input_list, 2, column_number);
                // Re style 
                if(style_data.second_style != ''){
                    row_control.find('img, .signature').attr('style',style_data.second_style);
                }
                if(style_data.main_style != ''){
                    row_control.attr('style',style_data.main_style);
                }
                // Update sort column
                if(style_data.sort != ''){
                    row_control.attr('data-sort',style_data.sort);
                }
                // Update value
                if(style_data.value != ''){
                    row_control.find('span').text(style_data.value);
                }
            }
        });
  sort_item(preview_panel, column_number);
  return false;
}
async function footer_panel_render_preview(){
  "use strict";
  var row_color = $('[name="footer[row_color]"]').val();
  var column_number = $('[name="footer[col_number]').val();
  var panel = $('.footer_panel');
  panel.css('background-color', row_color);
  var preview_panel = $('.preview-form .footer_panel');
  var footer_row_item_list = panel.find('.checkbox_row');
  preview_panel.find('.control-element').addClass('d-none');
  $.each(footer_row_item_list, function () {
    var obj = $(this);
    var checkbox = obj.find('input[type="checkbox"]:eq(0)');
            // Hide show by checkbox
            if(checkbox.is(':checked')){
                var parent_type = get_object_name(checkbox, 1);
                var row_control = preview_panel.find('.control-element.'+parent_type);
                row_control.removeClass('d-none');
                // Re style 
                var input_list = obj.find('input[required="required"], select[required="required"], .custom_field_value, .custom_field_formula');
                var style_data = get_style_string(input_list, 2, column_number);
                // Re style 
                if(style_data.second_style != ''){
                    row_control.find('img, .signature').attr('style',style_data.second_style);
                }
                if(style_data.main_style != ''){
                    row_control.attr('style',style_data.main_style);
                }
                // Update sort column
                if(style_data.sort != ''){
                    row_control.attr('data-sort',style_data.sort);
                }
                // Update value
                if(style_data.value != ''){
                    row_control.find('span').text(style_data.value);
                }
            }
            // End hide show by checkbox
        });
  sort_item(preview_panel, column_number);
  return false;
}
function convert_style_string(type, value){
  "use strict";
  let main_style = '';
  let style = '';
  let sort = '';
  let values = '';
  let formula = '';
  if(value != ''){
    switch(type){
        case 'width':
        style = 'width:'+value+'px';
        break;
        case 'height':
        style = 'height:'+value+'px';
        break;
        case 'text_color':
        main_style = 'color:'+value;
        break;
        case 'font_size':
        main_style = 'font-size:'+value+'px';
        break;
        case 'font_style':
        main_style = 'font-style:'+value;
        break;
        case 'text_align':
        main_style = 'text-align:'+value+';justify-content:'+value;
        break;
        case 'order_column':
        sort = value;
        break;
        case 'value':
        values = value;
        break;
        case 'formula':
        formula = value;
        break;
        case 'label_content':
        values = value;
        break;
        case 'content':
        values = value;
        break;
    }
}
if(type == 'order_column' && value == ''){
    sort = 1;
}
return {'main_style': main_style, 'style': style, 'sort': sort, 'value': values, 'formula': formula};
}

function get_style_string(input_list, name_index, column_number){
  "use strict";
  let second_style = '';
  let main_style = '';
  let sort = '';
  let value = '';
  let formula = '';
  var i;
  for(i = 0; i < input_list.length; i++){
    var input_obj = input_list.eq(i);
    var child_name = get_object_name(input_obj, name_index); 
    var input_value = input_obj.val(); 
    var style_data = convert_style_string(child_name, input_value);
    if(style_data.main_style != ''){
        main_style += style_data.main_style+';';
    }
    if(style_data.style != ''){
        second_style += style_data.style+';';
    }
    if(style_data.sort != ''){
        sort = style_data.sort;
        if(parseFloat(sort) > parseFloat(column_number)){
            sort = column_number;
        }
        if(input_value == '' || parseFloat(input_value).toFixed(0) <= 0){
            sort = 1;
        }
        input_obj.val(sort);
    }
    if(style_data.value != ''){
        value = style_data.value;
    }
    if(style_data.formula != ''){
        formula = style_data.formula;
    }
}
return {'main_style': main_style, 'second_style': second_style, 'value': value, 'sort': sort, 'formula': formula};
}


function sort_column(self) {
    "use strict";
    self.sort(function(lhs, rhs){
      return parseInt($(lhs).attr("data-sort"),10) - parseInt($(rhs).attr("data-sort"),10);
  }).appendTo(self.parent());
};

function check_form(){
   "use strict";
   var has_error = false;
   var first_error_obj = '';
   if(!$("#form_add_edit_template").valid()){
        var obj_list = $('.form-group.has-error');
        var error_length = obj_list.length;
        console.log(error_length);
        if(error_length > 0){
            has_error = true;
            first_error_obj = obj_list.eq(0);
            $('html,body').animate({scrollTop: first_error_obj.offset().top-50},'slow');
        }
   }
   var checkbox_row = $('.checkbox_row');
   $('.checkbox_row .checkbox').find('label').removeClass('text-danger').removeAttr('data-toggle');
   $.each(checkbox_row, function () {
    var obj = $(this);
    if(obj.find('.checkbox input[type="checkbox"]').is(':checked')){
        var control_name = '';
        var message = '<?php echo _l('ib_please_enter_full_information') ?>';
        var show_error = false;
        var input_list =  obj.find('input[required="required"], select[required="required"]'); 
        let i;
        for(i = 0; i < input_list.length; i++){
            var input_obj = input_list.eq(i);
            if(input_obj.val() == ''){
                list_id.push(input_obj.attr('name'));
                show_error = true;
            }
        }
        if(show_error == false){
            var formula_error = obj.find('.formula-error');
            let j;
            for(j = 0; j < formula_error.length; j++){
                var input_obj = formula_error.eq(j);
                if(!input_obj.hasClass('hide')){
                    var textarea_obj = input_obj.parent().find('textarea');
                    list_id.push(textarea_obj.attr('name'));
                    show_error = true;
                    var message = '<?php echo _l('ib_formula_not_valid') ?>';
                }
            }
        }
        if(show_error == false){
            var order_column = obj.find('.order_column');
            let l;
            for(l = 0; l < order_column.length; l++){
                var order_obj = order_column.eq(l);
                var max_value = order_obj.attr('max');
                var current_value = order_obj.val();
                if(current_value != '' && (parseFloat(current_value) > parseFloat(max_value))){
                    list_id.push(order_obj.attr('name'));
                    show_error = true;
                    var message = '<?php echo _l('ib_value_must_be_less_than_or_equal_to') ?> '+max_value;
                    break;
                }
            }
        }

            if (show_error) {
                if(first_error_obj == ''){
                    first_error_obj = obj;
                    // Scroll
                    $('html,body').animate({scrollTop: obj.offset().top-50},'slow');
                    has_error = true;
                }
                obj.find('.checkbox:eq(0)').find('label').addClass('text-danger').attr('data-toggle', 'tooltip').attr('data-placement', 'top').attr('data-original-title', message);
            }
        }
    });
   return has_error;
}

function sort_item(parent_list, max_column){
    "use strict";
    $.each(parent_list, function () {
        var parent = $(this);
        parent.find('.flex-item').addClass('d-none');
        var column_list = parent.find('.flex-item');
        var item_column_list = parent.find('.flex-item .control-element');
        if(column_list.length < 4){
            let i;
            for(i = 1; i <= (4 - column_list.length); i++){
                parent.append('<div class="flex4 flex-item d-none"></div>');
            }
        }
        $.each(column_list, function (i) {
            i += 1;
            var obj = $(this);
            obj.removeClass('d-none');
            // Sort item
            var j;
            for(j = 0; j < item_column_list.length; j++){
                var item_column = item_column_list.eq(j);
                var sort_index = item_column.data('sort'); 
                if(sort_index == i){
                    item_column.clone().appendTo(obj);
                    item_column.remove();
                }
            }
            if(i == max_column){
                return false;
            }
        });
    });
}

function get_object_name(input_obj, name_index){
    "use strict";
    var arr_name = input_obj.attr('name').split(/[[\]]+/);
    var child_name = arr_name[name_index]; 
    if($.isNumeric(child_name) || child_name == 'custom_field'){
        child_name = arr_name[name_index + 1];
    }
    return child_name;
}

function adjust_value(value, column_number){
    "use strict";
    if(parseFloat(value) > parseFloat(column_number)){
        value = column_number;
    }
    if(value == '' || parseFloat(value).toFixed(0) <= 0){
        value = 1;
    }
    return value;
}

function add_field_to_preview(preview_html, type){
    "use strict";
    if(type == 'header[invoice_infor]'){
        $('.control-element.invoice_infor').append(preview_html);
        header_panel_render_preview();
    }
    if(type == 'sender_receiver[sender_infor]'){
        $('.control-element.sender_infor').append(preview_html);
        sender_receiver_panel_render_preview();
    }
    if(type == 'sender_receiver[receiver_infor]'){
        $('.control-element.receiver_infor').append(preview_html);
        sender_receiver_panel_render_preview();
    }
    if(type == 'item_table'){
        var panel = $('.item_table_panel');
        var html_obj = $(preview_html);
        var id = html_obj.data('id');
        var label = html_obj.data('label');
        panel.find('table thead tr').append('<th class="text-nowrap control-element '+id+'" data-sort="1">'+label+'</th>');
        panel.find('table tbody tr').eq(0).append(preview_html.replace('data-row-index', 'data-row-index="1"'));
        panel.find('table tbody tr').eq(1).append(preview_html.replace('data-row-index', 'data-row-index="2"'));
        item_table_panel_render_preview();
    }
    if(type == 'invoice_total'){
        $('.invoice_total_panel .flex-item').eq(0).append(preview_html);
        invoice_total_panel_render_preview();
    }
    if(type == 'bottom_information'){
        $('.bottom_information_panel .flex-item').eq(0).append(preview_html);
        bottom_information_panel_render_preview();
    }
    if(type == 'footer'){
        $('.footer_panel .flex-item').eq(0).append(preview_html);
        footer_panel_render_preview();
    }
}
async function init_page_rotation(){
    "use strict";
    var page_list = $('.page');

    var page_obj = page_list.eq(0);
    if(page_width == 0){
        page_width = page_obj.width();
    }
    var page_content_height = page_obj.find('.page-content').height();
    var page_height = 0;
    if($('#page_rotation').val() == 'portrait'){
        page_height = page_width * 1.41428571429;
        page_obj.width(page_width);
    }
    else{
        let landcape_width = page_width * 2;
        page_height = landcape_width * 0.70707070707;  
        page_obj.width(landcape_width);
    }

    
}

function add_value(row_control, value, formula){
    "use strict";
    var hidden_input = row_control.find('input.hidden_customfield_value');
    if(hidden_input.length > 0){
        if(value != ''){
            formula = '';
            hidden_input.removeClass('has-formula');
        }
        else{
            if(formula != ''){
                hidden_input.addClass('has-formula');                
            }
        }
        row_control.find('span').text(value);
        hidden_input.val(value);
        hidden_input.attr('data-formula', formula);
    }
    else{
        row_control.text(value);
    }
}


function calculation_from_formula(new_hidden_list, formula){
    "use strict";
    var result = '';
    $.each(new_hidden_list, function (i, value) {
        formula = formula.replace(i, value); 
    });
    try { 
        result = eval(formula);
    }
    catch(err) {

    }
    return result;
}

function convert_hidden_list(row_custom_field, type){
    "use strict";
    var new_hidden_list = {};
    let j = 0;
    for(j = 0; j < row_custom_field.length; j++){
        var obj = row_custom_field.eq(j);
        // Element is active
        if(!obj.closest('.control-element').hasClass('d-none')){
            var name = obj.attr('name');
            var val = obj.val();
            if($.isNumeric(val) && val >= 0){
                new_hidden_list[name] = (new_hidden_list[name] || 0) + parseFloat(val);
            }
        }
    }
    if(type == 'item_table'){
        $.each(item_table_custom_field_value, function (key, value) {
            new_hidden_list[value['id']] = parseFloat(value['value']);
        });
    }
    if(type == 'invoice_total'){
     $.each(invoice_total_custom_field_value, function (key, value) {
        new_hidden_list[value['id']] = parseFloat(value['value']);
    });
 }
 return new_hidden_list;
}

function calculation_all_formula(){
    "use strict";
    var page_obj = $('.page').eq(0);
    // Calculate for item table
    var list_hidden_item_table = page_obj.find('.hidden_customfield_value.has-formula[data-type="item_table"]');
    $.each(list_hidden_item_table, function (i) {
        var obj = $(this);
        if(!obj.closest('.control-element').hasClass('d-none')){
            let row_index = obj.data('row-index');
            var row_custom_field = page_obj.find('.hidden_customfield_value[data-row-index="'+row_index+'"]');
            var formula = obj.attr('data-formula');
            var hidden_data_list = convert_hidden_list(row_custom_field, 'item_table');
            var result = calculation_from_formula(hidden_data_list, formula);
            if(result != ''){
                obj.val(result);
                obj.closest('.control-element').find('span').text(result);
            }
        }
    });

    //Calculate for invoice total
    var list_hidden_invoice = page_obj.find('.hidden_customfield_value.has-formula[data-type="invoice_total"]');
    $.each(list_hidden_invoice, function (i) {
        var obj = $(this);
        if(!obj.closest('.control-element').hasClass('d-none')){
            var row_custom_field = page_obj.find('.hidden_customfield_value');
            var formula = obj.attr('data-formula');
            var hidden_data_list = convert_hidden_list(row_custom_field, 'invoice_total');
            var result = calculation_from_formula(hidden_data_list, formula);
            if(result != ''){
                obj.val(result);
                obj.closest('.control-element').find('span').text(result);
            }
        }
    });
}
function htmlEncode(value){
    "use strict";
  return $('<textarea/>').text(value).html();
}
function htmlDecode(value){
    "use strict";
  return $('<textarea/>').html(value).text();
}
function get_object_info(input_obj, name_index){
    "use strict";
    var name = '';
    var type = 'normal';
    var arr_name = input_obj.attr('name').split(/[[\]]+/);
    var child_name = arr_name[name_index]; 
    if($.isNumeric(child_name) || child_name == 'custom_field'){
        child_name = arr_name[name_index + 1];
        type = 'custom_field';
    }
    return {'name' : child_name, 'type' : type};
}
async function order_item_by_column(){
    var flex_column = $('.flex4');
    $.each(flex_column, function (i) {
        var obj = $(this);
         obj.find("[data-order]")
         .sort((a,b) => $(a).data("order") - $(b).data("order"))
         .appendTo(obj);
    });
}
</script>