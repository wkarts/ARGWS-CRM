<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Authentication_model $authentication_model
 * @property-read Staff_model $staff_model
 */
class Staff extends AdminController
{
    /* List all staff members */
    public function index()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffTable.php';$this->output->set_header('Cache-Control: no-store');
            if($this->input->is_ajax_request()){$result=NativeCompanyStaffTable::endpoint($this);$this->output->set_content_type('application/json')->set_output(json_encode($result));return;}
            if(strtoupper((string)$this->input->method())!=='GET')throw new DomainException('issuer_staff_denied');$c=CompanyRequestScope::context();if((int)get_staff_user_id()!==$c->staffId())throw new DomainException('issuer_staff_denied');$page=(new NativeCompanyStaffTable($this->db,db_prefix()))->read($c->issuerId(),$c->staffId());
            $this->load->view('admin/staff/manage',['native_staff_manage'=>true,'native_staff_can_create'=>$page['can_create'],'native_staff_table_fields'=>$page['fields'],'staff_members'=>array_values(array_filter($page['rows'],static fn($r)=>(int)$r['active']===1)),'title'=>_l('staff_members')]);return;
        }

        if (staff_cant('view', 'staff')) {
            access_denied('staff');
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('staff');
        }
        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        $data['title']         = _l('staff_members');
        $this->load->view('admin/staff/manage', $data);
    }

    /* Add new staff member or edit existing */
    public function member($id = '')
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffMember.php';
            if(strtoupper((string)$this->input->method())==='POST'){$saved=NativeCompanyStaffMember::save($this,$id);set_alert('success',_l($id===''?'added_successfully':'updated_successfully',_l('staff_member')));redirect(CompanyRequestScope::adminUrl($this,'staff/member/'.$saved));return;}
            $this->output->set_header('Cache-Control: no-store');$this->load->view('admin/staff/member',NativeCompanyStaffMember::page($this,$id));return;
        }

        if (staff_cant('view', 'staff')) {
            access_denied('staff');
        }
        hooks()->do_action('staff_member_edit_view_profile', $id);

        $this->load->model('departments_model');
        if ($this->input->post()) {
            $data = $this->input->post();
            $issuerAccess = null;
            if (function_exists('issuers_prepare_staff_access')) {
                try {
                    $issuerAccess = issuers_prepare_staff_access($data, $id === '' ? null : $id);
                    if ($id === '') { issuers_require_new_staff_access($issuerAccess); }
                }
                catch (Throwable $error) {
                    set_alert('warning', _l($error instanceof DomainException ? $error->getMessage() : 'issuer_storage_failed'));
                    redirect(admin_url('staff/member/' . $id));
                    return;
                }
            }
            // Don't do XSS clean here.
            $data['email_signature'] = $this->input->post('email_signature', false);
            $data['email_signature'] = html_entity_decode($data['email_signature']);

            if ($data['email_signature'] == strip_tags($data['email_signature'])) {
                // not contains HTML, add break lines
                $data['email_signature'] = nl2br_save_html($data['email_signature']);
            }

            $data['password'] = $this->input->post('password', false);

            if($issuerAccess!==null){
                if(staff_cant($id===''?'create':'edit','staff')){access_denied('staff');}
                try{$result=issuers_save_staff_atomically($this->staff_model,$id===''?'add':'update',$data,$id===''?null:$id,$issuerAccess);$id=$result['staff_id'];}
                catch(Throwable $error){issuers_staff_atomic_error($error);redirect(admin_url('staff/member/'.$id));return;}
                issuers_staff_atomic_effects($result,$id);redirect(admin_url('staff/member/'.$id));return;
            }

            if ($id == '') {
                if (staff_cant('create', 'staff')) {
                    access_denied('staff');
                }
                try { $id = $this->staff_model->add($data); }
                catch (Throwable $error) { set_alert('warning', StaffIdentityTransaction::publicMessage($error)); redirect(admin_url('staff/member')); return; }
                if ($id) {
                    handle_staff_profile_image_upload($id);
                    set_alert('success', _l('added_successfully', _l('staff_member')));
                    if (function_exists('issuers_apply_staff_access')) { issuers_apply_staff_access($issuerAccess, $id); }
                    redirect(admin_url('staff/member/' . $id));
                }
            } else {
                if (staff_cant('edit', 'staff')) {
                    access_denied('staff');
                }
                try { $response = $this->staff_model->update($data, $id); }
                catch (Throwable $error) { set_alert('warning', StaffIdentityTransaction::publicMessage($error)); redirect(admin_url('staff/member/'.$id)); return; }
                if (!is_array($response)) { handle_staff_profile_image_upload($id); }
                if (is_array($response)) {
                    if (isset($response['cant_remove_installation_master'])) {
                        set_alert('warning', _l('issuer_master_protected'));
                    } elseif (isset($response['cant_remove_main_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_main_admin'));
                    } elseif (isset($response['cant_remove_yourself_from_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_yourself_from_admin'));
                    }
                } elseif ($response == true) {
                    set_alert('success', _l('updated_successfully', _l('staff_member')));
                }
                if (!is_array($response) && function_exists('issuers_apply_staff_access')) { issuers_apply_staff_access($issuerAccess, $id); }
                redirect(admin_url('staff/member/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('staff_member'));
        } else {
            $member = $this->staff_model->get($id);
            if (!$member) {
                blank_page('Staff Member Not Found', 'danger');
            }
            $data['member']            = $member;
            $title                     = $member->firstname . ' ' . $member->lastname;
            $data['staff_departments'] = $this->departments_model->get_staff_departments($member->staffid);

            $ts_filter_data = [];
            if ($this->input->get('filter')) {
                if ($this->input->get('range') != 'period') {
                    $ts_filter_data[$this->input->get('range')] = true;
                } else {
                    $ts_filter_data['period-from'] = $this->input->get('period-from');
                    $ts_filter_data['period-to']   = $this->input->get('period-to');
                }
            } else {
                $ts_filter_data['this_month'] = true;
            }

            $data['logged_time'] = $this->staff_model->get_logged_time_data($id, $ts_filter_data);
            $data['timesheets']  = $data['logged_time']['timesheets'];
        }
        $this->load->model('currencies_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['roles']         = $this->roles_model->get();
        $data['user_notes']    = $this->misc_model->get_notes($id, 'staff');
        $data['departments']   = $this->departments_model->get();
        $data['title']         = $title;

        $this->load->view('admin/staff/member', $data);
    }

    /* Get role permission for specific role id */
    public function role_changed($id)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(NativeCompanyStaffEndpoints::roleChanged($this,$id),JSON_THROW_ON_ERROR));return;
        }
        if (staff_cant('view', 'staff')) {
            ajax_access_denied('staff');
        }

        echo json_encode($this->roles_model->get($id)->permissions);
    }

    private function nativeDashboardPreference(string $part): bool
    {
        if(!class_exists('CompanyRequestScope',false)||!CompanyRequestScope::nativeFor($this))return false;
        try{
            CompanyRequestScope::assertNativeMutation($this);$ctx=CompanyRequestScope::context();
            if($ctx->issuerId()===null)throw new DomainException('native_dashboard_preferences_denied');
            require_once APPPATH.'services/NativeCompanyDashboardPreferences.php';
            $input=$this->input->post();if(!is_array($input))$input=[];
            unset($input[$this->security->get_csrf_token_name()]);
            if($part==='visibility'){if(array_diff(array_keys($input),['widgets'])||!is_array($input['widgets']??null))throw new DomainException('native_dashboard_preferences_invalid');$input=$input['widgets'];}
            if($part==='reset'&&$input)throw new DomainException('native_dashboard_preferences_invalid');
            (new NativeCompanyDashboardPreferences($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId()))->save($part,$input);
            $this->output->set_header('Cache-Control: no-store');$this->output->set_content_type('application/json')->set_output('{"success":true}');
        }catch(DomainException $error){show_error('Não foi possível salvar as preferências desta empresa.',403);}
        return true;
    }

    public function save_dashboard_widgets_order()
    {
        if($this->nativeDashboardPreference('order'))return;
        hooks()->do_action('before_save_dashboard_widgets_order');

        $post_data = $this->input->post();
        foreach ($post_data as $container => $widgets) {
            if ($widgets == 'empty') {
                $post_data[$container] = [];
            }
        }
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_order', serialize($post_data));
    }

    public function save_dashboard_widgets_visibility()
    {
        if($this->nativeDashboardPreference('visibility'))return;
        hooks()->do_action('before_save_dashboard_widgets_visibility');

        $post_data = $this->input->post();
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility', serialize($post_data['widgets']));
    }

    public function reset_dashboard()
    {
        if($this->nativeDashboardPreference('reset'))return;
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility', null);
        update_staff_meta(get_staff_user_id(), 'dashboard_widgets_order', null);

        redirect(admin_url());
    }

    public function save_hidden_table_columns()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';NativeCompanyStaffEndpoints::hiddenColumns($this);$this->output->set_header('Cache-Control: no-store');return;
        }
        hooks()->do_action('before_save_hidden_table_columns');
        $data   = $this->input->post();
        $id     = $data['id'];
        $hidden = isset($data['hidden']) ? $data['hidden'] : [];
        update_staff_meta(get_staff_user_id(), 'hidden-columns-' . $id, json_encode($hidden));
    }

    public function change_language($lang = '')
    {
        $lang = crm_ui_language();
        hooks()->do_action('before_staff_change_language', $lang);

        $this->db->where('staffid', get_staff_user_id());
        $this->db->update(db_prefix() . 'staff', ['default_language' => $lang]);
        
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function timesheets()
    {
        $data['view_all'] = false;
        if (staff_can('view-timesheets', 'reports') && $this->input->get('view') == 'all') {
            $data['staff_members_with_timesheets'] = $this->db->query('SELECT DISTINCT staff_id FROM ' . db_prefix() . 'taskstimers WHERE staff_id !=' . get_staff_user_id())->result_array();
            $data['view_all']                      = true;
        }

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('staff_timesheets', ['view_all' => $data['view_all']]);
        }

        if ($data['view_all'] == false) {
            unset($data['view_all']);
        }

        $data['logged_time'] = $this->staff_model->get_logged_time_data(get_staff_user_id());
        $data['title']       = '';
        $this->load->view('admin/staff/timesheets', $data);
    }

    public function delete()
    {
        if (!is_admin() && is_admin($this->input->post('id'))) {
            die('Busted, you can\'t delete administrators');
        }

        if (staff_can('delete',  'staff')) {
            try { $success = $this->staff_model->delete($this->input->post('id'), $this->input->post('transfer_data_to')); }
            catch (DomainException $error) {
                if ($error->getMessage() !== 'issuer_master_protected') { throw $error; }
                set_alert('warning', _l('issuer_master_protected'));
                redirect(admin_url('staff'));
                return;
            }
            if ($success) {
                set_alert('success', _l('deleted', _l('staff_member')));
            }
        }

        redirect(admin_url('staff'));
    }

    /* When staff edit his profile */
    public function edit_profile()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
            if(strtoupper((string)$this->input->method())==='POST'){
                try{NativeCompanyStaffEndpoints::ownSave($this);set_alert('success',_l('staff_profile_updated'));}
                catch(DomainException $error){set_alert('warning',_l('issuer_staff_profile_field_denied'));}
                redirect(admin_url('staff/edit_profile'));return;
            }
            $data=NativeCompanyStaffEndpoints::ownPage($this);$data['title']=$data['member']->firstname.' '.$data['member']->lastname;
            $this->output->set_header('Cache-Control: no-store');$this->load->view('admin/staff/profile',$data);return;
        }

        hooks()->do_action('edit_logged_in_staff_profile');

        if ($this->input->post()) {
            $data = $this->input->post();
            $issuerAccess = null;
            if (function_exists('issuers_prepare_staff_access')) {
                try { $issuerAccess = issuers_prepare_staff_access($data, get_staff_user_id(), true); }
                catch (Throwable $error) {
                    set_alert('warning', _l($error instanceof DomainException ? $error->getMessage() : 'issuer_storage_failed'));
                    redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
                    return;
                }
            }
            // Don't do XSS clean here.
            $data['email_signature'] = $this->input->post('email_signature', false);
            $data['email_signature'] = html_entity_decode($data['email_signature']);

            if ($data['email_signature'] == strip_tags($data['email_signature'])) {
                // not contains HTML, add break lines
                $data['email_signature'] = nl2br_save_html($data['email_signature']);
            }

            if($issuerAccess!==null){
                try{$result=issuers_save_staff_atomically($this->staff_model,'update_profile',$data,get_staff_user_id(),$issuerAccess);}
                catch(Throwable $error){issuers_staff_atomic_error($error);redirect(admin_url('staff/edit_profile/'.get_staff_user_id()));return;}
                issuers_staff_atomic_effects($result,get_staff_user_id());redirect(admin_url('staff/edit_profile/'.get_staff_user_id()));return;
            }
            try { $success = $this->staff_model->update_profile($data, get_staff_user_id()); }
            catch (Throwable $error) {
                set_alert('warning', $error->getMessage() === 'issuer_staff_profile_field_denied' ? _l('issuer_staff_profile_field_denied') : StaffIdentityTransaction::publicMessage($error));
                redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
                return;
            }

            handle_staff_profile_image_upload();
            if ($success) {
                set_alert('success', _l('staff_profile_updated'));
            }

            if (function_exists('issuers_apply_staff_access')) { issuers_apply_staff_access($issuerAccess, get_staff_user_id()); }
            redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
        }
        $member = $this->staff_model->get(get_staff_user_id());
        $this->load->model('departments_model');
        $data['member']            = $member;
        $data['departments']       = $this->departments_model->get();
        $data['staff_departments'] = $this->departments_model->get_staff_departments($member->staffid);
        $data['title']             = $member->firstname . ' ' . $member->lastname;
        $this->load->view('admin/staff/profile', $data);
    }

    /* Remove staff profile image / ajax */
    public function remove_staff_profile_image($id = '')
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';$revision=NativeCompanyStaffEndpoints::removeAvatar($this,$id);
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'revision'=>$revision],JSON_THROW_ON_ERROR));return;
        }

        $staff_id = get_staff_user_id();
        if (is_numeric($id) && (staff_can('create',  'staff') || staff_can('edit',  'staff'))) {
            $staff_id = $id;
        }
        hooks()->do_action('before_remove_staff_profile_image');
        $member = $this->staff_model->get($staff_id);
        if (file_exists(get_upload_path_by_type('staff') . $staff_id)) {
            delete_dir(get_upload_path_by_type('staff') . $staff_id);
        }
        $this->db->where('staffid', $staff_id);
        $this->db->update(db_prefix() . 'staff', [
            'profile_image' => null,
        ]);

        if (!is_numeric($id)) {
            redirect(admin_url('staff/edit_profile/' . $staff_id));
        } else {
            redirect(admin_url('staff/member/' . $staff_id));
        }
    }

    /* When staff change his password */
    public function change_password_profile()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
            try{NativeCompanyStaffEndpoints::ownPassword($this);set_alert('success',_l('staff_password_changed'));}
            catch(DomainException $error){set_alert('warning',_l($error->getMessage()==='native_staff_old_password_invalid'?'staff_old_password_incorrect':'staff_problem_changing_password'));}
            redirect(admin_url('staff/edit_profile'));return;
        }

        if ($this->input->post()) {
            $response = $this->staff_model->change_password($this->input->post(null, false), get_staff_user_id());
            if (is_array($response) && isset($response[0]['passwordnotmatch'])) {
                set_alert('danger', _l('staff_old_password_incorrect'));
            } else {
                if ($response == true) {
                    set_alert('success', _l('staff_password_changed'));
                } else {
                    set_alert('warning', _l('staff_problem_changing_password'));
                }
            }
            redirect(admin_url('staff/edit_profile'));
        }
    }

    /* View public profile. If id passed view profile by staff id else current user*/
    public function profile($id = '')
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffProfile.php';$c=CompanyRequestScope::context();if((int)get_staff_user_id()!==$c->staffId())throw new DomainException('issuer_staff_denied');$subject=$id===''?$c->staffId():IssuerPolicy::id($id);$data=(new NativeCompanyStaffProfile($this->db,db_prefix()))->page($c->issuerId(),$c->staffId(),$subject);$data['title']=_l('staff_profile_string').' - '.$data['staff_p']->firstname.' '.$data['staff_p']->lastname;$this->output->set_header('Cache-Control: no-store');$this->load->view('admin/staff/myprofile',$data);return;
        }

        if ($id == '') {
            $id = get_staff_user_id();
        }

        hooks()->do_action('staff_profile_access', $id);

        $data['logged_time'] = $this->staff_model->get_logged_time_data($id);
        $data['staff_p']     = $this->staff_model->get($id);

        if (!$data['staff_p']) {
            blank_page('Staff Member Not Found', 'danger');
        }

        $this->load->model('departments_model');
        $data['staff_departments'] = $this->departments_model->get_staff_departments($data['staff_p']->staffid);
        $data['departments']       = $this->departments_model->get();
        $data['title']             = _l('staff_profile_string') . ' - ' . $data['staff_p']->firstname . ' ' . $data['staff_p']->lastname;
        // notifications
        $total_notifications = total_rows(db_prefix() . 'notifications', [
            'touserid' => get_staff_user_id(),
        ]);
        $data['total_pages'] = ceil($total_notifications / $this->misc_model->get_notifications_limit());
        $this->load->view('admin/staff/myprofile', $data);
    }

    /* Change status to staff active or inactive / ajax */
    public function change_staff_status($id, $status)
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';$result=NativeCompanyStaffEndpoints::status($this,$id,$status);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($result));return;}
        if (staff_can('edit',  'staff')) {
            if ($this->input->is_ajax_request()) {
                try { $this->staff_model->change_staff_status($id, $status); }
                catch (DomainException $error) {
                    if ($error->getMessage() !== 'issuer_master_protected') { throw $error; }
                    show_error(_l('issuer_master_protected'), 403);
                    return;
                }
            }
        }
    }

    /* Logged in staff notifications*/
    public function notifications()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){require_once APPPATH.'services/NativeCompanyStaffProfile.php';$result=NativeCompanyStaffProfile::notifications($this);$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode($result));return;}

        $this->load->model('misc_model');
        if ($this->input->post()) {
            $page   = $this->input->post('page');
            $offset = ($page * $this->misc_model->get_notifications_limit());
            $this->db->limit($this->misc_model->get_notifications_limit(), $offset);
            $this->db->where('touserid', get_staff_user_id());
            $this->db->order_by('date', 'desc');
            $notifications = $this->db->get(db_prefix() . 'notifications')->result_array();
            $i             = 0;
            foreach ($notifications as $notification) {
                if (($notification['fromcompany'] == null && $notification['fromuserid'] != 0) || ($notification['fromcompany'] == null && $notification['fromclientid'] != 0)) {
                    if ($notification['fromuserid'] != 0) {
                        $notifications[$i]['profile_image'] = '<a href="' . admin_url('staff/profile/' . $notification['fromuserid']) . '">' . staff_profile_image($notification['fromuserid'], [
                            'staff-profile-image-small',
                            'img-circle',
                            'pull-left',
                        ]) . '</a>';
                    } else {
                        $notifications[$i]['profile_image'] = '<a href="' . admin_url('clients/client/' . $notification['fromclientid']) . '">
                    <img class="client-profile-image-small img-circle pull-left" src="' . contact_profile_image_url($notification['fromclientid']) . '"></a>';
                    }
                } else {
                    $notifications[$i]['profile_image'] = '';
                    $notifications[$i]['full_name']     = '';
                }
                $additional_data = '';
                if (!empty($notification['additional_data'])) {
                    $additional_data = unserialize($notification['additional_data']);
                    $x               = 0;
                    foreach ($additional_data as $data) {
                        if (strpos($data, '<lang>') !== false) {
                            $lang = get_string_between($data, '<lang>', '</lang>');
                            $temp = _l($lang);
                            if (strpos($temp, 'project_status_') !== false) {
                                $status = get_project_status_by_id(strafter($temp, 'project_status_'));
                                $temp   = $status['name'];
                            }
                            $additional_data[$x] = $temp;
                        }
                        $x++;
                    }
                }
                $notifications[$i]['description'] = _l($notification['description'], $additional_data);
                $notifications[$i]['date']        = time_ago($notification['date']);
                $notifications[$i]['full_date']   = _dt($notification['date']);
                $i++;
            } //$notifications as $notification
            echo json_encode($notifications);
            die;
        }
    }

    public function update_two_factor()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';
            try{NativeCompanyStaffEndpoints::ownTwoFactor($this);set_alert('success',_l('set_two_factor_authentication_successful'));}
            catch(DomainException $error){set_alert('warning',_l('set_two_factor_authentication_failed'));}
            redirect(admin_url('staff/edit_profile'));return;
        }

        $fail_reason = _l('set_two_factor_authentication_failed');
        if ($this->input->post()) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('two_factor_auth', _l('two_factor_auth'), 'required');

            if ($this->input->post('two_factor_auth') == 'google') {
                $this->form_validation->set_rules('google_auth_code', _l('google_authentication_code'), 'required');
            }

            if ($this->form_validation->run() !== false) {
                $two_factor_auth_mode = $this->input->post('two_factor_auth');
                $id = get_staff_user_id();
                if ($two_factor_auth_mode == 'google') {
                    $this->load->model('Authentication_model');
                    $secret = $this->input->post('secret');
                    $success = $this->authentication_model->set_google_two_factor($secret);
                    $fail_reason = _l('set_google_two_factor_authentication_failed');
                } elseif ($two_factor_auth_mode == 'email') {
                    $this->db->where('staffid', $id);
                    $success = $this->db->update(db_prefix() . 'staff', ['two_factor_auth_enabled' => 1]);
                } else {
                    $this->db->where('staffid', $id);
                    $success = $this->db->update(db_prefix() . 'staff', ['two_factor_auth_enabled' => 0]);
                }
                if ($success) {
                    set_alert('success', _l('set_two_factor_authentication_successful'));
                    redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
                }
            }
        }
        set_alert('danger', $fail_reason);
        redirect(admin_url('staff/edit_profile/' . get_staff_user_id()));
    }

    public function verify_google_two_factor()
    {
        if(class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyStaffEndpoints.php';$valid=NativeCompanyStaffEndpoints::verifyGoogleSetup($this);
            $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['status'=>$valid?'success':'failed','message'=>_l($valid?'google_2fa_code_valid':'google_2fa_code_invalid')],JSON_THROW_ON_ERROR));return;
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
            die;
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('authentication_model');
            $is_success = $this->authentication_model->is_google_two_factor_code_valid($data['code'],$data['secret']);
            $result = [];

            header('Content-Type: application/json');
            if ($is_success) {
                $result['status'] = 'success';
                $result['message'] = _l('google_2fa_code_valid');;

                echo json_encode($result);
                die;
            }

            $result['status'] = 'failed';
            $result['message'] = _l('google_2fa_code_invalid');;

            echo json_encode($result);
            die;
        }
    }

    public function save_completed_checklist_visibility()
    {
        hooks()->do_action('before_save_completed_checklist_visibility');

        $post_data = $this->input->post();
        if (is_numeric($post_data['task_id'])) {
            update_staff_meta(get_staff_user_id(), 'task-hide-completed-items-'. $post_data['task_id'], $post_data['hideCompleted']);
        }
    }
}
