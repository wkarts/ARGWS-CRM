<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12">
        <h4
            class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-text section-heading-verification-required">
            <?= _l('email_verification_required'); ?>
        </h4>
        <div class="panel_s">
            <div class="panel-body">
                <div class="alert alert-warning no-mbot verification-required-alert">
                    <h4 class="verification-required-heading">
                        <?= _l('email_verification_required_message'); ?>
                    </h4>
                    <p class="bold verification-required-message">
                        <?php if(empty($native_portal)){ ?><?= _l('email_verification_required_message_mail', site_url('verification/resend')); ?><?php }else{ ?><?= _l('email_verification_required_message'); ?><?php } ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if(!empty($native_portal)){ ?>
<div class="row"><div class="col-md-12">
<?php if(!empty($native_portal_message)){ ?><div class="alert alert-info"><?= e($native_portal_message); ?></div><?php } ?>
<?= form_open(NativePortalPresentation::url(($native_verification_action??'')==='verify'?'verification/verify/'.(int)$native_verification_contact.'/'.($native_verification_route_key??'native'):'verification/resend'),['id'=>'native-verification-form']); ?>
<?= form_hidden('issuer_id',(string)$native_portal_issuer); ?><?= form_hidden('_native_portal_csrf',$native_portal_csrf); ?>
<?php if(($native_verification_action??'')==='verify'){ ?>
<input type="hidden" name="native_verification_token" id="native-verification-token" value="<?= e($native_verification_legacy_token??''); ?>">
<?php } ?>
<button type="submit" class="btn btn-primary"><?= _l(($native_verification_action??'')==='verify'?'confirm':'send'); ?></button>
<?= form_close(); ?></div></div>
<script>(function(){var e=document.getElementById('native-verification-token');if(!e)return;var p=new URLSearchParams(location.hash.slice(1)),t=p.get('token');if(t&&/^[a-f0-9]{32}$/.test(t))e.value=t;if(location.hash)history.replaceState(null,'',location.pathname+location.search);})();</script>
<?php } ?>
