<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="container">
    <div class="row">
        <div class="col-md-4 col-md-offset-4 mtop40 text-center">
            <h1 class="tw-font-semibold">
                <?= _l('customer_reset_password_heading'); ?>
            </h1>
            <div class="panel_s text-left">
                <div class="panel-body">
                    <?= form_open($this->uri->uri_string()); ?>
                    <?php if(!empty($native_portal))echo form_hidden('issuer_id',$native_portal_issuer).form_hidden('_native_portal_csrf',$native_portal_csrf).form_hidden('native_reset_token',$native_reset_token??''); ?>
                    <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>
                    <?php if ($this->session->flashdata('message-danger')) { ?>
                    <div class="alert alert-danger">
                        <?= $this->session->flashdata('message-danger'); ?>
                    </div>
                    <?php } ?>
                    <?= render_input('password', 'customer_reset_password', '', 'password'); ?>
                    <?= render_input('passwordr', 'customer_reset_password_repeat', '', 'password'); ?>
                    <div class="form-group">
                        <button type="submit"
                            class="btn btn-primary btn-block"><?= _l('customer_reset_action'); ?></button>
                    </div>
                    <?= form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if(!empty($native_portal)){ ?>
<script>
(function(){'use strict';var form=document.querySelector('#content form');if(!form)return;var field=form.querySelector('input[name="native_reset_token"]');if(!field)return;var fragment=new URLSearchParams(location.hash.slice(1)),token=fragment.get('token');if(token&&/^[a-f0-9]{64}$/.test(token)){field.value=token;history.replaceState(null,'',location.pathname+location.search);}})();
</script>
<?php } ?>
