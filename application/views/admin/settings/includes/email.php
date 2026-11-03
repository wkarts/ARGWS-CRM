<?php defined('BASEPATH') or exit('No direct script access allowed');
$native_settings_email = $native_settings_email ?? null;
$nativeMail = isset($native_settings_email) && is_array($native_settings_email);
$mailValue = static function(string $key) use ($nativeMail, $native_settings_email): string {
    if (!$nativeMail) return (string)get_option($key);
    if (!array_key_exists($key, $native_settings_email['values'])) throw new DomainException('native_mail_settings_invalid');
    return $native_settings_email['values'][$key];
};
$mailSecret = fn(string $key): string => $nativeMail ? '' : (string)$this->encryption->decrypt($mailValue($key));
$mailYesNo = static function($key,$label,$tooltip) use($nativeMail,$mailValue): void {
    render_yes_no_option($key,$label,$tooltip,'','',null,null,$nativeMail?$mailValue($key):null);
};
?>
<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <li role="presentation" class="active">
                <a href="#email_config" aria-controls="email_config" role="tab"
                    data-toggle="tab"><?= _l('settings_smtp_settings_heading'); ?></a>
            </li>
            <li role="presentation">
                <a href="#email_queue" aria-controls="email_queue" role="tab"
                    data-toggle="tab"><?= _l('email_queue'); ?></a>
            </li>
        </ul>
    </div>
</div>
<div class="tab-content mtop15">
    <div role="tabpanel" class="tab-pane active" id="email_config">
        <?php
        if (! empty($mailValue('smtp_email'))) {
            if ($mailValue('email_protocol') !== 'google' && preg_match('/gmail.com/', $mailValue('smtp_email'))) {
                ?>
        <div class="alert alert-warning">
            <p class="bold">
                Starting from May 30, 2022, Google will no longer support sign in to your Google Account using your
                email/username and account password.
            </p>
            <p>
                If you are using your Google Account password to connect to SMTP, it's highly recommended to <span
                    class="bold">update your password with an App Password</span> or use <span class="bold">Google
                    OAuth2</span> to avoid any email sending
                disruptions, find more information on how to generate App Password for your Google Account at the
                following link: <a href="https://support.google.com/accounts/answer/185833?hl=en"
                    class="alert-link">https://support.google.com/accounts/answer/185833?hl=en</a>
            </p>
        </div>
        <?php
            }
        }
