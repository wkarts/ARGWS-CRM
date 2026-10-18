<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Issuers extends AdminController
{
    private $registry;

    public function __construct()
    {
        parent::__construct();
        if (!defined('ISSUERS_MODULE_NAME')) {
            show_404();
        }
        $this->load->model('issuers/issuers_model');
        $this->registry = $this->issuers_model->registry();
    }

    public function index($issuerId = null)
    {
        $actor = get_staff_user_id();
        try {
            $available = $this->registry->available($actor);
            $catalog = is_admin() ? $this->registry->catalog($actor) : $available;
            if (!is_admin() && !$available) {
                access_denied('issuers');
            }
            $selected = null;
            if ($issuerId !== null) {
                $issuerId = IssuerPolicy::id($issuerId);
                foreach ($catalog as $entry) {
                    if ((int) $entry['id'] === $issuerId) {
                        $selected = $entry;
                        break;
                    }
                }
                if ($selected === null) {
                    access_denied('issuers');
                }
            }
            $token = $this->session->userdata('issuers_csrf');
            if (!is_string($token) || strlen($token) !== 64) {
                $token = bin2hex(random_bytes(32));
                $this->session->set_userdata('issuers_csrf', $token);
            }
            $clients = [];
            $targets = [];
            if ($selected && $this->registry->canAccess($issuerId, $actor)) {
                $clients = $this->registry->clients($issuerId, $actor);
                if ($this->registry->canAccess($issuerId, $actor, 'share_clients')) {
                    foreach ($clients as $client) {
                        if ((int) $client['owner_issuer_id'] === $issuerId) {
                            $targets[$client['userid']] = $this->registry->clientTargets($issuerId, $client['userid'], $actor);
                        }
                    }
                }
            }
            $this->load->view('issuers/manage', [
                'title' => _l('issuers_title'), 'catalog' => $catalog, 'available' => $available,
                'selected' => $selected, 'clients' => $clients, 'targets' => $targets,
                'members' => $selected && is_admin() ? $this->registry->members($issuerId, $actor) : [],
                'staff' => is_admin() ? $this->db->select('staffid,firstname,lastname')->where('active', 1)->get(db_prefix() . 'staff')->result_array() : [],
                'default_issuer' => $this->registry->defaultForStaff($actor), 'issuers_csrf' => $token,
            ]);
        } catch (DomainException $exception) {
            access_denied('issuers');
        }
    }

    /** No mutation has a GET route; token is required even when the legacy app disables global CSRF. */
    public function save()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error(_l('issuer_post_required'), 405);
            return;
        }
        $token = $this->session->userdata('issuers_csrf');
        $submitted = $this->input->post('issuers_csrf');
        if (!is_string($token) || strlen($token) !== 64 || !is_string($submitted) || !hash_equals($token, $submitted)) {
            show_error(_l('issuer_invalid_csrf'), 403);
            return;
        }
        $actor = get_staff_user_id();
        $issuer = null;
        try {
            $action = $this->input->post('action');
            if ($action === 'issuer') {
                $id = $this->input->post('issuer_id');
                $issuer = $this->registry->saveIssuer($actor, [
                    'legal_name' => $this->input->post('legal_name'), 'tax_id' => $this->input->post('tax_id'),
                    'active' => $this->input->post('active'),
                ], $id === '' ? null : $id);
            } else {
                $issuer = IssuerPolicy::id($this->input->post('issuer_id'));
                if ($action === 'member') {
                    $permissions = $this->input->post('permissions');
                    if ($permissions !== null && $permissions !== false && !is_array($permissions)) {
                        throw new DomainException('issuer_invalid_permission');
                    }
                    $this->registry->assignMember($issuer, $this->input->post('staff_id'), $permissions ?: [], $actor);
                } elseif ($action === 'remove_member') {
                    $this->registry->removeMember($issuer, $this->input->post('staff_id'), $actor);
                } elseif ($action === 'default') {
                    $this->registry->setDefault($issuer, $actor);
                } elseif ($action === 'register_client') {
                    $this->registry->registerClient($issuer, $this->input->post('client_id'), $actor);
                } elseif ($action === 'share_client') {
                    $targets = $this->input->post('targets');
                    if ($targets !== null && $targets !== false && !is_array($targets)) {
                        throw new DomainException('issuer_invalid_id');
                    }
                    $this->registry->shareClient($issuer, $this->input->post('client_id'), $targets ?: [], $actor);
                } else {
                    throw new DomainException('issuer_invalid_action');
                }
            }
            set_alert('success', _l('issuer_saved'));
        } catch (DomainException $exception) {
            set_alert('danger', _l($exception->getMessage()));
        } catch (Throwable $exception) {
            log_message('error', 'Issuer registry operation failed; no customer values logged.');
            set_alert('danger', _l('issuer_storage_failed'));
        }
        redirect(admin_url('issuers' . ($issuer ? '/index/' . $issuer : '')));
    }
}
