<?php

use app\services\InvoiceIssuerScope;

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/CompanyNativeStaffTransaction.php';

class Staff_model extends App_Model
{
    /** Called only when the native form contains validated company assignments/default. */
    public function save_with_company_access(string $mode,array $data,$id,array $prepared,array $resources):array
    {
        if(array_key_exists('role',$data)&&$data['role']===''){$data['role']=0;}
        return CompanyNativeStaffTransaction::run($this->db,db_prefix(),(int)get_staff_user_id(),$mode,$id,$prepared,$resources,
            fn()=>$mode==='add'?$this->add($data):($mode==='update'?$this->update($data,$id):$this->update_profile($data,$id)));
    }
    private function issuer_staff_after_commit(callable $effect):void
    {
        CompanyNativeStaffTransaction::afterCommit($this->db,$effect);
    }
    private function issuer_staff_departments($id,array $departments):void
    {
        $expected=array_map(static fn($value)=>['departmentid'=>$value],$departments);
        usort($expected,static fn($a,$b)=>(int)$a['departmentid']<=>(int)$b['departmentid']);
        CompanyNativeStaffTransaction::expect($this->db,'departments:'.$id,'SELECT departmentid FROM '.db_prefix().'staff_departments WHERE staffid=? ORDER BY departmentid',[$id],$expected);
    }
    private function issuer_installation_master($id): bool
    {
        require_once APPPATH . 'services/issuers/IssuerResourceAccess.php';
        return (new IssuerResourceAccess($this->db, db_prefix()))->isInstallationMaster($id);
    }

    /** Staff deletion cannot transfer financial attribution across ownership scopes. */
    private function has_named_invoice_reference($staffId): bool
    {
        if (!InvoiceIssuerScope::schemaReady($this->db, db_prefix() . 'invoices')) { return false; }
        $result = $this->db->query('SELECT id FROM ' . db_prefix() . 'invoices WHERE issuer_id IS NOT NULL AND (addedfrom=? OR sale_agent=? OR id IN (SELECT invoice_id FROM ' . db_prefix() . 'credits WHERE staff_id=?)) LIMIT 1', [$staffId, $staffId, $staffId]);
        return $result === false || $result->row() !== null;

    }

    public function delete($id, $transfer_data_to)
    {
        if ($this->issuer_installation_master($id)) { throw new DomainException('issuer_master_protected'); }
        if (!is_numeric($transfer_data_to)) {
            return false;
        }

        if ($id == $transfer_data_to) {
            return false;
        }

        if ($this->has_named_invoice_reference($id)) { return false; }
        hooks()->do_action('before_delete_staff_member', [
            'id'               => $id,
            'transfer_data_to' => $transfer_data_to,
        ]);
        if ($this->has_named_invoice_reference($id)) { return false; }

        $name           = get_staff_full_name($id);
        $transferred_to = get_staff_full_name($transfer_data_to);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'estimates', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('sale_agent', $id);
        $this->db->update(db_prefix() . 'estimates', [
            'sale_agent' => $transfer_data_to,
        ]);

        InvoiceIssuerScope::applyLegacy($this->db, db_prefix() . 'invoices');
        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'invoices', [
            'addedfrom' => $transfer_data_to,
        ]);

