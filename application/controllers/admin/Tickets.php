<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeCompanySpecializedOperations.php';

/**
 * @property Tickets_model $tickets_model
 */
class Tickets extends AdminController
{
    use NativeCompanySpecializedOperations;

    public function ai() { $this->nativeTicketAi(); }
    public function connect_api() { $this->nativeTicketConnectApi(); }
    public function connect() { $this->nativeTicketConnect(); }
    public function __construct()
    {
        parent::__construct();
        if(CompanyRequestScope::nativeFor($this)){
            $method = strtolower((string) $this->router->fetch_method());
            if ($method === 'ticket') {
                // Reading a ticket and marking it read require view access only.
                // Mutating replies or merging still re-authorizes edit in their handler.
                require_once APPPATH . 'services/NativeCompanyTicketDetailController.php';
                new NativeCompanyTicketDetailController($this);
                return;
            }
            if(in_array($method,['bulk_action','change_status_ajax','edit_message','block_sender','update_staff_replying','check_staff_replying','delete_attachment','delete','delete_ticket_reply','update_single_ticket_settings'],true)){$this->nativeTicketLifecycle();return;}
            if(in_array(strtolower((string)$this->router->fetch_method()),['add','ticket_change_data'],true)){$this->nativeTicketPage();return;}
            if(strtolower((string)$this->router->fetch_method())==='index')return;
            if(in_array(strtolower((string)$this->router->fetch_method()),['ai','connect','connect_api'],true))return;
            if(!in_array(strtolower((string)$this->router->fetch_method()),['statuses','status','delete_ticket_status','priorities','priority','delete_priority','services','service','delete_service','predefined_replies','predefined_reply','delete_predefined_reply','get_predefined_reply_ajax'],true))throw new DomainException('native_ticket_adapter_incomplete');
            $this->nativeTicketCatalog();return;
        }
        if (get_option('access_tickets_to_none_staff_members') == 0 && ! is_staff_member()) {
            redirect(admin_url());
        }
        $this->load->model('tickets_model');
    }

    private function nativeTicketResponse(callable $action):void
    {
        try{$action();}
        catch(DomainException $error){
            $reason=$error->getMessage();
            $status=str_contains($reason,'method')?405:(str_contains($reason,'stale')||str_contains($reason,'conflict')||str_contains($reason,'changed')?409:(str_contains($reason,'csrf')||str_contains($reason,'denied')||str_contains($reason,'access')?403:422));
            show_error('Não foi possível confirmar a operação deste chamado nesta empresa. Reabra a tela e confira os dados.',$status);
        }catch(RuntimeException $error){show_error('A operação do chamado não foi confirmada. Recarregue antes de repetir.',503);}
    }

    private function nativeTicketCatalog():NativeCompanyTicketCatalogController
    {require_once APPPATH.'services/NativeCompanyTicketCatalogController.php';return new NativeCompanyTicketCatalogController($this);}

    private function nativeTicketPage():NativeCompanyTicketPageController
    {require_once APPPATH.'services/NativeCompanyTicketPageController.php';return new NativeCompanyTicketPageController($this);}

    private function nativeTicketIndex():NativeCompanyTicketIndexController
    {require_once APPPATH.'services/NativeCompanyTicketIndexController.php';return new NativeCompanyTicketIndexController($this);}

    private function nativeTicketLifecycle():NativeCompanyTicketLifecycleController
    {require_once APPPATH.'services/NativeCompanyTicketLifecycleController.php';return new NativeCompanyTicketLifecycleController($this);}

