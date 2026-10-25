<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<body class="tw-bg-neutral-100 authentication set-password">
    <div class="tw-max-w-md tw-mx-auto tw-pt-24 authentication-form-wrapper tw-relative tw-z-20">
        <div class="company-logo text-center">
            <?= get_dark_company_logo(); ?>
        </div>

        <h1 class="tw-text-2xl tw-text-neutral-800 text-center tw-font-semibold tw-mb-5">
            <?= _l('admin_auth_set_password_heading'); ?>
        </h1>

        <div
            class="tw-bg-white tw-mx-2 sm:tw-mx-6 tw-py-8 tw-px-6 sm:tw-px-8 tw-shadow-sm tw-rounded-lg tw-border tw-border-solid tw-border-neutral-600/20">
            <?= form_open($this->uri->uri_string()); ?>
            <?php if(!empty($native_staff_invitation)){echo form_hidden('native_staff_invitation_token','');} ?>
            <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>
            <?php $this->load->view('authentication/includes/alerts'); ?>
            <?= render_input('password', 'admin_auth_set_password', '', 'password'); ?>
            <?= render_input('passwordr', 'admin_auth_set_password_repeat', '', 'password'); ?>
            <button type="submit" class="btn btn-primary btn-block tw-font-semibold tw-py-2">
                <?= _l('admin_auth_set_password_heading'); ?>
            </button>
            <?= form_close(); ?>
        </div>
    </div>
<?php if(!empty($native_staff_invitation)){ ?>
<script>
(function () {
    var field = document.querySelector('input[name="native_staff_invitation_token"]');
    if (!field) return;
    var token = new URLSearchParams(window.location.hash.slice(1)).get('token');
    if (typeof token === 'string' && /^[a-f0-9]{64}$/.test(token)) field.value = token;
    if (window.location.hash) window.history.replaceState(null, '', window.location.pathname + window.location.search);
    field.form.addEventListener('submit', function (event) {
        if (!/^[a-f0-9]{64}$/.test(field.value)) event.preventDefault();
    });
}());
</script>
<?php } ?>
</body>

</html>