<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Prepares email template preview $data for the view
 * @param  string $template    template class name
 * @param  mixed $customer_id_or_email customer ID to fetch the primary contact email or email
 * @return array
 */
function prepare_mail_preview_data($template, $customer_id_or_email, $mailClassParams = [])
{
    $CI = &get_instance();

    if (is_numeric($customer_id_or_email)) {
        $contact = $CI->clients_model->get_contact(get_primary_contact_user_id($customer_id_or_email));
        $email   = $contact ? $contact->email : '';
    } else {
        $email = $customer_id_or_email;
    }

    $CI->load->model('emails_model');

    $data['template'] = $CI->app_mail_template->prepare($email, $template, $mailClassParams);
    $slug             = $CI->app_mail_template->get_default_property_value('slug', $template, $mailClassParams);

    $data['template_name'] = $slug;

    // Preview metadata belongs to the same localized row selected for the actual message.
    $template_result = $data['template'];
    if (!$template_result) {
        show_error('Modelo de e-mail não encontrado: ' . html_escape($slug));
    }

    $data['template_system_name'] = $template_result->name;
    $data['template_id']          = $template_result->emailtemplateid;

    $data['template_disabled'] = $template_result->active == 0;

    return $data;
}
/**
 * Parse email template with the merge fields
 * @param  mixed $template     template
 * @param  array  $merge_fields
 * @return object
 */
function parse_email_template($template, $merge_fields = [])
{
    $CI = & get_instance();
    if (!is_object($template) || $CI->input->post('template_name')) {
        $original_template = $template;

        if (!class_exists('emails_model', false)) {
            $CI->load->model('emails_model');
        }

        if ($CI->input->post('template_name')) {
            $template = $CI->input->post('template_name');
        }

        $template = $CI->emails_model->get_for_sending((string) $template,
            is_object($original_template) && isset($original_template->language)
                ? (string) $original_template->language : (string) get_option('active_language'));
        if (!$template) throw new DomainException('email_template_not_found');

        if ($CI->input->post('email_template_custom')) {
            $template->message = $CI->input->post('email_template_custom', false);
            // Replace the subject too
            $template->subject = $original_template->subject;
        }
    }

    $template = parse_email_template_merge_fields($template, $merge_fields);

    // Used in hooks eq for emails tracking
    $template->tmp_id = app_generate_hash();

    return hooks()->apply_filters('email_template_parsed', $template);
}

/**
 * This function will parse email template merge fields and replace with the corresponding merge fields passed before sending email
 * @param  object $template     template from database
 * @param  array $merge_fields available merge fields
 * @return object
 */
function parse_email_template_merge_fields($template, $merge_fields)
{
    $CI = &get_instance();

    if (!class_exists('other_merge_fields', false)) {
        $CI->load->library('merge_fields/other_merge_fields');
    }

    $merge_fields = array_merge($merge_fields, $CI->other_merge_fields->format());

    foreach ($merge_fields as $key => $val) {
        foreach (['message', 'fromname', 'subject'] as $section) {
            $template->{$section} = stripos($template->{$section}, $key) !== false
            ? str_replace($key, $val, $template->{$section})
            : str_replace($key, '', $template->{$section});
        }
    }

    return $template;
}

/**
 * Send mail template
 * @since  2.3.0
 * @return mixed
 */
function send_mail_template()
{
    $params = func_get_args();

    return mail_template(...$params)->send();
}

/**
 * Prepare mail template class
 * @param  string $class mail template class name
 * @return mixed
 */
function mail_template($class)
{
    $CI = &get_instance();

    $params = func_get_args();

    // First params is the $class param
    unset($params[0]);

    $params = array_values($params);

    $path = get_mail_template_path($class, $params);

    if (!file_exists($path)) {
        if (!defined('CRON')) {
            show_error('Mail Class Does Not Exists [' . $path . ']');
        } else {
            return false;
        }
    }

    // Include the mailable class
    if (!class_exists($class, false)) {
        include_once($path);
    }

    // Initialize the class and pass the params
    $instance = new $class(...$params);

    // Call the send method
    return $instance;
}

function get_mail_template_path($class, &$params)
{
    $CI  = &get_instance();
    $dir = APPPATH . 'libraries/mails/';

    // Check if second parameter is module and is activated so we can get the class from the module path
    // Also check if the first value is not equal to '/' e.q. when import is performed we set
    // for some values which are blank to "/"
    if (isset($params[0]) && is_string($params[0]) && $params[0] !== '/' && is_dir(module_dir_path($params[0]))) {
        $module = $CI->app_modules->get($params[0]);

        if ($module['activated'] === 1) {
            $dir = module_libs_path($params[0]) . 'mails/';
        }

        unset($params[0]);
        $params = array_values($params);
    }

    return $dir . ucfirst($class) . '.php';
}
/**
 * Create new email template
 * @param  string  $subject the predefined email template subject
 * @param  string  $message the predefined email template message
 * @param  string  $type    for what feature this email template is related e.q. invoice|ticket
 * @param  string  $name    the email template name which user see in Setup->Email Template, this is used for easier email template recognition
 * @param  string  $slug    unique email template slug
 * @param  integer $active  whether by default this email template is active
 * @return mixed
 */
function create_email_template($subject, $message, $type, $name, $slug, $active = 1)
{
    require_once APPPATH . 'services/EmailTemplatesPtBr.php';
    require_once APPPATH . 'services/EmailTemplateReconciler.php';
    $CI = &get_instance();
    return EmailTemplateReconciler::create($CI->db, db_prefix(), [
        'subject' => $subject, 'message' => $message, 'type' => $type,
        'name' => $name, 'slug' => $slug, 'language' => 'english',
        'active' => $active, 'plaintext' => 0, 'order' => 0,
        'fromname' => '{companyname} | CRM', 'fromemail' => '',
    ]);
}

/**
 * Check whether an email template is active based on given slug
 *
 * @since 2.7.0
 *
 * @param  string  $slug
 *
 * @return boolean
 */
function is_email_template_active($slug)
{
    $CI = &get_instance();
    $scoped = false;
    $issuer = null;

    // Interactive requests and explicit settings operations both bind this
    // availability check to a server-owned issuer context.
    if (class_exists('CompanyRequestScope', false) && ($context = CompanyRequestScope::context()) !== null) {
        $scoped = true;
        $issuer = $context->issuerId();
    } elseif (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) {
        $scoped = true;
        $issuer = CompanySettingsRuntime::issuerId();
    }

    if ($scoped) {
        if ($issuer === null) { return false; }
        require_once APPPATH . 'services/CompanyEmailTemplates.php';
        $scope = CompanyEmailTemplates::scopeSql($CI->db, db_prefix(), 'active_template', $issuer);
        return (bool) IssuerStorage::query(
            $CI->db,
            'SELECT 1 FROM ' . db_prefix() . 'emailtemplates active_template WHERE active_template.slug=? AND active_template.active=1 AND ' . $scope . ' LIMIT 1',
            [$slug]
        )->row();
    }

    return total_rows(db_prefix() . 'emailtemplates', ['slug' => $slug, 'active' => 1]) > 0;
}
