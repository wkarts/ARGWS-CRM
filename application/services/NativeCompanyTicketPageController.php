<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketPresentation.php';

/** Adapter for Tickets/add and Tickets/ticket_change_data, using the original view. */
final class NativeCompanyTicketPageController
{
    private int $issuer;
    private int $actor;
    public function __construct(private $ci)
    {
        if (!CompanyRequestScope::nativeFor($ci) || !CompanyRequestScope::sealedFor($ci)) throw new DomainException('native_ticket_create_denied');
        $context = CompanyRequestScope::context();
        $this->issuer = (int) $context->issuerId();
        $this->actor = (int) $context->staffId();
        $this->authorize();
    }
    private function authorize(): void
    {
        $acl = new IssuerResourceAccess($this->ci->db, db_prefix());
        if (!$acl->member($this->issuer, $this->actor) || !$acl->can($this->issuer, $this->actor, 'tickets', 'view') || !$acl->can($this->issuer, $this->actor, 'tickets', 'create')) throw new DomainException('native_ticket_create_denied');
    }
    private function input(): array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);
        $this->authorize();
        $data = $this->ci->input->post(null, false);
        if (!is_array($data)) throw new DomainException('native_ticket_input_invalid');
        unset($data['_company_context'], $data['_native_company_csrf']);
        if (isset($this->ci->security)) unset($data[$this->ci->security->get_csrf_token_name()]);
        return $data;
    }
    public function add($userid = false): void
    {
        if (strtoupper($this->ci->input->method()) === 'POST') {
            $data = $this->input();
            // No upload may disappear into a successful ticket creation before its adapter exists.
            foreach ($_FILES as $upload) {
                foreach ((array) ($upload['error'] ?? []) as $error) if ($error !== UPLOAD_ERR_NO_FILE) throw new DomainException('native_ticket_attachments_unavailable');
            }
            $request = $data['_native_ticket_request'] ?? null;
            unset($data['_native_ticket_request']);
            if (!is_string($request) || !preg_match('/^[a-f0-9]{64}$/D', $request)) throw new DomainException('native_ticket_request_invalid');
            $id = (new NativeCompanyTicketCommands($this->ci->db, db_prefix()))->createNative($this->issuer, $this->actor, $data, $request);
            redirect(CompanyRequestScope::adminUrl($this->ci, 'tickets/ticket/'.$id), 'location', 303);
            return;
        }
        if (strtoupper($this->ci->input->method()) !== 'GET') throw new DomainException('native_ticket_method_invalid');
        $presentation = new NativeCompanyTicketPresentation($this->ci->db, db_prefix());
        $data = $presentation->add($this->issuer, $this->actor);
        $project = $this->ci->input->get('project_id');
        $contact = $this->ci->input->get('contact_id');
        $client = $this->ci->input->get('userid') ?: ($userid === false ? null : $userid);
        if ($client !== null) {
            $ref = NativeCompanyResourceReferences::resolve($this->ci->db, db_prefix(), $this->issuer, $this->actor, 'client', $client);
            $data['userid'] = $ref['canonical_id'];
        }
        if ($project !== null && $project !== '') {
            $ref = $presentation->project($this->issuer, $this->actor, $project);
            if ($client !== null && NativeCompanyResourceId::decode('client', $client)['source_id'] !== $ref['client_id']) throw new DomainException('native_ticket_project_denied');
            $data['project_id'] = $ref['canonical_id'];
            $data['userid'] = $ref['client_id'];
            $data['native_ticket_presentation']['project_name'] = $ref['name'];
            $client = $ref['client_id'];
            if (!$contact) {
                $contacts = new NativeCompanyTicketContacts($this->ci->db, db_prefix());
                $matches = $contacts->options($this->issuer, $this->actor, '', null, $client);
                if (count($matches) === 1) $contact = $matches[0]['id'];
            }
        }
        if ($contact !== null && $contact !== '') $data['contact'] = $presentation->contact($this->issuer, $this->actor, $contact, $client);
        $data += [
            'native_ticket_catalog'=>true, 'native_ticket_context'=>CompanyRequestScope::token(),
            'native_ticket_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),
            'native_ticket_request'=>bin2hex(random_bytes(32)), 'bodyclass'=>'ticket', 'title'=>_l('new_ticket'),
        ];
        NativeCompanyAdminPresentation::prepare($this->ci);
        $this->authorize();
        $this->ci->load->view('admin/tickets/add', $data);
    }
    public function contactChange(): void
    {
        $data = $this->input();
        if (array_diff(array_keys($data), ['contact_id']) || !isset($data['contact_id'])) throw new DomainException('native_ticket_input_invalid');
        $presentation = new NativeCompanyTicketPresentation($this->ci->db, db_prefix());
        $contact = $presentation->contact($this->issuer, $this->actor, $data['contact_id']);
        $hasProjects = $presentation->customerHasProjects($this->issuer, $this->actor, (int) $contact['userid']);
        if ($contact !== $presentation->contact($this->issuer, $this->actor, $data['contact_id'])) throw new DomainException('native_ticket_contact_denied');
        $this->authorize();
        $this->ci->output->set_content_type('application/json')->set_output(json_encode(['contact_data'=>$contact, 'customer_has_projects'=>$hasProjects], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
