
(function($) {
    "use strict";

    $(document).ready(function (){

        $("body").on('show.bs.modal', '.modal', function (event) {

            if ( $(this).attr('id') == 'convert_lead_to_client_modal' )
            {

                setTimeout(function (){

                    multiple_companies_lead_convert()

                },10);

                setTimeout(function (){

                    multiple_companies_lead_convert()

                },1000);


                if ( $('#lead_to_client_form #email').length )
                {

                    requestGet(admin_url+"multiple_companies/companies/get_contact_companies?email_adress="+$('#lead_to_client_form #email').val()).done(function ( response ){

                        if( $('#mc_contact_companies').length )
                            $('#mc_contact_companies').remove();

                        $('input[name="email"]').parent('.form-group').after(response);

                    });

                    $('input[name="email"]').on('blur', function() {

                        var emailValue = $(this).val();

                        if ( emailValue )
                        {

                            requestGet(admin_url+"multiple_companies/companies/get_contact_companies?email_adress="+emailValue).done(function ( response ){

                                if( $('#mc_contact_companies').length )
                                    $('#mc_contact_companies').remove();

                                $('input[name="email"]').parent('.form-group').after(response);

                            });

                        }

                    });

                }

            }

        });


        /**
         * Add exist contact
         */

        var multiple_companies_page_url = window.location.href;

        if ( multiple_companies_page_url.includes("group=contacts") && multiple_companies_page_url.includes("admin/clients/client") )
        {

            if ( $('.new-contact-wrapper').length == 1 )
            {

                var _mc_exist_contact_button = '<a href="#" onclick="multiple_companies_exist_contact( customer_id ); return false;" class="btn btn-primary new-contact mbot15 "> <i class="fa-regular fa-plus tw-mr-1"></i>'
                                                    + mc_existing_user_lang +
                                                '</a>';


                $('.new-contact-wrapper').append( _mc_exist_contact_button );

            }

        }


    });


})(jQuery);


function multiple_companies_lead_convert()
{

    var rules_convert_lead = {

        firstname: "required",

        lastname: "required",

        password: {

            required: {

                depends: function (element) {

                    var sent_set_password = $('input[name="send_set_password_email"]');

                    if (sent_set_password.prop("checked") === false) {

                        return true;

                    }

                },

            },

        },

        email: {

            required: true,

            email: true,

        },

    };

    if (app.options.company_is_required == 1) {

        rules_convert_lead.company = "required";

    }

    appValidateForm($("#lead_to_client_form"), rules_convert_lead);

}


function multiple_companies_exist_contact( customer_id ) {


    requestGet('multiple_companies/companies/exist_contact/' + customer_id ).done(function(response) {

        $('#multiple_companies_div_content').html(response);


    }).fail(function(error) {

        var response = JSON.parse(error.responseText);

        alert_float('danger', response.message);

    });

}




function multiple_companies_save_contact( customer_id )
{

    if ( $('#exist_contact_id').val() && $('#exist_contact_id').val().length > 0 )
    {

        $('#btn_multiple_companies_save_contact').attr('disabled',true);

        $.post(admin_url+"multiple_companies/companies/save_contact", { customer_id:customer_id , contacts:$('#exist_contact_id').val() } ).done(function ( response ){

            response = JSON.parse( response );

            if ( response.success )
            {

                if ($.fn.DataTable.isDataTable('.table-contacts'))
                {
                    $('.table-contacts').DataTable().ajax.reload(null, false);
                }

                $('#multiple_companies_exist_contact_modal').modal('hide');

                alert_float( 'success' , response.message );

            }
            else
            {

                $('#btn_multiple_companies_save_contact').attr('disabled',false);

                alert_float( 'danger' , response.message );
            }

        })

    }


}