        InvoiceIssuerScope::applyLegacy($this->db, db_prefix() . 'invoices');
        $this->db->where('sale_agent', $id);
        $this->db->update(db_prefix() . 'invoices', [
            'sale_agent' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'expenses', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'notes', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'newsfeed_post_comments', [
            'userid' => $transfer_data_to,
        ]);

        $this->db->where('creator', $id);
        $this->db->update(db_prefix() . 'newsfeed_posts', [
            'creator' => $transfer_data_to,
        ]);

        $this->db->where('staff_id', $id);
        $this->db->update(db_prefix() . 'projectdiscussions', [
            'staff_id' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'projects', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'creditnotes', [
            'addedfrom' => $transfer_data_to,
        ]);

        if (InvoiceIssuerScope::schemaReady($this->db, db_prefix() . 'invoices')) {
            $this->db->where('invoice_id IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE ' . InvoiceIssuerScope::legacySql($this->db, db_prefix() . 'invoices') . ')', null, false);
        }
        $this->db->where('staff_id', $id);
        $this->db->update(db_prefix() . 'credits', [
            'staff_id' => $transfer_data_to,
        ]);

        $this->db->where('staff_id', $id);
        $this->db->update('filters', [
            'staff_id' => $transfer_data_to,
        ]);
        
        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'project_files', [
            'staffid' => $transfer_data_to,
        ]);

        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'proposal_comments', [
            'staffid' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'proposals', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'templates', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'task_comments', [
            'staffid' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->where('is_added_from_contact', 0);
        $this->db->update(db_prefix() . 'tasks', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'files', [
            'staffid' => $transfer_data_to,
        ]);

        $this->db->where('renewed_by_staff_id', $id);
        $this->db->update(db_prefix() . 'contract_renewals', [
            'renewed_by_staff_id' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'task_checklist_items', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('assigned', $id);
        $this->db->update(db_prefix() . 'task_checklist_items', [
            'assigned' => $transfer_data_to,
        ]);

        $this->db->where('finished_from', $id);
        $this->db->update(db_prefix() . 'task_checklist_items', [
            'finished_from' => $transfer_data_to,
        ]);

        $this->db->where('admin', $id);
        $this->db->update(db_prefix() . 'ticket_replies', [
            'admin' => $transfer_data_to,
        ]);

        $this->db->where('admin', $id);
        $this->db->update(db_prefix() . 'tickets', [
            'admin' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'leads', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('assigned', $id);
        $this->db->update(db_prefix() . 'leads', [
            'assigned' => $transfer_data_to,
        ]);

        $this->db->where('staff_id', $id);
        $this->db->update(db_prefix() . 'taskstimers', [
            'staff_id' => $transfer_data_to,
        ]);

        $this->db->where('addedfrom', $id);
        $this->db->update(db_prefix() . 'contracts', [
            'addedfrom' => $transfer_data_to,
        ]);

        $this->db->where('assigned_from', $id);
        $this->db->where('is_assigned_from_contact', 0);
        $this->db->update(db_prefix() . 'task_assigned', [
            'assigned_from' => $transfer_data_to,
        ]);

        $this->db->where('responsible', $id);
        $this->db->update(db_prefix() . 'leads_email_integration', [
            'responsible' => $transfer_data_to,
        ]);

        $this->db->where('responsible', $id);
        $this->db->update(db_prefix() . 'web_to_lead', [
            'responsible' => $transfer_data_to,
        ]);

        $this->db->where('responsible', $id);
        $this->db->update(db_prefix() . 'estimate_request_forms', [
            'responsible' => $transfer_data_to,
        ]);

        $this->db->where('assigned', $id);
        $this->db->update(db_prefix() . 'estimate_requests', [
            'assigned' => $transfer_data_to,
        ]);

        $this->db->where('created_from', $id);
        $this->db->update(db_prefix() . 'subscriptions', [
            'created_from' => $transfer_data_to,
        ]);

        $this->db->where('notify_type', 'specific_staff');
        $web_to_lead = $this->db->get(db_prefix() . 'web_to_lead')->result_array();

        foreach ($web_to_lead as $form) {
            if (!empty($form['notify_ids'])) {
                $staff = unserialize($form['notify_ids']);
                if (is_array($staff) && in_array($id, $staff) && ($key = array_search($id, $staff)) !== false) {
                    unset($staff[$key]);
                    $staff = serialize(array_values($staff));
                    $this->db->where('id', $form['id']);
                    $this->db->update(db_prefix() . 'web_to_lead', [
                        'notify_ids' => $staff,
                    ]);
                }
            }
        }

        $this->db->where('notify_type', 'specific_staff');
        $estimate_requests = $this->db->get(db_prefix() . 'estimate_request_forms')->result_array();

        foreach ($estimate_requests as $form) {
            if (!empty($form['notify_ids'])) {
                $staff = unserialize($form['notify_ids']);
                if (is_array($staff) && in_array($id, $staff) && ($key = array_search($id, $staff)) !== false) {
                    unset($staff[$key]);
                    $staff = serialize(array_values($staff));
                    $this->db->where('id', $form['id']);
                    $this->db->update(db_prefix() . 'estimate_request_forms', [
                        'notify_ids' => $staff,
                    ]);
                }
            }
        }


        $this->db->where('id', 1);
        $leads_email_integration = $this->db->get(db_prefix() . 'leads_email_integration')->row();

        if ($leads_email_integration->notify_type == 'specific_staff') {
            if (!empty($leads_email_integration->notify_ids)) {
                $staff = unserialize($leads_email_integration->notify_ids);
                if (is_array($staff) && in_array($id, $staff) && ($key = array_search($id, $staff)) !== false) {
                    unset($staff[$key]);
                    $staff = serialize(array_values($staff));
                    $this->db->where('id', 1);
                    $this->db->update(db_prefix() . 'leads_email_integration', [
                        'notify_ids' => $staff,
                    ]);
                }
            }
        }

        $this->db->where('assigned', $id);
        $this->db->update(db_prefix() . 'tickets', [
            'assigned' => 0,
        ]);

        $this->db->where('staff', 1);
        $this->db->where('userid', $id);
        $this->db->delete(db_prefix() . 'dismissed_announcements');

        $this->db->where('userid', $id);
        $this->db->delete(db_prefix() . 'newsfeed_comment_likes');

        $this->db->where('userid', $id);
        $this->db->delete(db_prefix() . 'newsfeed_post_likes');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'customer_admins');

        $this->db->where('fieldto', 'staff');
        $this->db->where('relid', $id);
        $this->db->delete(db_prefix() . 'customfieldsvalues');

        $this->db->where('userid', $id);
        $this->db->delete(db_prefix() . 'events');

        $this->db->where('touserid', $id);
        $this->db->delete(db_prefix() . 'notifications');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'user_meta');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'project_members');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'project_notes');

        $this->db->where('creator', $id);
        $this->db->or_where('staff', $id);
        $this->db->delete(db_prefix() . 'reminders');

        $this->db->where('staffid', $id);
        $this->db->delete(db_prefix() . 'staff_departments');

        $this->db->where('staffid', $id);
        $this->db->delete(db_prefix() . 'todos');

        $this->db->where('staff', 1);
        $this->db->where('user_id', $id);
        $this->db->delete(db_prefix() . 'user_auto_login');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'staff_permissions');

        $this->db->where('staffid', $id);
        $this->db->delete(db_prefix() . 'task_assigned');

        $this->db->where('staffid', $id);
        $this->db->delete(db_prefix() . 'task_followers');

        $this->db->where('staff_id', $id);
        $this->db->delete(db_prefix() . 'pinned_projects');

        $this->db->where('staffid', $id);
        $this->db->delete(db_prefix() . 'staff');
        log_activity('Staff Member Deleted [Name: ' . $name . ', Data Transferred To: ' . $transferred_to . ']');

        hooks()->do_action('staff_member_deleted', [
            'id'               => $id,
            'transfer_data_to' => $transfer_data_to,
        ]);

        return true;
    }

    /**
     * Get staff member/s
     * @param  mixed $id Optional - staff id
     * @param  mixed $where where in query
     * @return mixed if id is passed return object else array
     */
    public function get($id = '', $where = [])
    {
        $select_str = '*,CONCAT(firstname,\' \',lastname) as full_name';

        // Used to prevent multiple queries on logged in staff to check the total unread notifications in core/AdminController.php
        if (is_staff_logged_in() && $id != '' && $id == get_staff_user_id()) {
            $select_str .= ',(SELECT COUNT(*) FROM ' . db_prefix() . 'notifications WHERE touserid=' . get_staff_user_id() . ' and isread=0) as total_unread_notifications, (SELECT COUNT(*) FROM ' . db_prefix() . 'todos WHERE finished=0 AND staffid=' . get_staff_user_id() . ') as total_unfinished_todos';
        }

        $this->db->select($select_str);
        $this->db->where($where);

        if (is_numeric($id)) {
            $this->db->where('staffid', $id);
            $staff = $this->db->get(db_prefix() . 'staff')->row();

            if ($staff) {
                $staff->permissions = $this->get_staff_permissions($id);
            }

            return $staff;
        }
        $this->db->order_by('firstname', 'desc');

        return $this->db->get(db_prefix() . 'staff')->result_array();
    }

    /**
     * Get staff permissions
     * @param  mixed $id staff id
     * @return array
     */
    public function get_staff_permissions($id)
    {
        // Fix for version 2.3.1 tables upgrade
        if (defined('DOING_DATABASE_UPGRADE')) {
            return [];
        }

        $permissions = $this->app_object_cache->get('staff-' . $id . '-permissions');

        if (!$permissions && !is_array($permissions)) {
            $this->db->where('staff_id', $id);
            $permissions = $this->db->get('staff_permissions')->result_array();

            $this->app_object_cache->add('staff-' . $id . '-permissions', $permissions);
        }

        return $permissions;
    }

    /**
     * Add new staff member
     * @param array $data staff $_POST data
     */
    public function add($data)
    {
        if (isset($data['fakeusernameremembered'])) {
            unset($data['fakeusernameremembered']);
        }
        if (isset($data['fakepasswordremembered'])) {
            unset($data['fakepasswordremembered']);
        }

        // First check for all cases if the email exists.
        $data = hooks()->apply_filters('before_create_staff_member', $data);

        $this->db->where('email', $data['email']);
        $email = $this->db->get(db_prefix() . 'staff')->row();

        if ($email) {
            if(CompanyNativeStaffTransaction::active($this->db)){throw new DomainException('issuer_staff_email_conflict');}
            die('Email already exists');
        }

        $data['admin'] = 0;

        if (is_admin()) {
            if (isset($data['administrator'])) {
                $data['admin'] = 1;
                unset($data['administrator']);
            }
        }

        $send_welcome_email = true;
        $original_password  = $data['password'];
        if (!isset($data['send_welcome_email'])) {
            $send_welcome_email = false;
        } else {
            unset($data['send_welcome_email']);
        }

        $data['password']    = app_hash_password($data['password']);
        $data['datecreated'] = date('Y-m-d H:i:s');
        if (isset($data['departments'])) {
            $departments = $data['departments'];
            unset($data['departments']);
        }

        $permissions = [];
        if (isset($data['permissions'])) {
            $permissions = $data['permissions'];
            unset($data['permissions']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        if ($data['admin'] == 1) {
            $data['is_not_staff'] = 0;
        }

        $this->db->insert(db_prefix() . 'staff', $data);
        $staffid = $this->db->insert_id();
        if(CompanyNativeStaffTransaction::active($this->db)&&!$staffid){throw new RuntimeException('issuer_staff_write_unconfirmed');}
        if ($staffid) {
            CompanyNativeStaffTransaction::profile($this->db,db_prefix(),(int)$staffid,$data);
            $slug = $data['firstname'] . ' ' . $data['lastname'];

            if ($slug == ' ') {
                $slug = 'unknown-' . $staffid;
            }

            if ($send_welcome_email == true) {
                $this->issuer_staff_after_commit(fn()=>send_mail_template('staff_created', $data['email'], $staffid, $original_password));
            }

            $this->db->where('staffid', $staffid);
            $this->db->update(db_prefix() . 'staff', [
                'media_path_slug' => slug_it($slug),
            ]);
            CompanyNativeStaffTransaction::profile($this->db,db_prefix(),(int)$staffid,['media_path_slug'=>slug_it($slug)]);

            if (isset($custom_fields)) {
                handle_custom_fields_post($staffid, $custom_fields);
            }
            if (isset($departments)) {
                foreach ($departments as $department) {
                    $this->db->insert(db_prefix() . 'staff_departments', [
                        'staffid'      => $staffid,
                        'departmentid' => $department,
                    ]);
                }
            }
            $this->issuer_staff_departments($staffid,$departments??[]);

            // Delete all staff permission if is admin we dont need permissions stored in database (in case admin check some permissions)
            $this->update_permissions($data['admin'] == 1 ? [] : $permissions, $staffid);

            $this->issuer_staff_after_commit(fn()=>log_activity('New Staff Member Added [ID: ' . $staffid . ', ' . $data['firstname'] . ' ' . $data['lastname'] . ']'));

            // Get all announcements and set it to read.
            $this->db->select('announcementid');
            $this->db->from(db_prefix() . 'announcements');
            $this->db->where('showtostaff', 1);
            $announcements = $this->db->get()->result_array();
            foreach ($announcements as $announcement) {
                $this->db->insert(db_prefix() . 'dismissed_announcements', [
                    'announcementid' => $announcement['announcementid'],
                    'staff'          => 1,
                    'userid'         => $staffid,
                ]);
            }
            $expected=array_map(static fn($row)=>['announcementid'=>$row['announcementid']],$announcements);usort($expected,static fn($a,$b)=>(int)$a['announcementid']<=>(int)$b['announcementid']);
            CompanyNativeStaffTransaction::expect($this->db,'announcements:'.$staffid,'SELECT announcementid FROM '.db_prefix().'dismissed_announcements WHERE staff=1 AND userid=? ORDER BY announcementid',[$staffid],$expected);
            $this->issuer_staff_after_commit(fn()=>hooks()->do_action('staff_member_created', $staffid));

            return $staffid;
        }

        return false;
    }

    /**
     * Update staff member info
     * @param  array $data staff data
     * @param  mixed $id   staff id
     * @return boolean
     */
    public function update($data, $id)
    {
        if (isset($data['fakeusernameremembered'])) {
            unset($data['fakeusernameremembered']);
        }
        if (isset($data['fakepasswordremembered'])) {
            unset($data['fakepasswordremembered']);
        }

        $data = hooks()->apply_filters('before_update_staff_member', $data, $id);

        if (is_admin()) {
            if (isset($data['administrator'])) {
                $data['admin'] = 1;
                unset($data['administrator']);
            } else {
                if ($id != get_staff_user_id()) {
                    if ($id == 1) {
                        return [
                            'cant_remove_main_admin' => true,
                        ];
                    }
                } else {
                    return [
                        'cant_remove_yourself_from_admin' => true,
                    ];
                }
                $data['admin'] = 0;
            }
        }

        if ($this->issuer_installation_master($id)
            && ((array_key_exists('admin', $data) && (string) $data['admin'] !== '1')
                || (array_key_exists('active', $data) && (string) $data['active'] !== '1'))) {
            return ['cant_remove_installation_master' => true];
        }

        $affectedRows = 0;
        if (isset($data['departments'])) {
            $departments = $data['departments'];
            unset($data['departments']);
        }

        $permissions = [];
        if (isset($data['permissions'])) {
            $permissions = $data['permissions'];
            unset($data['permissions']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password']             = app_hash_password($data['password']);
            $data['last_password_change'] = date('Y-m-d H:i:s');
        }


        // if (isset($data['two_factor_auth_enabled'])) {
        //     $data['two_factor_auth_enabled'] = 1;
        // } else {
        //     $data['two_factor_auth_enabled'] = 0;
        // }

        if (isset($data['is_not_staff'])) {
            $data['is_not_staff'] = 1;
        } else {
            $data['is_not_staff'] = 0;
        }

        if (isset($data['admin']) && $data['admin'] == 1) {
            $data['is_not_staff'] = 0;
        }

        $this->load->model('departments_model');
        $staff_departments = $this->departments_model->get_staff_departments($id);
        if (sizeof($staff_departments) > 0) {
            if (!isset($data['departments'])) {
                $this->db->where('staffid', $id);
                $this->db->delete(db_prefix() . 'staff_departments');
            } else {
                foreach ($staff_departments as $staff_department) {
                    if (isset($departments)) {
                        if (!in_array($staff_department['departmentid'], $departments)) {
                            $this->db->where('staffid', $id);
                            $this->db->where('departmentid', $staff_department['departmentid']);
                            $this->db->delete(db_prefix() . 'staff_departments');
                            if ($this->db->affected_rows() > 0) {
                                $affectedRows++;
                            }
                        }
                    }
                }
            }
            if (isset($departments)) {
                foreach ($departments as $department) {
                    $this->db->where('staffid', $id);
                    $this->db->where('departmentid', $department);
                    $_exists = $this->db->get(db_prefix() . 'staff_departments')->row();
                    if (!$_exists) {
                        $this->db->insert(db_prefix() . 'staff_departments', [
                            'staffid'      => $id,
                            'departmentid' => $department,
                        ]);
                        if ($this->db->affected_rows() > 0) {
                            $affectedRows++;
                        }
                    }
                }
            }
        } else {
            if (isset($departments)) {
                foreach ($departments as $department) {
                    $this->db->insert(db_prefix() . 'staff_departments', [
                        'staffid'      => $id,
                        'departmentid' => $department,
                    ]);
                    if ($this->db->affected_rows() > 0) {
                        $affectedRows++;
                    }
                }
            }
        }


        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'staff', $data);

        CompanyNativeStaffTransaction::profile($this->db,db_prefix(),(int)$id,$data);
        $this->issuer_staff_departments($id,$departments??[]);

        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
        }

        if ($this->update_permissions((isset($data['admin']) && $data['admin'] == 1 ? [] : $permissions), $id)) {
            $affectedRows++;
        }

        if ($affectedRows > 0) {
            $this->issuer_staff_after_commit(fn()=>hooks()->do_action('staff_member_updated', $id));
            $this->issuer_staff_after_commit(fn()=>log_activity('Staff Member Updated [ID: ' . $id . ', ' . $data['firstname'] . ' ' . $data['lastname'] . ']'));

            return true;
        }

        return false;
    }

    public function update_permissions($permissions, $id)
    {
        $this->db->where('staff_id', $id);
        $this->db->delete('staff_permissions');

        $is_staff_member = is_staff_member($id);
        $expected=[];

        foreach ($permissions as $feature => $capabilities) {
            foreach ($capabilities as $capability) {

                // Maybe do this via hook.
                if ($feature == 'leads' && !$is_staff_member) {
                    continue;
                }

                $this->db->insert('staff_permissions', ['staff_id' => $id, 'feature' => $feature, 'capability' => $capability]);
                $expected[]=['feature'=>$feature,'capability'=>$capability];
            }
        }
        usort($expected,static fn($a,$b)=>[$a['feature'],$a['capability']]<=>[$b['feature'],$b['capability']]);
        CompanyNativeStaffTransaction::expect($this->db,'permissions:'.$id,'SELECT feature,capability FROM '.db_prefix().'staff_permissions WHERE staff_id=? ORDER BY feature,capability',[$id],$expected);

        return true;
    }

    public function update_profile($data, $id)
    {
        // The self-profile form is not a staff administration writer.
        $profileFields = ['firstname','lastname','email','phonenumber','default_language','direction','facebook','linkedin','skype','email_signature'];
        foreach (['admin','administrator','active','staffid','role','is_not_staff','permissions'] as $protected) {
            if (array_key_exists($protected, $data)) { throw new DomainException('issuer_staff_profile_field_denied'); }
        }
        $data = array_intersect_key($data, array_flip($profileFields));
        $data = hooks()->apply_filters('before_staff_update_profile', $data, $id);
        $data = array_intersect_key($data, array_flip($profileFields));

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password']             = app_hash_password($data['password']);
            $data['last_password_change'] = date('Y-m-d H:i:s');
        }

        unset($data['two_factor_auth_enabled']);
        unset($data['google_auth_secret']);


        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'staff', $data);
        CompanyNativeStaffTransaction::profile($this->db,db_prefix(),(int)$id,$data);
        if ($this->db->affected_rows() > 0) {
            $this->issuer_staff_after_commit(fn()=>hooks()->do_action('staff_member_profile_updated', $id));
            $this->issuer_staff_after_commit(fn()=>log_activity('Staff Profile Updated [Staff: ' . get_staff_full_name($id) . ']'));

            return true;
        }

        return false;
    }

