
    <div class="row" id="mc_contact_companies" style="padding-left: 20px;  ">

        <?php if ( !empty( $email_exist ) ) { ?>
            <p class="text-danger bold"><?php echo _l('mc_email_exist_same_customer',$email_address)?></p>
        <?php } else if ( !empty( $companies ) && $contact_id == 0 ) { ?>
            <p class="text-danger bold"><?php echo _l('mc_email_exist_use_exist_option',$email_address)?></p>
        <?php } ?>


        <div class="col-md-12">

            <?php if ( !empty( $companies ) ) { ?>

                <h5 class="text-info"> <?php echo _l('mc_email_exist_customer_lists')?> </h5>

                <table style="margin-top:0px!important; border-left:5px solid #249f2d" class="table table-hover">

                    <thead>

                        <th><?php echo _l('clients_list_company')?></th>
                        <th><?php echo _l('contact')?></th>

                    </thead>

                    <tbody>

                        <?php foreach ( $companies as $company ) { ?>

                            <tr>

                                <td><?php echo $company->company?></td>
                                <td><?php echo $company->firstname.' '.$company->lastname?></td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

                <hr />

            <?php } ?>

        </div>

    </div>




    <script>

        $(document).ready(function (){

            <?php if ( !empty( $email_exist ) ) { ?>

                $('#contact-form').find('input[name="email"]').parent('.form-group').addClass('has-error');

                //$('#contact-form').find('input[name="email"]').val('');

            <?php } else { ?>

                $('#contact-form').find('input[name="email"]').parent('.form-group').removeClass('has-error');

            <?php } ?>

        })

    </script>