?>
        <!-- fake fields are a workaround for chrome autofill getting the wrong fields -->
        <input type="text" class="fake-autofill-field" name="fakeusernameremembered" value='' tabindex="-1" />
        <input type="password" class="fake-autofill-field" name="fakepasswordremembered" value='' tabindex="-1" />
        <h4 style="margin-top:-20px;" class="tw-font-semibold">
            <?= _l('settings_smtp_settings_heading'); ?>
            <small
                class="text-muted"><?= _l('settings_smtp_settings_subheading'); ?></small>
        </h4>
        <hr />
        <div class="form-group">

            <label
                for="mail_engine"><?= _l('mail_engine'); ?></label><br />
            <div class="radio radio-inline radio-primary">
                <input type="radio" name="settings[mail_engine]" id="phpmailer" value="phpmailer"
                    <?= $mailValue('mail_engine') == 'phpmailer' ? 'checked' : ''; ?>>
                <label for="phpmailer">PHPMailer</label>
            </div>

            <div class="radio radio-inline radio-primary">
                <input type="radio" name="settings[mail_engine]" id="codeigniter" value="codeigniter"
                    <?= $mailValue('mail_engine') == 'codeigniter' ? 'checked' : ''; ?>>
                <label for="codeigniter">CodeIgniter</label>
            </div>
            <hr />
            <?php if ($mailValue('email_protocol') == 'mail') { ?>
            <div class="alert alert-warning">
                The "mail" protocol is not the recommended protocol to send emails, you should strongly consider
                configuring the "SMTP" protocol to avoid any distruptions and delivery issues.
            </div>
            <?php } ?>
            <label
                for="email_protocol"><?= _l('email_protocol'); ?></label><br />
            <div class="radio radio-inline radio-primary">
                <input type="radio" name="settings[email_protocol]" id="smtp" value="smtp"
                    <?= $mailValue('email_protocol') == 'smtp' ? 'checked' : ''; ?>>
                <label for="smtp">SMTP</label>
            </div>

            <div
                class="radio radio-inline radio-primary protocol-microsoft<?= $mailValue('mail_engine') === 'codeigniter' ? ' hide' : ''; ?>">
                <input type="radio" name="settings[email_protocol]" id="microsoft" value="microsoft"
                    <?= $mailValue('email_protocol') == 'microsoft' ? 'checked' : ''; ?>>
                <label for="microsoft">Microsoft OAuth 2.0</label>
            </div>

            <div
                class="radio radio-inline radio-primary protocol-google<?= $mailValue('mail_engine') === 'codeigniter' ? ' hide' : ''; ?>">
                <input type="radio" name="settings[email_protocol]" id="google" value="google"
                    <?= $mailValue('email_protocol') == 'google' ? 'checked' : ''; ?>>
                <label for="google">Gmail OAuth 2.0</label>
            </div>

            <?php if (!$nativeMail) { ?><div class="radio radio-inline radio-primary">
                <input type="radio" name="settings[email_protocol]" id="sendmail" value="sendmail"
                    <?= $mailValue('email_protocol') == 'sendmail' ? 'checked' : ''; ?>>
                <label for="sendmail">Sendmail</label>
            </div>

            <div class="radio radio-inline radio-primary">
                <input type="radio" name="settings[email_protocol]" id="mail" value="mail"
                    <?= $mailValue('email_protocol') == 'mail' ? 'checked' : ''; ?>>
                <label for="mail">Mail</label>
            </div><?php } ?>
        </div>
        <div
            class="smtp-fields<?= $mailValue('email_protocol') == 'mail' ? ' hide' : ''; ?>">
            <div
                class="tw-my-8 tw-border tw-border-solid tw-rounded-md tw-border-neutral-200 tw-px-3 tw-py-4 xoauth-microsoft-show<?= $mailValue('email_protocol') !== 'microsoft' ? ' hide' : ''; ?>">
                <p>
                    These details are obtained by setting up an app in your Microsoft Azure <a
                        href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade"
                        target="_blank">developer portal</a>.
                </p>
                <p>
                    <span class="tw-font-semibold">Redirect URL:</span>
                    <span
                        class="tw-select-all"><?= admin_url('smtp_oauth_microsoft/token'); ?></span>
                </p>
                <div class="form-group">
                    <label for="ClientId">Client Id</label>
                    <input type="text" class="form-control" id="ClientId" name="settings[microsoft_mail_client_id]"
                        value="<?= e($mailValue('microsoft_mail_client_id')); ?>" />
                </div>
                <div class="form-group">
                    <label for="clientSecret">Client Secret</label>
                    <input type="password" class="form-control" id="clientSecret"
                        name="settings[microsoft_mail_client_secret]"
                        value="<?= $mailSecret('microsoft_mail_client_secret'); ?>" />
                </div>
                <div class="form-group">
                    <label for="tenantId">Tenant ID (only relevant for Azure)</label>
                    <input type="text" class="form-control" id="tenantId"
                        name="settings[microsoft_mail_azure_tenant_id]"
                        value="<?= e($mailValue('microsoft_mail_azure_tenant_id')); ?>" />
                </div>
                <?php if (! empty($mailValue('microsoft_mail_client_id')) && ($nativeMail ? $native_settings_email['secret_configured']['microsoft'] : ! empty($mailValue('microsoft_mail_client_secret')))) { ?>
                <?php if($nativeMail) { ?>
                <button type="button" class="btn btn-primary native-mail-command" data-url="<?= e(admin_url('smtp_oauth_microsoft/token')); ?>" data-action="begin" data-revision="<?= (int)$native_settings_email['microsoft_revision']; ?>">Authenticate</button>
                <button type="button" class="btn btn-default native-mail-command" data-url="<?= e(admin_url('smtp_oauth_microsoft/token')); ?>" data-action="revoke" data-revision="<?= (int)$native_settings_email['microsoft_revision']; ?>"><?= _l('revoke'); ?></button>
                <?php } else { ?><a href="<?= admin_url('smtp_oauth_microsoft/token'); ?>"
                    class="btn btn-primary">
                    Authenticate
                </a><?php } ?>
                <?php } else { ?>
                <div class="alert alert-warning">
                    To authenticate, first add Client Id and Client Secret and save settings.
                </div>
                <?php } ?>

            </div>

            <div
                class="tw-my-8 tw-border tw-border-solid tw-rounded-md tw-border-neutral-200 tw-px-3 tw-py-4 xoauth-google-show<?= $mailValue('email_protocol') !== 'google' ? ' hide' : ''; ?>">
                <p>
                    These details are obtained by setting up a project in your <a
                        href="https://console.developers.google.com/" target="_blank">Google API Console</a>.
                </p>
                <p>
                    <span class="tw-font-semibold">Redirect URL:</span>
                    <span
                        class="tw-select-all"><?= admin_url('smtp_oauth_google/token'); ?></span>
                </p>
                <div class="form-group">
                    <label for="ClientId">Client Id</label>
                    <input type="text" class="form-control" id="ClientId" name="settings[google_mail_client_id]"
                        value="<?= e($mailValue('google_mail_client_id')); ?>" />
                </div>
                <div class="form-group">
                    <label for="clientSecret">Client Secret</label>
                    <input type="password" class="form-control" id="clientSecret"
                        name="settings[google_mail_client_secret]"
                        value="<?= $mailSecret('google_mail_client_secret'); ?>" />
                </div>
                <?php if (! empty($mailValue('google_mail_client_id')) && ($nativeMail ? $native_settings_email['secret_configured']['google'] : ! empty($mailValue('google_mail_client_secret')))) { ?>
                <?php if($nativeMail) { ?>
                <button type="button" class="btn btn-primary native-mail-command" data-url="<?= e(admin_url('smtp_oauth_google/token')); ?>" data-action="begin" data-revision="<?= (int)$native_settings_email['google_revision']; ?>">Authenticate</button>
                <button type="button" class="btn btn-default native-mail-command" data-url="<?= e(admin_url('smtp_oauth_google/token')); ?>" data-action="revoke" data-revision="<?= (int)$native_settings_email['google_revision']; ?>"><?= _l('revoke'); ?></button>
                <?php } else { ?><a href="<?= admin_url('smtp_oauth_google/token'); ?>"
                    class="btn btn-primary">
                    Authenticate
                </a><?php } ?>
                <?php } else { ?>
                <div class="alert alert-warning">
                    To authenticate, first add Client Id and Client Secret and save settings.
                </div>
                <?php } ?>

            </div>

            <div class="form-group mtop15">
                <label
                    for="smtp_encryption"><?= _l('smtp_encryption'); ?></label><br />
                <select name="settings[smtp_encryption]" class="selectpicker" data-width="100%">
                    <option value="" <?= $mailValue('smtp_encryption') == '' ? 'selected' : '' ?>>
                        <?= _l('smtp_encryption_none'); ?>
                    </option>
                    <option value="ssl" <?= $mailValue('smtp_encryption') == 'ssl' ? 'selected' : '' ?>>SSL
                    </option>
                    <option value="tls" <?= $mailValue('smtp_encryption') == 'tls' ? 'selected' : '' ?>>TLS
                    </option>

                </select>
            </div>
            <?= render_input('settings[smtp_host]', 'settings_email_host', $mailValue('smtp_host')); ?>
            <?= render_input('settings[smtp_port]', 'settings_email_port', $mailValue('smtp_port')); ?>
        </div>
        <?= render_input(
            'settings[smtp_email]',
            'settings_email',
            $mailValue('smtp_email'),
            'text',
            [],
            [],
            empty($mailValue('smtp_email')) && in_array($mailValue('email_protocol'), ['microsoft', 'google']) ? 'has-error' : ''
        ); ?>
        <div class="xoauth-hide smtp-fields<?php if (in_array($mailValue('email_protocol'), ['mail', 'microsoft', 'google'])) {
            echo ' hide';
        } ?>">
            <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip"
                data-title="<?= _l('smtp_username_help'); ?>"></i>
            <?= render_input('settings[smtp_username]', 'smtp_username', $mailValue('smtp_username')); ?>
            <?php
            $ps = $mailValue('smtp_password');