    public function index($status = '', $userid = '')
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{$this->nativeTicketIndex()->index($status,$userid);}
            catch(DomainException $error){
                if(in_array($error->getMessage(),['native_ticket_access_denied','native_ticket_denied'],true)){show_404();return;}
                show_error('Não foi possível carregar os tickets desta empresa.',422);return;
            }
            return;
        }
        close_setup_menu();

        if (! is_numeric($status)) {
            $status = '';
        }

        $data['table'] = App_table::find('tickets');

        if ($this->input->is_ajax_request()) {
            if (! $this->input->post('via_ticket')) {
                $tableParams = [
                    'status' => $status,
                    'userid' => $userid,
                ];
            } else {
                // request for othes tickets when single ticket is opened
                $tableParams = [
                    'userid'     => $this->input->post('via_ticket_userid'),
                    'via_ticket' => $this->input->post('via_ticket'),
                ];

                if ($tableParams['userid'] == 0) {
                    unset($tableParams['userid']);
                    $tableParams['by_email'] = $this->input->post('via_ticket_email');
                }
            }
            $data['table']->output($tableParams);
        }

        $data['chosen_ticket_status']              = $status;
        $data['weekly_tickets_opening_statistics'] = json_encode($this->tickets_model->get_weekly_tickets_opening_statistics());
        $data['title']                             = _l('support_tickets');
        $this->load->model('departments_model');
        $data['statuses']             = $this->tickets_model->get_ticket_status();
        $data['staff_deparments_ids'] = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
        $data['departments']          = $this->departments_model->get();
        $data['priorities']           = $this->tickets_model->get_priority();
        $data['services']             = $this->tickets_model->get_service();
        $data['ticket_assignees']     = $this->tickets_model->get_tickets_assignes_disctinct();
        $data['bodyclass']            = 'tickets-page';
        add_admin_tickets_js_assets();
        $data['default_tickets_list_statuses'] = hooks()->apply_filters('default_tickets_list_statuses', [1, 2, 4]);
        $this->load->view('admin/tickets/list', $data);
    }

    public function add($userid = false)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketResponse(fn()=>$this->nativeTicketPage()->add($userid));return;}
        if ($this->input->post()) {
            $data            = $this->input->post();
            $data['message'] = html_purify($this->input->post('message', false));
            $id              = $this->tickets_model->add($data, get_staff_user_id());
            if ($id) {
                set_alert('success', _l('new_ticket_added_successfully', $id));
                redirect(admin_url('tickets/ticket/' . $id));
            }
        }
        if ($userid !== false) {
            $data['userid'] = $userid;
            $data['client'] = $this->clients_model->get($userid);
        }
        // Load necessary models
        $this->load->model('knowledge_base_model');
        $this->load->model('departments_model');

        $data['departments']        = $this->departments_model->get();
        $data['predefined_replies'] = $this->tickets_model->get_predefined_reply();
        $data['priorities']         = $this->tickets_model->get_priority();
        $data['services']           = $this->tickets_model->get_service();
        $whereStaff                 = ['active' => 1];
        if (get_option('access_tickets_to_none_staff_members') == 0) {
            $whereStaff['is_not_staff'] = 0;
        }
        $data['staff']     = $this->staff_model->get('', $whereStaff);
        $data['articles']  = $this->knowledge_base_model->get();
        $data['bodyclass'] = 'ticket';
        $data['title']     = _l('new_ticket');

        if ($this->input->get('project_id') && $this->input->get('project_id') > 0) {
            // request from project area to create new ticket
            $data['project_id'] = $this->input->get('project_id');
            $data['userid']     = get_client_id_by_project_id($data['project_id']);
            if (total_rows(db_prefix() . 'contacts', ['active' => 1, 'userid' => $data['userid']]) == 1) {
                $contact = $this->clients_model->get_contacts($data['userid']);
                if (isset($contact[0])) {
                    $data['contact'] = $contact[0];
                }
            }
        } elseif ($this->input->get('contact_id') && $this->input->get('contact_id') > 0 && $this->input->get('userid')) {
            $contact_id = $this->input->get('contact_id');
            if (total_rows(db_prefix() . 'contacts', ['active' => 1, 'id' => $contact_id]) == 1) {
                $contact = $this->clients_model->get_contact($contact_id);
                if ($contact) {
                    $data['contact'] = (array) $contact;
                }
            }
        }
        add_admin_tickets_js_assets();
        $this->load->view('admin/tickets/add', $data);
    }

    public function delete($ticketid)
    {
        if (CompanyRequestScope::nativeFor($this)) { require_once APPPATH.'services/NativeCompanyTicketLifecycleController.php'; (new NativeCompanyTicketLifecycleController($this))->deleteRecord($ticketid); return; }
        if (! $ticketid) {
            redirect(admin_url('tickets'));
        }

        if (! can_staff_delete_ticket()) {
            access_denied('delete ticket');
        }

        $response = $this->tickets_model->delete($ticketid);

        if ($response == true) {
            set_alert('success', _l('deleted', _l('ticket')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('ticket_lowercase')));
        }

        // ensure if deleted from single ticket page, user is redirected to index
        if (str_contains(previous_url(), 'ticket/' . $ticketid)) {
            redirect(admin_url('tickets'));

            return;
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function delete_attachment($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketResponse(fn()=>$this->nativeTicketLifecycle()->deleteAttachment((string)$id));return;}
        if (is_admin() || (! is_admin() && get_option('allow_non_admin_staff_to_delete_ticket_attachments') == '1')) {
            if (get_option('staff_access_only_assigned_departments') == 1 && ! is_admin()) {
                $attachment = $this->tickets_model->get_ticket_attachment($id);
                $ticket     = $this->tickets_model->get_ticket_by_id($attachment->ticketid);

                $this->load->model('departments_model');
                $staff_departments = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                if (! in_array($ticket->department, $staff_departments)) {
                    set_alert('danger', _l('ticket_access_by_department_denied'));
                    redirect(admin_url('access_denied'));
                }
            }

            $this->tickets_model->delete_ticket_attachment($id);
        }

        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    public function update_staff_replying($ticketId, $userId = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketLifecycle()->updateReplying($ticketId,$userId);return;}
        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => $this->tickets_model->update_staff_replying($ticketId, $userId)]);

            exit;
        }
    }

    public function check_staff_replying($ticketId)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketLifecycle()->checkReplying($ticketId);return;}
        if ($this->input->is_ajax_request()) {
            $ticket            = $this->tickets_model->get_staff_replying($ticketId);
            $isAnotherReplying = $ticket->staff_id_replying !== null && $ticket->staff_id_replying !== get_staff_user_id();
            echo json_encode([
                'is_other_staff_replying' => $isAnotherReplying,
                'message'                 => $isAnotherReplying ? e(_l('staff_is_currently_replying', get_staff_full_name($ticket->staff_id_replying))) : '',
            ]);

            exit;
        }
    }

    public function ticket($id)
    {
        if (CompanyRequestScope::nativeFor($this)) {
            $this->nativeTicketResponse(function()use($id):void {
            $method = strtoupper($this->input->method());
            if ($method === 'GET' && $this->input->get('merge_preview') !== null) {
                $this->nativeTicketLifecycle()->mergePreview($id);
                return;
            }
            if (in_array($method, ['GET','HEAD'], true)) {
                require_once APPPATH . 'services/NativeCompanyTicketDetailController.php';
                (new NativeCompanyTicketDetailController($this))->show($id);
                return;
            }
            if ($method !== 'POST') throw new DomainException('native_ticket_method_invalid');
            if ($this->input->post('native_ticket_command', false) === 'upload_files') {
                $this->nativeTicketLifecycle()->uploadFiles($id);
                return;
            }
            if (in_array($this->input->post('action', false), ['portal_permission','portal_publish'], true)) {
                try {
                    require_once APPPATH . 'services/NativeCompanyTicketDetailController.php';
                    (new NativeCompanyTicketDetailController($this))->portalAction($id);
                } catch (DomainException $error) {
                    $reason = $error->getMessage();
                    $status = str_contains($reason, 'stale') || str_contains($reason, 'conflict') ? 409 : (str_contains($reason, 'csrf') || str_contains($reason, 'denied') || str_contains($reason, 'access') ? 403 : 422);
                    show_error('Não foi possível confirmar a operação do portal deste chamado.', $status);
                } catch (Throwable $error) {
                    show_error('Não foi possível confirmar a operação do portal deste chamado.', 503);
                }
                return;
            }
            if ($this->input->post('native_ticket_command', false) === 'mark_read') {
                require_once APPPATH . 'services/NativeCompanyTicketDetailController.php';
                (new NativeCompanyTicketDetailController($this))->markRead($id);
                return;
            }
            $this->nativeTicketLifecycle()->reply($id);
            });
            return;
        }
        if (! $id) {
            redirect(admin_url('tickets/add'));
        }

        $data['ticket']         = $this->tickets_model->get_ticket_by_id($id);
        $data['merged_tickets'] = $this->tickets_model->get_merged_tickets_by_primary_id($id);

        if (! $data['ticket']) {
            blank_page(_l('ticket_not_found'));
        }

        if (get_option('staff_access_only_assigned_departments') == 1) {
            if (! is_admin()) {
                $this->load->model('departments_model');
                $staff_departments = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                if (! in_array($data['ticket']->department, $staff_departments)) {
                    set_alert('danger', _l('ticket_access_by_department_denied'));
                    redirect(admin_url('access_denied'));
                }
            }
        }

        if ($this->input->post()) {
            $returnToTicketList = false;
            $data               = $this->input->post();

            if (isset($data['ticket_add_response_and_back_to_list'])) {
                $returnToTicketList = true;
                unset($data['ticket_add_response_and_back_to_list']);
            }

            $data['message'] = html_purify($this->input->post('message', false));
            $replyid         = $this->tickets_model->add_reply($data, $id, get_staff_user_id());

            if ($replyid) {
                set_alert('success', _l('replied_to_ticket_successfully', $id));
            }
            if (! $returnToTicketList) {
                redirect(admin_url('tickets/ticket/' . $id));
            } else {
                set_ticket_open(0, $id);
                redirect(admin_url('tickets'));
            }
        }
        // Load necessary models
        $this->load->model('knowledge_base_model');
        $this->load->model('departments_model');

        $data['statuses']                       = $this->tickets_model->get_ticket_status();
        $data['statuses']['callback_translate'] = 'ticket_status_translate';

        $data['departments']        = $this->departments_model->get();
        $data['predefined_replies'] = $this->tickets_model->get_predefined_reply();
        $data['priorities']         = $this->tickets_model->get_priority();
        $data['services']           = $this->tickets_model->get_service();
        $whereStaff                 = ['active' => 1];
        if (get_option('access_tickets_to_none_staff_members') == 0) {
            $whereStaff['is_not_staff'] = 0;
        }
        $data['staff']          = $this->staff_model->get('', $whereStaff);
        $data['articles']       = $this->knowledge_base_model->get();
        $data['ticket_replies'] = $this->tickets_model->get_ticket_replies($id);
        $data['bodyclass']      = 'top-tabs ticket single-ticket';
        $data['title']          = $data['ticket']->subject;
        $data['sender_blocked'] = total_rows(db_prefix() . 'spam_filters', [
            'type' => 'sender', 'value' => $data['ticket']->ticket_email, 'rel_type' => 'tickets',
        ]) > 0;
        $data['ticket']->ticket_notes = $this->misc_model->get_notes($id, 'ticket');
        add_admin_tickets_js_assets();
        $this->load->view('admin/tickets/single', $data);
    }

    public function edit_message()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketLifecycle()->editMessage();return;}
        if (! can_staff_edit_ticket_message()) {
            access_denied();
        }

        if ($this->input->post()) {
            $data         = $this->input->post();
            $data['data'] = html_purify($this->input->post('data', false));

            if ($data['type'] == 'reply') {
                $this->db->where('id', $data['id']);
                $this->db->update(db_prefix() . 'ticket_replies', [
                    'message' => $data['data'],
                ]);
            } elseif ($data['type'] == 'ticket') {
                $this->db->where('ticketid', $data['id']);
                $this->db->update(db_prefix() . 'tickets', [
                    'message' => $data['data'],
                ]);
            }
            if ($this->db->affected_rows() > 0) {
                set_alert('success', _l('ticket_message_updated_successfully'));
            }
            redirect(admin_url('tickets/ticket/' . $data['main_ticket']));
        }
    }

    public function delete_ticket_reply($ticket_id, $reply_id)
    {
        if (CompanyRequestScope::nativeFor($this)) { require_once APPPATH.'services/NativeCompanyTicketLifecycleController.php'; (new NativeCompanyTicketLifecycleController($this))->deleteRecord($ticket_id,$reply_id); return; }
        if (! $reply_id) {
            redirect(admin_url('tickets'));
        }

        if (! can_staff_delete_ticket_reply()) {
            access_denied('delete ticket');
        }

        $response = $this->tickets_model->delete_ticket_reply($ticket_id, $reply_id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('ticket_reply')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('ticket_reply')));
        }
        redirect(admin_url('tickets/ticket/' . $ticket_id));
    }

    public function change_status_ajax($id, $status)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketResponse(fn()=>$this->nativeTicketLifecycle()->changeStatus($id,$status));return;}
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->tickets_model->change_ticket_status($id, $status));
        }
    }

    public function update_single_ticket_settings()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketResponse(fn()=>$this->nativeTicketLifecycle()->updateSettings());return;}
        if ($this->input->post()) {
            if ($this->input->post('merge_ticket_ids') !== 0) {
                $ticketsToMerge = explode(',', $this->input->post('merge_ticket_ids') ?: '');

                $alreadyMergedTickets = $this->tickets_model->get_already_merged_tickets($ticketsToMerge);
                if (count($alreadyMergedTickets) > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => _l('cannot_merge_tickets_with_ids', implode(',', $alreadyMergedTickets)),
                    ]);

                    exit();
                }
            }

            $success = $this->tickets_model->update_single_ticket_settings($this->input->post());
            if ($success) {
                if (get_option('staff_access_only_assigned_departments') == 1) {
                    $ticket = $this->tickets_model->get_ticket_by_id($this->input->post('ticketid'));
                    $this->load->model('departments_model');
                    $staff_departments = $this->departments_model->get_staff_departments(get_staff_user_id(), true);
                    if (! in_array($ticket->department, $staff_departments) && ! is_admin()) {
                        set_alert('success', _l('ticket_settings_updated_successfully_and_reassigned', $ticket->department_name));
                        echo json_encode([
                            'success'               => $success,
                            'department_reassigned' => true,
                        ]);

                        exit();
                    }
                }
                set_alert('success', _l('ticket_settings_updated_successfully'));
            }
            echo json_encode([
                'success' => $success,
            ]);

            exit();
        }
    }

    // Priorities
    // Get all ticket priorities
    public function priorities()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->priorities();return;}
        if (! is_admin()) {
            access_denied('Ticket Priorities');
        }
        $data['priorities'] = $this->tickets_model->get_priority();
        $data['title']      = _l('ticket_priorities');
        $this->load->view('admin/tickets/priorities/manage', $data);
    }

    // Add new priority od update existing
    public function priority()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->save('priority');return;}
        if (! is_admin()) {
            access_denied('Ticket Priorities');
        }
        if ($this->input->post()) {
            if (! $this->input->post('id')) {
                $id = $this->tickets_model->add_priority($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('ticket_priority')));
                }
            } else {
                $data = $this->input->post();
                $id   = $data['id'];
                unset($data['id']);
                $success = $this->tickets_model->update_priority($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('ticket_priority')));
                }
            }

            exit;
        }
    }

    // Delete ticket priority
    public function delete_priority($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->delete('priority',$id);return;}
        if (! is_admin()) {
            access_denied('Ticket Priorities');
        }
        if (! $id) {
            redirect(admin_url('tickets/priorities'));
        }
        $response = $this->tickets_model->delete_priority($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('ticket_priority_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('ticket_priority')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('ticket_priority_lowercase')));
        }
        redirect(admin_url('tickets/priorities'));
    }

    // List all ticket predefined replies
    public function predefined_replies()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->table('predefined_reply');return;}
        if (! is_admin()) {
            access_denied('Predefined Replies');
        }
        if ($this->input->is_ajax_request()) {
            $aColumns = [
                'name',
            ];
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'tickets_predefined_replies';
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], [
                'id',
            ]);
            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $output['aaData'][] = require APPPATH.'views/admin/tickets/predefined_replies/table_row.php';
            }
            echo json_encode($output);

            exit();
        }
        $data['title'] = _l('predefined_replies');
        $this->load->view('admin/tickets/predefined_replies/manage', $data);
    }

    public function get_predefined_reply_ajax($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->predefinedRead($id);return;}
        echo json_encode($this->tickets_model->get_predefined_reply($id));
    }

    public function ticket_change_data()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketPage()->contactChange();return;}
        if ($this->input->is_ajax_request()) {
            $contact_id = $this->input->post('contact_id');
            echo json_encode([
                'contact_data'          => $this->clients_model->get_contact($contact_id),
                'customer_has_projects' => customer_has_projects(get_user_id_by_contact_id($contact_id)),
            ]);
        }
    }

    // Add new reply or edit existing
    public function predefined_reply($id = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->predefined($id);return;}
        if (! is_admin() && get_option('staff_members_save_tickets_predefined_replies') == '0') {
            access_denied('Predefined Reply');
        }
        if ($this->input->post()) {
            $data              = $this->input->post();
            $data['message']   = html_purify($this->input->post('message', false));
            $ticketAreaRequest = isset($data['ticket_area']);

            if (isset($data['ticket_area'])) {
                unset($data['ticket_area']);
            }

            if ($id == '') {
                $id = $this->tickets_model->add_predefined_reply($data);
                if (! $ticketAreaRequest) {
                    if ($id) {
                        set_alert('success', _l('added_successfully', _l('predefined_reply')));
                        redirect(admin_url('tickets/predefined_reply/' . $id));
                    }
                } else {
                    echo json_encode(['success' => $id ? true : false, 'id' => $id]);

                    exit;
                }
            } else {
                $success = $this->tickets_model->update_predefined_reply($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('predefined_reply')));
                }
                redirect(admin_url('tickets/predefined_reply/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('predefined_reply'));
        } else {
            $predefined_reply         = $this->tickets_model->get_predefined_reply($id);
            $data['predefined_reply'] = $predefined_reply;
            $title                    = _l('edit', _l('predefined_reply')) . ' ' . $predefined_reply->name;
        }
        $data['title'] = $title;
        $this->load->view('admin/tickets/predefined_replies/reply', $data);
    }

    // Delete ticket reply from database
    public function delete_predefined_reply($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->delete('predefined_reply',$id);return;}
        if (! is_admin()) {
            access_denied('Delete Predefined Reply');
        }
        if (! $id) {
            redirect(admin_url('tickets/predefined_replies'));
        }
        $response = $this->tickets_model->delete_predefined_reply($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('predefined_reply')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('predefined_reply_lowercase')));
        }
        redirect(admin_url('tickets/predefined_replies'));
    }

    // Ticket statuses
    // Get all ticket statuses
    public function statuses()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->statuses();return;}
        if (! is_admin()) {
            access_denied('Ticket Statuses');
        }
        $data['statuses'] = $this->tickets_model->get_ticket_status();
        $data['title']    = 'Ticket statuses';
        $this->load->view('admin/tickets/tickets_statuses/manage', $data);
    }

    // Add new or edit existing status
    public function status()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->save('status');return;}
        if (! is_admin()) {
            access_denied('Ticket Statuses');
        }
        if ($this->input->post()) {
            if (! $this->input->post('id')) {
                $id = $this->tickets_model->add_ticket_status($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('ticket_status')));
                }
            } else {
                $data = $this->input->post();
                $id   = $data['id'];
                unset($data['id']);
                $success = $this->tickets_model->update_ticket_status($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('ticket_status')));
                }
            }

            exit;
        }
    }

    // Delete ticket status from database
    public function delete_ticket_status($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->delete('status',$id);return;}
        if (! is_admin()) {
            access_denied('Ticket Statuses');
        }
        if (! $id) {
            redirect(admin_url('tickets/statuses'));
        }
        $response = $this->tickets_model->delete_ticket_status($id);
        if (is_array($response) && isset($response['default'])) {
            set_alert('warning', _l('cant_delete_default', _l('ticket_status_lowercase')));
        } elseif (is_array($response) && isset($response['referenced'])) {
            set_alert('danger', _l('is_referenced', _l('ticket_status_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('ticket_status')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('ticket_status_lowercase')));
        }
        redirect(admin_url('tickets/statuses'));
    }

    // List all ticket services
    public function services()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->table('service');return;}
        if (! is_admin()) {
            access_denied('Ticket Services');
        }
        if ($this->input->is_ajax_request()) {
            $aColumns = [
                'serviceid',
                'name',
            ];
            $sIndexColumn = 'serviceid';
            $sTable       = db_prefix() . 'services';
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], [
                'serviceid',
            ]);
            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $output['aaData'][] = require APPPATH.'views/admin/tickets/services/table_row.php';
            }
            echo json_encode($output);

            exit();
        }
        $data['title'] = _l('services');
        $this->load->view('admin/tickets/services/manage', $data);
    }

    // Add new service od delete existing one
    public function service($id = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->save('service');return;}
        if (! is_admin() && get_option('staff_members_save_tickets_predefined_replies') == '0') {
            access_denied('Ticket Services');
        }

        if ($this->input->post()) {
            $post_data = $this->input->post();
            if (! $this->input->post('id')) {
                $requestFromTicketArea = isset($post_data['ticket_area']);
                if (isset($post_data['ticket_area'])) {
                    unset($post_data['ticket_area']);
                }
                $id = $this->tickets_model->add_service($post_data);
                if (! $requestFromTicketArea) {
                    if ($id) {
                        set_alert('success', _l('added_successfully', _l('service')));
                    }
                } else {
                    echo json_encode(['success' => $id ? true : false, 'id' => $id, 'name' => $post_data['name']]);
                }
            } else {
                $id = $post_data['id'];
                unset($post_data['id']);
                $success = $this->tickets_model->update_service($post_data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('service')));
                }
            }

            exit;
        }
    }

    // Delete ticket service from database
    public function delete_service($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketCatalog()->delete('service',$id);return;}
        if (! is_admin()) {
            access_denied('Ticket Services');
        }
        if (! $id) {
            redirect(admin_url('tickets/services'));
        }
        $response = $this->tickets_model->delete_service($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('service_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('service')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('service_lowercase')));
        }
        redirect(admin_url('tickets/services'));
    }

    public function block_sender()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketLifecycle()->blockSender();return;}
        if ($this->input->post()) {
            $this->load->model('spam_filters_model');
            $sender  = $this->input->post('sender');
            $success = $this->spam_filters_model->add(['type' => 'sender', 'value' => $sender], 'tickets');
            if ($success) {
                set_alert('success', _l('sender_blocked_successfully'));
            }
        }
    }

    public function bulk_action()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeTicketLifecycle()->bulk();return;}
        hooks()->do_action('before_do_bulk_action_for_tickets');
        if ($this->input->post()) {
            $ids                  = $this->input->post('ids');
            $is_admin             = is_admin();
            $staffCanDeleteTicket = can_staff_delete_ticket();

            if (! is_array($ids)) {
                return;
            }

            if ($this->input->post('merge_tickets')) {
                $primary_ticket = $this->input->post('primary_ticket');
                $status         = $this->input->post('primary_ticket_status');

                if ($this->tickets_model->is_merged($primary_ticket)) {
                    set_alert('warning', _l('cannot_merge_into_merged_ticket'));

                    return;
                }

                $total_merged = $this->tickets_model->merge($primary_ticket, $status, $ids);
            } elseif ($this->input->post('mass_delete')) {
                $total_deleted = 0;
                if ($is_admin || $staffCanDeleteTicket) {
                    foreach ($ids as $id) {
                        if ($this->tickets_model->delete($id)) {
                            $total_deleted++;
                        }
                    }
                } else {
                    ajax_access_denied();

                    return;
                }
            } else {
                $status     = $this->input->post('status');
                $department = $this->input->post('department');
                $service    = $this->input->post('service');
                $priority   = $this->input->post('priority');
                $tags       = $this->input->post('tags');

                foreach ($ids as $id) {
                    if ($status) {
                        $this->db->where('ticketid', $id);
                        $this->db->update(db_prefix() . 'tickets', [
                            'status' => $status,
                        ]);
                    }
                    if ($department) {
                        $this->db->where('ticketid', $id);
                        $this->db->update(db_prefix() . 'tickets', [
                            'department' => $department,
                        ]);
                    }
                    if ($priority) {
                        $this->db->where('ticketid', $id);
                        $this->db->update(db_prefix() . 'tickets', [
                            'priority' => $priority,
                        ]);
                    }

                    if ($service) {
                        $this->db->where('ticketid', $id);
                        $this->db->update(db_prefix() . 'tickets', [
                            'service' => $service,
                        ]);
                    }
                    if ($tags) {
                        handle_tags_save($tags, $id, 'ticket');
                    }
                }
            }

            if ($this->input->post('mass_delete')) {
                set_alert('success', _l('total_tickets_deleted', $total_deleted));
            } elseif ($this->input->post('merge_tickets') && $total_merged > 0) {
                set_alert('success', _l('tickets_merged'));
            }
        }
    }
}