    /**
     * Change staff passwordn
     * @param  mixed $data   password data
     * @param  mixed $userid staff id
     * @return mixed
     */
    public function change_password($data, $userid)
    {
        $data = hooks()->apply_filters('before_staff_change_password', $data, $userid);

        $member = $this->get($userid);
        // CHeck if member is active
        if ($member->active == 0) {
            return [
                [
                    'memberinactive' => true,
                ],
            ];
        }

        // Check new old password
        if (!app_hasher()->CheckPassword($data['oldpassword'], $member->password)) {
            return [
                [
                    'passwordnotmatch' => true,
                ],
            ];
        }

        $data['newpasswordr'] = app_hash_password($data['newpasswordr']);

        $this->db->where('staffid', $userid);
        $this->db->update(db_prefix() . 'staff', [
            'password'             => $data['newpasswordr'],
            'last_password_change' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affected_rows() > 0) {
            log_activity('Staff Password Changed [' . $userid . ']');

            return true;
        }

        return false;
    }

    /**
     * Change staff status / active / inactive
     * @param  mixed $id     staff id
     * @param  mixed $status status(0/1)
     */
    public function change_staff_status($id, $status)
    {
        if ((string) $status !== '1' && $this->issuer_installation_master($id)) { throw new DomainException('issuer_master_protected'); }
        $status = hooks()->apply_filters('before_staff_status_change', $status, $id);
        if ((string) $status !== '1' && $this->issuer_installation_master($id)) { throw new DomainException('issuer_master_protected'); }

        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'staff', [
            'active' => $status,
        ]);

        log_activity('Staff Status Changed [StaffID: ' . $id . ' - Status(Active/Inactive): ' . $status . ']');
        hooks()->do_action('after_staff_status_change', $id);
    }