if (!$nativeMail && ! empty($ps)) {
    if ($this->encryption->decrypt($ps) == false) {
        $ps = $ps;
    } else {
        $ps = $this->encryption->decrypt($ps);
    }
}
echo render_input('settings[smtp_password]', 'settings_email_password', $ps, 'password', ['autocomplete' => 'off']); ?>
        </div>
        <?= render_input('settings[smtp_email_charset]', 'settings_email_charset', $mailValue('smtp_email_charset')); ?>
        <?= render_input('settings[bcc_emails]', 'bcc_all_emails', $mailValue('bcc_emails')); ?>
        <?= render_textarea('settings[email_signature]', 'settings_email_signature', $mailValue('email_signature'), ['data-entities-encode' => 'true']); ?>
        <hr />
        <?= render_textarea('settings[email_header]', 'email_header', $mailValue('email_header'), ['rows' => 15, 'data-entities-encode' => 'true']); ?>
        <?= render_textarea('settings[email_footer]', 'email_footer', $mailValue('email_footer'), ['rows' => 15, 'data-entities-encode' => 'true']); ?>
        <hr />
        <h4><?= _l('settings_send_test_email_heading'); ?>
        </h4>
        <p class="text-muted">
            <?= _l('settings_send_test_email_subheading'); ?>
        </p>
        <div class="form-group">
            <div class="input-group">
                <input type="email" class="form-control" name="test_email" data-ays-ignore="true"
                    placeholder="<?= _l('settings_send_test_email_string'); ?>">
                <div class="input-group-btn">
                    <button type="button" class="btn btn-info <?= $nativeMail?'native-smtp-test':'test_email'; ?>"<?php if($nativeMail){ ?> data-url="<?= e(admin_url('emails/sent_smtp_test_email')); ?>"<?php } ?>>Test</button>
                </div>
            </div>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="email_queue">
        <?php if (($nativeMail ? $native_settings_email['cron_ready'] : get_option('cron_has_run_from_cli')) != '1') { ?>
        <div class="alert alert-danger">
            This feature requires a properly configured cron job. Before activating the feature, make sure that the <a
                href="<?= admin_url('settings?group=cronjob'); ?>">cron
                job</a> is configured as explanation in
            the documentation.
        </div>
        <?php } ?>
        <?php $mailYesNo('email_queue_enabled', 'email_queue_enabled', 'To speed up the emailing process, the system will add the emails in queue and will send them via cron job, make sure that the cron job is properly configured in order to use this feature.'); ?>
        <hr />
        <?php $mailYesNo('email_queue_skip_with_attachments', 'email_queue_skip_attachments', 'Most likely you will encounter problems with the email queue if the system needs to add big files to the queue. If you plan to use this option consult with your server administrator/hosting provider to increase the max_allowed_packet and wait_timeout options in your server config, otherwise when this option is set to yes the system won\'t add emails with attachments in the queue and will be sent immediately.'); ?>
        <?php
        $queueEmails = $nativeMail ? $native_settings_email['queue'] : $this->email->get_queue_emails();
