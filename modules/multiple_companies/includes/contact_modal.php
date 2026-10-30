
<script>

    var mc_customer_id  = 0 ;
    var mc_contact_id   = 0 ;

    $(document).ready(function (){


        if ( $('#contact-form').find('input[name="email"]').length )
        {


            if( "undefined" != typeof customer_id )

                mc_customer_id = customer_id;

            mc_contact_id = $('input[name="contactid"]').val();

            $('input[name="email"]').on('blur', function() {

                var emailValue = $(this).val();

                if ( emailValue )
                {

                    requestGet(admin_url+"multiple_companies/companies/get_contact_companies/"+mc_customer_id+"/"+mc_contact_id+"?email_adress="+emailValue).done(function ( response ){

                        if( $('#mc_contact_companies').length )
                            $('#mc_contact_companies').remove();

                        $('input[name="email"]').parent('.form-group').after(response);

                    });

                }

            });


            if ( $('input[name="email"]').val() )
            {

                requestGet(admin_url+"multiple_companies/companies/get_contact_companies/"+mc_customer_id+"/"+mc_contact_id+"?email_adress="+$('#email').val()).done(function ( response ){

                    $('input[name="email"]').parent('.form-group').after(response);

                });

            }


            appValidateForm('#contact-form', {

                firstname: 'required',

                lastname: 'required',

                password: {

                    required: {

                        depends: function(element) {



                            var $sentSetPassword = $('input[name="send_set_password_email"]');



                            if ($('#contact input[name="contactid"]').val() == '' && $sentSetPassword.prop(

                                'checked') == false) {

                                return true;

                            }

                        }

                    }

                },

                email: {

                    required: true,

                    email: true,

                    remote: {

                        url: admin_url + "multiple_companies/companies/check_contact_email",

                        type: 'post',

                        data: {

                            email: function() {

                                return $('#contact-form input[name="email"]').val();

                            },

                            customer_id: mc_customer_id ,

                            contact_id: mc_contact_id ,

                        }

                    }

                }

            }, contactFormHandler);


        }

    })

</script>
