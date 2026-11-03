
<script>

    $(document).ready(function (){


        $('#company').on('change', function() {

            var newCompanyName = $(this).val();

            if ( newCompanyName )
            {

                requestGet(admin_url+"multiple_companies/companies/check_company_name?company="+newCompanyName).done(function ( response ){

                    $('#company').parent('.form-group').after(response);

                });

            }

        });



    })

</script>