?>
        <hr />
        <h4 class="mbot15 tw-flex tw-justify-between tw-items-center">
            <?= _l('email_queue'); ?>
            <?php if($nativeMail){ ?><button type="button" class="btn btn-danger btn-sm native-mail-command" data-url="<?= e(admin_url('emails/clear_queued_emails')); ?>"><?= _l('clear_queue'); ?></button><?php }else{ ?>
            <a href="<?= admin_url('emails/clear_queued_emails'); ?>" class="btn btn-danger btn-sm"><?= _l('clear_queue'); ?></a><?php } ?>
        </h4>

        <table class="table dt-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>To</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queueEmails as $email) {
                    $headers = $nativeMail ? ['subject'=>$email->subject] : unserialize($email->headers, ['allowed_classes'=>false]); ?>
                <tr>
                    <td><?= e($headers['subject']); ?>
                    </td>
                    <td><?= e($email->email); ?></td>
                    <td><?= e($email->status); ?></td>
                    <td>
                        <?php if($nativeMail){ ?><button type="button" class="btn btn-link text-danger native-mail-command" aria-label="<?= e(_l('delete')); ?>" data-url="<?= e(admin_url('emails/delete_queued_email/'.$email->id)); ?>"><i class="fa fa-trash"></i></button><?php }else{ ?><a href="<?= admin_url('emails/delete_queued_email/' . $email->id); ?>"
                            class="text-danger">
                            <i class="fa fa-trash"></i>
                        </a><?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if($nativeMail){ ?>
<script>
(function(){
 var commandFields=<?= json_encode($native_settings_email['command_fields'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?>;
 // DataTables creates a named pagination select inside the surrounding Settings
 // form. It controls only the queue display and is not a Settings POST field.
 var settingsForm=document.getElementById('settings-form');
 if(settingsForm)settingsForm.addEventListener('submit',function(){
  settingsForm.querySelectorAll('#email_queue .dataTables_length select[name]').forEach(function(select){select.removeAttribute('name');});
 });
 document.querySelectorAll('.native-mail-command').forEach(function(button){button.addEventListener('click',function(){
  if(button.disabled)return;var target=new URL(button.dataset.url,window.location.href);if(target.origin!==window.location.origin)return;
  var form=document.createElement('form');form.method='POST';form.action=target.href;
  var fields=Object.assign({},commandFields);
  if(button.dataset.action)fields.action=button.dataset.action;if(button.dataset.revision)fields.revision=button.dataset.revision;
  Object.keys(fields).forEach(function(key){var input=document.createElement('input');input.type='hidden';input.name=key;input.value=fields[key];form.appendChild(input);});
  document.body.appendChild(form);button.disabled=true;form.submit();
 });});
 var test=document.querySelector('.native-smtp-test');if(test)test.addEventListener('click',async function(){
  if(test.disabled)return;var input=document.querySelector('[name="test_email"]');if(!input||!input.reportValidity()||!input.value)return;
  var url=new URL(test.dataset.url,location.href);if(url.origin!==location.origin)return;
  var result=document.getElementById('native-smtp-result');if(!result){result=document.createElement('p');result.id='native-smtp-result';result.setAttribute('role','status');test.closest('.form-group').appendChild(result);}
  test.disabled=true;result.textContent='Enviando teste da empresa selecionada…';
  try{var body=new URLSearchParams(Object.assign({},commandFields,{test_email:input.value}));var response=await fetch(url.href,{method:'POST',credentials:'same-origin',redirect:'error',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()});var data=await response.json();result.textContent=typeof data.message==='string'?data.message:'Não foi possível confirmar o envio.';}
  catch(error){result.textContent='Não foi possível confirmar o envio. A tentativa não será repetida automaticamente.';}
  // Reload is explicit: never retry a lost response from this click automatically.
 });
})();
</script>
<?php } ?>
