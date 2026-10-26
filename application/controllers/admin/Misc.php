<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Misc extends AdminController
{
    private ?NativeCompanyAuxiliaryUi $companyAuxiliary = null;
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';
            $context=CompanyRequestScope::context();
            $this->companyAuxiliary=new NativeCompanyAuxiliaryUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);
            return;
        }
        $this->load->model('misc_model');
    }

    public function fetch_address_info_gmaps()
    {
        include_once APPPATH . 'third_party/JD_Geocoder_Request.php';

        $data    = $this->input->post();
        $address = '';

        $address .= $data['address'];
        if (! empty($data['city'])) {
            $address .= ', ' . $data['city'];
        }

        if (! empty($data['country'])) {
            $address .= ', ' . $data['country'];
        }

        $apiKey = get_option('google_api_key');
        if (empty($apiKey)) {
            echo json_encode([
                'response' => [
                    'status'        => 'MISSING_API_KEY',
                    'error_message' => 'Add Google API Key in Setup->Settings->Google',
                ],
            ]);

            exit;
        }

        $georequest = new JD_Geocoder_Request($apiKey);
        $georequest->forwardSearch($address);
        echo json_encode($georequest);
    }

    public function get_currency($id)
    {
        if($this->companyAuxiliary!==null){echo json_encode($this->nativeCatalog()->currency($id),JSON_THROW_ON_ERROR);return;}
        echo json_encode(get_currency($id));
    }

    public function get_taxes_dropdown_template()
    {
        if($this->companyAuxiliary!==null){$name=$this->input->post('name');if(!is_string($name))throw new DomainException('company_catalog_invalid');echo $this->nativeCatalog()->taxDropdown($name,$this->input->post('taxname'));return;}
        $name    = $this->input->post('name');
        $taxname = $this->input->post('taxname');
        echo $this->misc_model->get_taxes_dropdown_template($name, $taxname);
    }

    public function dismiss_cron_setup_message()
    {
        update_option('hide_cron_is_required_message', 1);
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function dismiss_cloudflare_notice()
    {
        update_option('show_cloudflare_notice', 0);
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function dismiss_php_version_notice()
    {
        update_option('show_php_version_notice', 0);
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function clear_system_popup()
    {
        if($this->companyAuxiliary!==null)CompanyRequestScope::assertNativeMutation($this);
        $this->session->unset_userdata('system-popup');
    }

    public function tinymce_file_browser()
    {
        $data['connector']   = admin_url() . '/utilities/media_connector';
        $data['mediaLocale'] = get_media_locale();
        $this->app_css->add('app-css', base_url($this->app_css->core_file('assets/css', 'style.css')) . '?v=' . $this->app_css->core_version(), 'editor-media');
        $this->load->view('admin/includes/elfinder_tinymce', $data);
    }

    public function get_relation_data()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyRelationEndpoints.php';echo json_encode(NativeCompanyRelationEndpoints::read($this),JSON_THROW_ON_ERROR);return;}
        if ($this->input->post()) {
            $type = $this->input->post('type');
            $data = get_relation_data($type, '', $this->input->post('extra'));
            if ($this->input->post('rel_id')) {
                $rel_id = $this->input->post('rel_id');
            } else {
                $rel_id = '';
            }

            $relOptions = init_relation_options($data, $type, $rel_id);
            echo json_encode($relOptions);

            exit;
        }
    }

    public function delete_sale_activity($id)
    {
        if (is_admin()) {
            $activity = $this->db->where('id', $id)->get(db_prefix() . 'sales_activity')->row();
            if (!$activity || ($activity->rel_type === 'invoice' && !\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $activity->rel_id))) { show_404(); }
            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'sales_activity');
        }
    }

    public function upload_sales_file()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyFileEndpoints.php';echo json_encode(NativeCompanyFileEndpoints::uploadInvoice($this),JSON_THROW_ON_ERROR);return;}
        $rel_id = $this->input->post('rel_id');

        if (! is_numeric($rel_id)) {
            ajax_access_denied();
        }

        handle_sales_attachments($rel_id, $this->input->post('type'));
    }

    public function add_sales_external_attachment()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyFileEndpoints.php';echo json_encode(NativeCompanyFileEndpoints::externalInvoice($this),JSON_THROW_ON_ERROR);return;}
        if ($this->input->post()) {
            $file = $this->input->post('files');
            $this->misc_model->add_attachment_to_database($this->input->post('rel_id'), $this->input->post('type'), $file, $this->input->post('external'));
        }
    }

    public function toggle_file_visibility($id)
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyFileEndpoints.php';echo json_encode(NativeCompanyFileEndpoints::visibility($this,$id),JSON_THROW_ON_ERROR);return;}
        $this->db->where('id', $id);
        $row = $this->db->get(db_prefix() . 'files')->row();
        if (!$row || ($row->rel_type === 'invoice' && !\app\services\InvoiceIssuerScope::isLegacyInvoice($this->db, db_prefix() . 'invoices', $row->rel_id))) { show_404(); }
        if ($row->visible_to_customer == 1) {
            $v = 0;
        } else {
            $v = 1;
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'files', [
            'visible_to_customer' => $v,
        ]);
        echo $v;
    }

    public function format_date()
    {
        if($this->companyAuxiliary!==null){
            $raw=$this->input->post('date');if(!is_string($raw)||strlen($raw)>160||str_contains($raw,"\0"))throw new DomainException('company_auxiliary_invalid_input');
            $stamp=strtotime(trim(explode('(',$raw,2)[0]));if($stamp===false)throw new DomainException('company_auxiliary_invalid_input');
            require_once APPPATH.'services/NativeCompanyDateInput.php';echo (new NativeCompanyDateInput($this->db,db_prefix(),CompanyRequestScope::context()->issuerId()))->displayDate(date('Y-m-d',$stamp));return;
        }
        if ($this->input->post()) {
            $date = $this->input->post('date');
            $date = strtotime(current(explode('(', $date)));
            echo _d(date('Y-m-d', $date));
        }
    }

    public function send_file()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyFileEndpoints.php';echo json_encode(NativeCompanyFileEndpoints::send($this),JSON_THROW_ON_ERROR);return;}
        if ($this->input->post('send_file_email')) {
            if ($this->input->post('file_path')) {
                $this->load->model('emails_model');
                $this->emails_model->add_attachment([
                    'attachment' => $this->input->post('file_path'),
                    'filename'   => $this->input->post('file_name'),
                    'type'       => $this->input->post('filetype'),
                    'read'       => true,
                ]);
                $message = $this->input->post('send_file_message');
                $message = nl2br($message);
                $success = $this->emails_model->send_simple_email($this->input->post('send_file_email'), $this->input->post('send_file_subject'), $message);
                if ($success) {
                    set_alert('success', _l('custom_file_success_send', $this->input->post('send_file_email')));
                } else {
                    set_alert('warning', _l('custom_file_fail_send'));
                }
            }
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function update_ei_items_order($type)
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyItemOrderEndpoints.php';echo json_encode(NativeCompanyItemOrderEndpoints::save($this,$type),JSON_THROW_ON_ERROR);return;}
        $context=CompanyRequestScope::context();
        if(strtoupper($this->input->method(true))!=='POST'||$context===null||!$context->isPrimary()||$context->staffId()!==(int)get_staff_user_id())throw new DomainException('native_legacy_order_denied');
        require_once APPPATH.'services/NativeCompanyLegacyItemOrder.php';
        (new NativeCompanyLegacyItemOrder($this->db,db_prefix()))->save($context->issuerId(),$context->staffId(),$type,$this->input->post('data'));
    }

    // Since version 1.0.2 add client reminder
    public function add_reminder($rel_id_id, $rel_type)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->saveReminder($rel_type,$rel_id_id,$this->nativeReminderInput());
            echo json_encode(['alert_type'=>'success','message'=>_l('reminder_added_successfully')],JSON_THROW_ON_ERROR); return;
        }
        $message    = '';
        $alert_type = 'warning';
        if ($this->input->post()) {
            $success = $this->misc_model->add_reminder($this->input->post(), $rel_id_id);
            if ($success) {
                $alert_type = 'success';
                $message    = _l('reminder_added_successfully');
            }
        }
        echo json_encode([
            'alert_type' => $alert_type,
            'message'    => $message,
        ]);
    }

    public function get_reminders($id, $rel_type)
    {
        if($this->companyAuxiliary!==null){$this->nativeReminderTable($rel_type,$id,'relation'); return;}
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('reminders', [
                'id'       => $id,
                'rel_type' => $rel_type,
            ]);
        }
    }

    public function my_reminders()
    {
        if($this->companyAuxiliary!==null){$this->nativeReminderTable(null,null,'mine'); return;}
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('staff_reminders');
        }
    }

    public function reminders()
    {
        if($this->companyAuxiliary!==null){$this->load->view('admin/utilities/all_reminders',['members'=>$this->companyAuxiliary->members(),'title'=>_l('reminders'),'bodyclass'=>'all-reminders']); return;}
        $this->load->model('staff_model');
        $data['members']   = $this->staff_model->get('', ['active' => 1]);
        $data['title']     = _l('reminders');
        $data['bodyclass'] = 'all-reminders';
        $this->load->view('admin/utilities/all_reminders', $data);
    }

    public function reminders_table()
    {
        if($this->companyAuxiliary!==null){$this->nativeReminderTable(null,null,'all'); return;}
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('all_reminders');
        }
    }

    // Since version 1.0.2 delete client reminder
    public function delete_reminder($rel_id, $id, $rel_type)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $row=$this->companyAuxiliary->reminder($id);
            if ((string)$row['rel_id']!==(string)$rel_id || $row['rel_type']!==$rel_type) throw new DomainException('company_auxiliary_access_denied');
            $this->companyAuxiliary->deleteReminder($id,$this->input->post('revision'));
            echo json_encode(['alert_type'=>'success','message'=>_l('reminder_deleted')],JSON_THROW_ON_ERROR); return;
        }
        if (! $id && ! $rel_id) {
            exit('No reminder found');
        }
        $success    = $this->misc_model->delete_reminder($id);
        $alert_type = 'warning';
        $message    = _l('reminder_failed_to_delete');
        if ($success) {
            $alert_type = 'success';
            $message    = _l('reminder_deleted');
        }
        echo json_encode([
            'alert_type' => $alert_type,
            'message'    => $message,
        ]);
    }

    public function get_reminder($id)
    {
        if ($this->companyAuxiliary !== null) {
            $row=$this->companyAuxiliary->reminder($id);require_once APPPATH.'services/NativeCompanyDateInput.php';
            $row['date']=(new NativeCompanyDateInput($this->db,db_prefix(),CompanyRequestScope::context()->issuerId()))->display($row['date']);
            echo json_encode($row,JSON_THROW_ON_ERROR); return;
        }
        $reminder = $this->misc_model->get_reminders($id);
        if ($reminder) {
            if ($reminder->creator == get_staff_user_id() || is_admin()) {
                $reminder->date        = _dt($reminder->date);
                $reminder->description = clear_textarea_breaks($reminder->description);
                echo json_encode($reminder);
            }
        }
    }

    public function edit_reminder($id)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $row=$this->companyAuxiliary->reminder($id);
            $this->companyAuxiliary->saveReminder($row['rel_type'],$row['rel_id'],$this->nativeReminderInput(),$id,$this->input->post('revision'));
            echo json_encode(['alert_type'=>'success','message'=>_l('updated_successfully',_l('reminder'))],JSON_THROW_ON_ERROR); return;
        }
        $reminder = $this->misc_model->get_reminders($id);
        if ($reminder && ($reminder->creator == get_staff_user_id() || is_admin()) && $reminder->isnotified == 0) {
            $success = $this->misc_model->edit_reminder($this->input->post(), $id);
            echo json_encode([
                'alert_type' => 'success',
                'message'    => ($success ? _l('updated_successfully', _l('reminder')) : ''),
            ]);
        }
    }

    public function run_cron_manually()
    {
        if (is_admin()) {
            $this->load->model('cron_model');
            $this->cron_model->run(true);
            redirect(admin_url('settings?group=cronjob'));
        }
    }

    // Since Version 1.0.1 - General search
    public function search()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::search($this),JSON_THROW_ON_ERROR);return;}
        $q = $this->input->post('q');

        $recentSearches = array_reverse(get_staff_recent_search_history());

        $recentSearches[] = $q;

        $recentSearches = update_staff_recent_search_history($recentSearches);

        $data['result'] = $this->misc_model->perform_search($q);
        echo json_encode([
            'results' => $this->load->view('admin/search', $data, true),
            'history' => $recentSearches,
        ]);
    }

    public function remove_recent_search($index)
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::removeSearch($this,$index),JSON_THROW_ON_ERROR);return;}
        $recentSearches = get_staff_recent_search_history();
        unset($recentSearches[$index]);
        update_staff_recent_search_history(array_reverse($recentSearches));
    }

    public function add_note($rel_id, $rel_type)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->saveNote($rel_type,$rel_id,['description'=>$this->input->post('description',false)]);
            $context=CompanyRequestScope::context();
            $ref=NativeCompanyResourceReferences::resolve($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$rel_type,$rel_id);
            redirect($rel_type==='staff'?CompanyRequestScope::adminUrl($this,'staff/member/'.$ref['source_id']):$ref['url'],'location',303); return;
        }
        if ($this->input->post()) {
            $success = $this->misc_model->add_note($this->input->post(), $rel_type, $rel_id);
            if ($success) {
                set_alert('success', _l('added_successfully', _l('note')));
            }
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function edit_note($id)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $row=$this->companyAuxiliary->note($id);$description=$this->input->post('description',false);
            $this->companyAuxiliary->saveNote($row['rel_type'],$row['rel_id'],['description'=>$description],$id,$this->input->post('revision'));
            $saved=$this->companyAuxiliary->note($id);
            echo json_encode(['success'=>true,'description'=>nl2br(htmlspecialchars($saved['description'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),'revision'=>$saved['revision'],'message'=>_l('note_updated_successfully')],JSON_THROW_ON_ERROR); return;
        }
        if ($this->input->post()) {
            $data    = $this->input->post();
            $success = $this->misc_model->edit_note($data, $id);

            echo json_encode([
                'description' => process_text_content_for_display(nl2br($data['description'])),
                'success'     => $success,
                'message'     => _l('note_updated_successfully'),
            ]);
        }
    }

    public function delete_note($id)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->deleteNote($id,$this->input->post('revision'));
            echo json_encode(['success'=>true],JSON_THROW_ON_ERROR); return;
        }
        $success = $this->misc_model->delete_note($id);

        if (! $this->input->is_ajax_request()) {
            if ($success) {
                set_alert('success', _l('deleted', _l('note')));
            }
            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        } else {
            echo json_encode(['success' => $success]);
        }
    }

    // Remove customizer open from database
    public function set_setup_menu_closed()
    {
        if($this->companyAuxiliary!==null)CompanyRequestScope::assertNativeMutation($this);
        if ($this->input->is_ajax_request()) {
            $this->session->set_userdata([
                'setup-menu-open' => '',
            ]);
        }
    }

    // Set session that user clicked on setup_menu menu link to stay open
    public function set_setup_menu_open()
    {
        if($this->companyAuxiliary!==null)CompanyRequestScope::assertNativeMutation($this);
        if ($this->input->is_ajax_request()) {
            $this->session->set_userdata([
                'setup-menu-open' => true,
            ]);
        }
    }

    // User dismiss announcement
    public function dismiss_announcement($id)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->dismissAnnouncement($id);
            redirect(CompanyRequestScope::adminUrl($this,'announcements'),'location',303); return;
        }
        $this->misc_model->dismiss_announcement($id);
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    // Set notifications to read
    public function set_notifications_read()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::markNotifications($this,null,'seen'),JSON_THROW_ON_ERROR);return;}
        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'success' => $this->misc_model->set_notifications_read(),
            ]);
        }
    }

    public function set_notification_read_inline($id)
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::markNotifications($this,$id,'inline'),JSON_THROW_ON_ERROR);return;}
        $this->misc_model->set_notification_read_inline($id);
    }

    public function set_desktop_notification_read($id)
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::markNotifications($this,$id,'all'),JSON_THROW_ON_ERROR);return;}
        $this->misc_model->set_desktop_notification_read($id);
    }

    public function mark_all_notifications_as_read_inline()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::markNotifications($this,null,'all'),JSON_THROW_ON_ERROR);return;}
        $this->misc_model->mark_all_notifications_as_read_inline();
    }

    public function notifications_check()
    {
        if($this->companyAuxiliary!==null){require_once APPPATH.'services/NativeCompanyHeaderEndpoints.php';echo json_encode(NativeCompanyHeaderEndpoints::notifications($this),JSON_THROW_ON_ERROR);return;}
        $notificationsIds = [];
        if (get_option('desktop_notifications') == '1') {
            $notifications = $this->misc_model->get_user_notifications();

            $notificationsPluck = array_filter($notifications, function ($n) {
                return $n['isread'] == 0;
            });

            $notificationsIds = array_pluck($notificationsPluck, 'id');
        }

        echo json_encode([
            'html'             => $this->load->view('admin/includes/notifications', [], true),
            'notificationsIds' => $notificationsIds,
        ]);
    }

    // Check if staff email exists / ajax
    public function staff_email_exists()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';$available=NativeCompanyStaffEndpoints::emailAvailable($this);
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($available));return;
        }

        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                require_once APPPATH.'services/StaffEmailIdentity.php';
                $member = $this->input->post('memberid');
                $except = $member === '' || $member === null ? null : (ctype_digit((string)$member) ? (int)$member : 0);
                try {
                    StaffEmailIdentity::assertAvailable($this->db, db_prefix(), $this->input->post('email'), $except);
                    echo json_encode(true);
                } catch (Throwable $error) {
                    echo json_encode(false);
                }
                exit();
            }
        }
    }

    // Check if client email exists/  ajax
    public function contact_email_exists()
    {
        if($this->companyAuxiliary!==null){
            CompanyRequestScope::assertNativeMutation($this);$context=CompanyRequestScope::context();
            $client=IssuerPolicy::id($this->input->post('client_id'));$id=$this->input->post('userid');$email=$this->input->post('email');
            if(!is_string($email))throw new DomainException('company_contact_invalid_input');
            require_once APPPATH.'services/CompanyNativeContacts.php';
            $available=(new CompanyNativeContacts($this->db,db_prefix()))->emailAvailable($context->issuerId(),$context->staffId(),$client,$email,$id===null||$id===''?null:IssuerPolicy::id($id));
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($available));return;
        }
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                // First we need to check if the email is the same
                $userid = $this->input->post('userid');
                if ($userid != '') {
                    $this->db->where('id', $userid);
                    $_current_email = $this->db->get(db_prefix() . 'contacts')->row();
                    if ($_current_email->email == $this->input->post('email')) {
                        echo json_encode(true);

                        exit();
                    }
                }
                $this->db->where('email', $this->input->post('email'));
                $total_rows = $this->db->count_all_results(db_prefix() . 'contacts');
                if ($total_rows > 0) {
                    echo json_encode(false);
                } else {
                    echo json_encode(true);
                }

                exit();
            }
        }
    }

    // Goes blank page but with messagae access danied / message set from session flashdata
    public function access_denied()
    {
        $this->load->view('admin/blank_page');
    }

    // Goes to blank page with message page not found / message set from session flashdata
    public function not_found()
    {
        $this->load->view('admin/blank_page');
    }

    public function change_maximum_number_of_digits_to_decimal_fields($digits)
    {
        if (is_admin()) {
            hooks()->do_action('before_change_maximum_number_of_digits_to_decimal_fields');
            $tables = $this->db->query("SELECT *
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='" . APP_DB_NAME . "'")->result_array();

            foreach ($tables as $table_data) {
                $table  = $table_data['TABLE_NAME'];
                $fields = $this->db->list_fields($table);

                foreach ($fields as $field) {
                    $field_info = $this->db->query('SHOW FIELDS
                        FROM ' . $table . " where Field ='" . $field . "'")->result_array();
                    $field_type = strtolower($field_info[0]['Type']);
                    if (strpos($field_type, 'decimal') !== false) {
                        $field_null = strtoupper($field_info[0]['Null']);
                        if ($field_null == 'YES') {
                            $field_is_null = 'NULL';
                        } else {
                            $field_is_null = 'NOT NULL';
                        }
                        $total_decimals = strafter($field_info[0]['Type'], ',');
                        $total_decimals = strbefore($total_decimals, ')');

                        if ($field_info[0]['Default'] == null) {
                            $field_default_value = '';
                        } else {
                            $field_default_value = ' DEFAULT 0.' . str_repeat(0, $total_decimals);
                        }

                        $this->db->query("ALTER TABLE {$table} CHANGE {$field} {$field} DECIMAL({$digits},{$total_decimals}) {$field_is_null}{$field_default_value};");
                    }
                }
            }
        } else {
            echo 'You need to be logged in as administrator to perform this action.';
        }
    }

    public function change_decimal_places($total_decimals)
    {
        $notChangableFields = ['estimated_hours'];

        if (is_admin()) {
            hooks()->do_action('before_change_decimal_places');

            $tables = $this->db->query("SELECT *
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA='" . APP_DB_NAME . "'")->result_array();

            foreach ($tables as $table_data) {
                $table  = $table_data['TABLE_NAME'];
                $fields = $this->db->list_fields($table);

                foreach ($fields as $field) {
                    if (! in_array($field, $notChangableFields)) {
                        $field_info = $this->db->query('SHOW FIELDS
                            FROM ' . $table . " where Field ='" . $field . "'")->result_array();
                        $field_type = strtolower($field_info[0]['Type']);
                        if (strpos($field_type, 'decimal') !== false) {
                            $field_null = strtoupper($field_info[0]['Null']);
                            if ($field_null == 'YES') {
                                $field_is_null = 'NULL';
                            } else {
                                $field_is_null = 'NOT NULL';
                            }
                            if ($field_info[0]['Default'] == null) {
                                $field_default_value = '';
                            } else {
                                $field_default_value = ' DEFAULT 0.' . str_repeat(0, $total_decimals);
                            }
                            $this->db->query("ALTER TABLE {$table} CHANGE {$field} {$field} DECIMAL(15,{$total_decimals}) {$field_is_null}{$field_default_value};");
                        }
                    }
                }
            }
            echo '<p><strong>Table columns with decimal places updated successfully.</strong></p>';
        } else {
            echo 'You need to be logged in as administrator to perform this action.';
        }
    }

    public function convert_tables_to_innodb_engine()
    {
        if (is_admin()) {
            $databaseName = APP_DB_NAME;
            $tables       = $this->db->query("SELECT TABLE_NAME,
                             ENGINE
                            FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = '{$databaseName}' and ENGINE = 'myISAM'")->result_array();

            foreach ($tables as $table) {
                $tableName = $table['TABLE_NAME'];
                $this->db->query("ALTER TABLE {$tableName} ENGINE=InnoDB;");
            }
            echo 'Table engines successfully changed to InnoDB';
        } else {
            echo 'You need to be logged in as administrator to perform this action.';
        }
    }

    /**
     * The upgrade script for 232 does not perform the queries below for backward compatibility
     * Mostly it changes the varchar maximum length because of InnoDB index
     */
    public function upgrade_232_database()
    {
        $charset = $this->db->char_set;
        $collat  = $this->db->dbcollat;

        if (! is_admin()) {
            exit('You must be logged in as administrator to perform this action');
        }

        if (get_option('_232_upgrade_db_queries_performed') === '1') {
            exit('This action is already processed');
        }

        $this->db->query('ALTER TABLE `' . db_prefix() . 'contacts` CHANGE `lastname` `lastname` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'contacts` CHANGE `firstname` `firstname` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` CHANGE `company` `company` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'customers_groups` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'options` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'invoicepaymentrecords` CHANGE `paymentmethod` `paymentmethod` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'leads` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'leads` CHANGE `company` `company` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'projects` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');

        $this->db->query('ALTER TABLE `' . db_prefix() . 'contacts` CHANGE `title` `title` VARCHAR(100) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');

        $this->db->query('ALTER TABLE `' . db_prefix() . 'web_to_lead` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'vault` CHANGE `username` `username` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'vault` CHANGE `server_address` `server_address` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tracked_mails` CHANGE `subject` `subject` MEDIUMTEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets_predefined_replies` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets_pipe_log` CHANGE `email_to` `email_to` VARCHAR(100) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets_pipe_log` CHANGE `email` `email` VARCHAR(100) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets_pipe_log` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'subscriptions` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'staff` CHANGE `media_path_slug` `media_path_slug` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'proposals` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'proposals` CHANGE `proposal_to` `proposal_to` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'projectdiscussions` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'projectdiscussioncomments` CHANGE `fullname` `fullname` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_files` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_activity` CHANGE `description_key` `description_key` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . " NOT NULL COMMENT 'Language file key';");
        $this->db->query('ALTER TABLE `' . db_prefix() . 'notifications` CHANGE `additional_data` `additional_data` TEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'lead_activity_log` CHANGE `additional_data` `additional_data` TEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'knowledge_base_groups` CHANGE `group_slug` `group_slug` TEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'knowledge_base_groups` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');

        $this->db->query('ALTER TABLE `' . db_prefix() . 'files` CHANGE `file_name` `file_name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'expenses_categories` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'expenses` CHANGE `expense_name` `expense_name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'contracts` CHANGE `subject` `subject` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'contacts` CHANGE `profile_image` `profile_image` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'staff` CHANGE `profile_image` `profile_image` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');

        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` CHANGE `longitude` `longitude` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'clients` CHANGE `latitude` `latitude` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'announcements` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'projectdiscussioncomments` CHANGE `file_name` `file_name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'gdpr_requests` CHANGE `request_type` `request_type` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'user_meta` CHANGE `meta_key` `meta_key` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'tickets_pipe_log` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'mail_queue` CHANGE `email` `email` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'mail_queue` CHANGE `cc` `cc` TEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'mail_queue` CHANGE `bcc` `bcc` TEXT CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'project_files` CHANGE `file_name` `file_name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'ticket_attachments` CHANGE `file_name` `file_name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'milestones` CHANGE `name` `name` VARCHAR(191) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NOT NULL;');
        $this->db->query('ALTER TABLE `' . db_prefix() . 'leads` CHANGE `email` `email` VARCHAR(100) CHARACTER SET ' . $charset . ' COLLATE ' . $collat . ' NULL DEFAULT NULL;');
        add_option('_232_upgrade_db_queries_performed', '1', 0);
    }
    private function nativeReminderInput():array
    {
        $input=[];
        foreach(['description','date','staff','notify_by_email']as$key){$value=$this->input->post($key,false);if($value!==null)$input[$key]=$value;}
        require_once APPPATH.'services/NativeCompanyDateInput.php';
        $input['date']=(new NativeCompanyDateInput($this->db,db_prefix(),CompanyRequestScope::context()->issuerId()))->parse($input['date']??null);
        return $input;
    }
    private function nativeReminderTable(?string $type,$id,string $mode):void
    {
        $start=$this->input->post('start');$length=$this->input->post('length');$draw=$this->input->post('draw');$search=$this->input->post('search');
        foreach([$start,$length,$draw]as$value)if(!is_scalar($value)||!preg_match('/^[0-9]{1,6}$/D',(string)$value))throw new DomainException('company_auxiliary_invalid_input');
        $result=$this->companyAuxiliary->reminderTable($type,$id,(int)$start,(int)$length,is_array($search)?(string)($search['value']??''):'',$mode==='mine',$mode==='all');
        $output=['draw'=>(int)$draw,'iTotalRecords'=>$result['total'],'iTotalDisplayRecords'=>$result['filtered'],'aaData'=>[]];
        foreach($result['rows']as$row){
            $options='';
            if($row['can_edit']){
                $options='<div class="row-options">';
                if(!(int)$row['isnotified'])$options.='<a href="#" onclick="edit_reminder('.(int)$row['id'].',this);return false;">'.e(_l('edit')).'</a> | ';
                $options.='<a href="'.e(admin_url('misc/delete_reminder/'.$row['rel_id'].'/'.$row['id'].'/'.$row['rel_type'])).'" class="text-danger delete-reminder" data-reminder-revision="'.e((string)$row['revision']).'">'.e(_l('delete')).'</a></div>';
            }
            $description=nl2br(e($row['description']));require_once APPPATH.'services/NativeCompanyDateInput.php';$date=e((new NativeCompanyDateInput($this->db,db_prefix(),CompanyRequestScope::context()->issuerId()))->display($row['date']));
            $relation='<a href="'.e(admin_url($row['relation']['url'])).'">'.e($row['relation']['name']).'</a>';
            $staff=e(trim($row['firstname'].' '.$row['lastname']));
            $notified=e(_l((int)$row['isnotified']?'reminder_is_notified_boolean_yes':'reminder_is_notified_boolean_no'));
            $cells=$mode==='mine'?[$relation.$options,$description,$date]:($mode==='relation'?[$description.$options,$date,$staff,$notified]:[$relation.$options,$description,$date,$staff,$notified]);
            $cells['DT_RowClass']='has-row-options';$output['aaData'][]=$cells;
        }
        echo json_encode($output,JSON_THROW_ON_ERROR);
    }
    private function nativeCatalog():NativeCompanyCatalogUi
    {
        require_once APPPATH.'services/NativeCompanyCatalogUi.php';$context=CompanyRequestScope::context();return new NativeCompanyCatalogUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);
    }
}