    public function get_logged_time_data($id = '', $filter_data = [])
    {
        if ($id == '') {
            $id = get_staff_user_id();
        }
        $result['timesheets'] = [];
        $result['total']      = [];
        $result['this_month'] = [];

        $first_day_this_month = date('Y-m-01'); // hard-coded '01' for first day
        $last_day_this_month  = date('Y-m-t 23:59:59');

        $result['last_month'] = [];
        $first_day_last_month = date('Y-m-01', strtotime('-1 MONTH')); // hard-coded '01' for first day
        $last_day_last_month  = date('Y-m-t 23:59:59', strtotime('-1 MONTH'));

        $result['this_week'] = [];
        $first_day_this_week = date('Y-m-d', strtotime('monday this week'));
        $last_day_this_week  = date('Y-m-d 23:59:59', strtotime('sunday this week'));

        $result['last_week'] = [];

        $first_day_last_week = date('Y-m-d', strtotime('monday last week'));
        $last_day_last_week  = date('Y-m-d 23:59:59', strtotime('sunday last week'));

        $this->db->select('task_id,start_time,end_time,staff_id,' . db_prefix() . 'taskstimers.hourly_rate,name,' . db_prefix() . 'taskstimers.id,rel_id,rel_type, billed');
        $this->db->where('staff_id', $id);
        $this->db->join(db_prefix() . 'tasks', db_prefix() . 'tasks.id = ' . db_prefix() . 'taskstimers.task_id', 'left');
        $timers           = $this->db->get(db_prefix() . 'taskstimers')->result_array();
        $_end_time_static = time();

        $filter_period = false;
        if (isset($filter_data['period-from']) && $filter_data['period-from'] != '' && isset($filter_data['period-to']) && $filter_data['period-to'] != '') {
            $filter_period = true;
            $from          = to_sql_date($filter_data['period-from']);
            $from          = date('Y-m-d', strtotime($from));
            $to            = to_sql_date($filter_data['period-to']);
            $to            = date('Y-m-d', strtotime($to));
        }

        foreach ($timers as $timer) {
            $start_date = date('Y-m-d', $timer['start_time']);

            $end_time    = $timer['end_time'];
            $notFinished = false;
            if ($timer['end_time'] == null) {
                $end_time    = $_end_time_static;
                $notFinished = true;
            }

            $total = $end_time - $timer['start_time'];

            $result['total'][]     = $total;
            $timer['total']        = $total;
            $timer['end_time']     = $end_time;
            $timer['not_finished'] = $notFinished;

            if ($start_date >= $first_day_this_month && $start_date <= $last_day_this_month) {
                $result['this_month'][] = $total;
                if (isset($filter_data['this_month']) && $filter_data['this_month'] != '') {
                    $result['timesheets'][$timer['id']] = $timer;
                }
            }
            if ($start_date >= $first_day_last_month && $start_date <= $last_day_last_month) {
                $result['last_month'][] = $total;
                if (isset($filter_data['last_month']) && $filter_data['last_month'] != '') {
                    $result['timesheets'][$timer['id']] = $timer;
                }
            }
            if ($start_date >= $first_day_this_week && $start_date <= $last_day_this_week) {
                $result['this_week'][] = $total;
                if (isset($filter_data['this_week']) && $filter_data['this_week'] != '') {
                    $result['timesheets'][$timer['id']] = $timer;
                }
            }
            if ($start_date >= $first_day_last_week && $start_date <= $last_day_last_week) {
                $result['last_week'][] = $total;
                if (isset($filter_data['last_week']) && $filter_data['last_week'] != '') {
                    $result['timesheets'][$timer['id']] = $timer;
                }
            }

            if ($filter_period == true) {
                if ($start_date >= $from && $start_date <= $to) {
                    $result['timesheets'][$timer['id']] = $timer;
                }
            }
        }
        $result['total']      = array_sum($result['total']);
        $result['this_month'] = array_sum($result['this_month']);
        $result['last_month'] = array_sum($result['last_month']);
        $result['this_week']  = array_sum($result['this_week']);
        $result['last_week']  = array_sum($result['last_week']);

        return $result;
    }
}
