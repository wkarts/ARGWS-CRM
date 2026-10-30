
<div class="modal fade" id="multiple_companies_exist_contact_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">

    <div class="modal-dialog modal-md" role="document">

        <div class="modal-content" >

            <div class="modal-header">

                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

                <h4 class="modal-title">

                    <span class="edit-title"><?php echo _l('mc_existing_user_add_title')?></span>

                </h4>

            </div>

            <div class="modal-body">

                <div class="row">

                    <div class="col-md-12">
                        <p><?php echo _l('mc_existing_user_info_message')?></p>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-12">

                        <div class="form-group select-placeholder">

                            <label for="exist_contact_id" class="control-label"><?php echo _l('contact'); ?></label>

                            <select name="exist_contact_id[]" id="exist_contact_id" class="ajax-search"

                                    data-width="100%" data-live-search="true" multiple="multiple"

                                    data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >

                            </select>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>

                <a onclick="multiple_companies_save_contact( <?php echo $customer_id?> ); " class="btn btn-primary" id="btn_multiple_companies_save_contact"><?php echo _l('save'); ?></a>

            </div>


        </div>

    </div>

</div>


<script>

    $(document).ready(function (){


        $('#multiple_companies_exist_contact_modal').modal({

            show: true,

            backdrop: 'static'

        });


        init_ajax_search('multiple_contact', '#exist_contact_id.ajax-search', {

            mt_customer_id: <?php echo $customer_id?>

        });

    })

</script>
