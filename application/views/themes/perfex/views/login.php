<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mtop40">
    <div class="col-md-4 col-md-offset-4 text-center">
        <h1 class="tw-font-bold mbot20 login-heading">
            <?= _l((!empty($native_portal) ? $native_portal_options['allow_registration'] : get_option('allow_registration')) == 1 ? 'clients_login_heading_register' : 'clients_login_heading_no_register');
?>
        </h1>
    </div>
    <div class="col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2">
        <?= form_open($this->uri->uri_string(), ['class' => 'login-form']); ?>
        <?php if(!empty($native_portal)){echo form_hidden('issuer_id',$native_portal_issuer).form_hidden('_native_portal_csrf',$native_portal_csrf);}else hooks()->do_action('clients_login_form_start'); ?>
        <div class="panel_s">
            <div class="panel-body">

                <?php if (!empty($native_portal) ? $native_portal_options['disable_language']==='0' : !is_language_disabled()) { ?>
                <div class="form-group">
                    <label for="language" class="control-label">
                        <?= _l('language'); ?>
                    </label>
                    <select name="language" id="language" class="form-control selectpicker"
                        <?= empty($native_portal) ? 'onchange="change_contact_language(this)"' : ''; ?>
                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                        data-live-search="true">
                        <?php $selected = !empty($native_portal) ? $language : ((get_contact_language() != '') ? get_contact_language() : get_option('active_language')); ?>
                        <?php foreach ((!empty($native_portal)?$native_portal_languages:$this->app->get_available_languages()) as $availableLanguage) { ?>
                        <option value="<?= e($availableLanguage); ?>"
                            <?= ($availableLanguage == $selected) ? 'selected' : '' ?>>
                            <?= e(ucfirst($availableLanguage)); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>

                <div class="form-group">
                    <label
                        for="email"><?= _l('clients_login_email'); ?></label>
                    <input type="text" autofocus="true" class="form-control" name="email" id="email">
                    <?= form_error('email'); ?>
                </div>

                <div class="form-group">
                    <label
                        for="password"><?= _l('clients_login_password'); ?></label>
                    <input type="password" class="form-control" name="password" id="password">
                    <?= form_error('password'); ?>
                </div>

                <?php if (!empty($native_portal) ? $native_portal_options['use_recaptcha_customers_area']==='1' : show_recaptcha_in_customers_area()) { ?>
                <div class="g-recaptcha tw-mb-4"
                    data-sitekey="<?= e(!empty($native_portal)?$native_portal_options['recaptcha_site_key']:get_option('recaptcha_site_key')); ?>">
                </div>
                <?= form_error('g-recaptcha-response'); ?>
                <?php } ?>

                <div class="checkbox">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">
                        <?= _l('clients_login_remember'); ?>
                    </label>
                </div>

                <div class="form-group tw-mt-6">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= _l('clients_login_login_string'); ?>
                    </button>
                    <?php if ((!empty($native_portal) ? $native_portal_options['allow_registration'] : get_option('allow_registration')) == 1) { ?>
                    <a href="<?= !empty($native_portal) ? NativePortalPresentation::url('authentication/register') : site_url('authentication/register'); ?>"
                        class="btn btn-default btn-block">
                        <?= _l('clients_register_string'); ?>
                    </a>
                    <?php } ?>
                </div>
                <div class="tw-text-center">
                    <a href="<?= !empty($native_portal) ? NativePortalPresentation::url('authentication/forgot_password') : site_url('authentication/forgot_password'); ?>"
                        class="text-muted">
                        <?= _l('customer_forgot_password'); ?>
                    </a>
                </div>
                <?php if(empty($native_portal))hooks()->do_action('clients_login_form_end'); ?>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php if(!empty($native_portal)&&$native_portal_options['use_recaptcha_customers_area']==='1'){ ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php } ?>

<?php if(!empty($native_portal)&&$native_portal_options['disable_language']==='0'){ ?>
<script>(function(){var e=document.getElementById('language');if(!e)return;e.addEventListener('change',function(){var f=document.createElement('form');f.method='post';f.action=<?= json_encode(site_url('authentication/change_language/'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>+encodeURIComponent(e.value);var values={issuer_id:<?= json_encode((string)$native_portal_issuer); ?>,_native_portal_csrf:<?= json_encode($native_portal_csrf); ?>};Object.keys(values).forEach(function(k){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=values[k];f.appendChild(i);});document.body.appendChild(f);f.submit();});})();</script>
<?php } ?>
