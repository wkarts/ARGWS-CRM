<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Notificações opcionais de eventos existentes. Nenhum disparo é habilitado
 * automaticamente; cada SMS usa a instância Connect|API já cadastrada.
 * Os eventos e os respectivos dados seguem os contratos do CRM.
 */
hooks()->add_action('contact_created', 'sms_notice_contact_created');
hooks()->add_action('lead_created', 'sms_notice_lead_created');
hooks()->add_action('ticket_created', 'sms_notice_ticket_created');
hooks()->add_action('estimate_sent', 'sms_notice_estimate_sent');
hooks()->add_action('proposal_sent', 'sms_notice_proposal_sent');
hooks()->add_action('proposal_accepted', 'sms_notice_proposal_accepted');
hooks()->add_action('task_assignee_added', 'sms_notice_task_assigned');
hooks()->add_action('task_status_changed', 'sms_notice_task_completed');
hooks()->add_action('project_status_changed', 'sms_notice_project_finished');
hooks()->add_action('invoice_marked_as_cancelled', 'sms_notice_invoice_cancelled');

function sms_notice_gateway($trigger)
{
    $CI = &get_instance();
    if (!isset($CI->app_sms) || !is_object($CI->app_sms)) {
        return false;
    }
    if (!$CI->app_sms->get_active_gateway() || !$CI->app_sms->is_trigger_active($trigger)) {
        return false;
    }

    return $CI;
}

function sms_notice_dispatch($CI, $trigger, $phone, array $variables)
{
    if (trim((string) $phone) === '') {
        return;
    }

    try {
        $CI->app_sms->trigger($trigger, (string) $phone, $variables);
    } catch (Throwable $exception) {
        // O envio opcional jamais pode impedir gravações de clientes/faturas.
        log_message('error', 'Falha em notificação opcional: ' . get_class($exception));
    }
}

function sms_notice_client_contacts($CI, $clientId, $trigger, array $variables)
{
    if ((int) $clientId <= 0) {
        return;
    }
    $contacts = $CI->db->where('userid', (int) $clientId)
        ->where('active', 1)
        ->get(db_prefix() . 'contacts')->result_array();

    foreach ($contacts as $contact) {
        if (empty($contact['phonenumber'])) {
            continue;
        }
        sms_notice_dispatch($CI, $trigger, $contact['phonenumber'],
            array_merge($variables, [
                '{contact_firstname}' => (string) ($contact['firstname'] ?? ''),
                '{contact_lastname}' => (string) ($contact['lastname'] ?? ''),
                '{contact_email}' => (string) ($contact['email'] ?? ''),
            ])
        );
    }
}

function sms_notice_staff($CI, $staffId, $trigger, array $variables)
{
    if ((int) $staffId <= 0) {
        return;
    }
    $staff = $CI->db->get_where(db_prefix() . 'staff',
        ['staffid' => (int) $staffId])->row_array();
    if (!$staff || (string) ($staff['active'] ?? '0') !== '1') {
        return;
    }
    sms_notice_dispatch($CI, $trigger, $staff['phonenumber'] ?? '', $variables);
}

function sms_notice_contact_created($id)
{
    $trigger = 'contact_created_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $contact = $CI->db->get_where(db_prefix() . 'contacts',
        ['id' => (int) $id])->row_array();
    if (!$contact || (int) ($contact['active'] ?? 0) !== 1) {
        return;
    }
    $client = $CI->db->get_where(db_prefix() . 'clients',
        ['userid' => (int) ($contact['userid'] ?? 0)])->row_array();
    sms_notice_dispatch($CI, $trigger, $contact['phonenumber'] ?? '', [
        '{contact_firstname}' => (string) ($contact['firstname'] ?? ''),
        '{contact_lastname}' => (string) ($contact['lastname'] ?? ''),
        '{contact_email}' => (string) ($contact['email'] ?? ''),
        '{client_company}' => (string) ($client['company'] ?? ''),
        '{client_vat_number}' => (string) ($client['vat'] ?? ''),
        '{client_id}' => (string) ($contact['userid'] ?? ''),
    ]);
}

function sms_notice_lead_created($id)
{
    $trigger = 'lead_created_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $lead = $CI->db->get_where(db_prefix() . 'leads',
        ['id' => (int) $id])->row_array();
    if (!$lead) {
        return;
    }
    sms_notice_dispatch($CI, $trigger, $lead['phonenumber'] ?? '', [
        '{lead_name}' => (string) ($lead['name'] ?? ''),
        '{lead_id}' => (string) ($lead['id'] ?? ''),
    ]);
}

function sms_notice_ticket_created($id)
{
    $trigger = 'ticket_created_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $ticket = $CI->db->get_where(db_prefix() . 'tickets',
        ['ticketid' => (int) $id])->row_array();
    if (!$ticket || (int) ($ticket['contactid'] ?? 0) <= 0) {
        return;
    }
    $contact = $CI->db->get_where(db_prefix() . 'contacts',
        ['id' => (int) $ticket['contactid'], 'active' => 1])->row_array();
    if (!$contact) {
        return;
    }
    sms_notice_dispatch($CI, $trigger, $contact['phonenumber'] ?? '', [
        '{ticket_id}' => (string) $id,
        '{ticket_subject}' => (string) ($ticket['subject'] ?? ''),
        '{contact_firstname}' => (string) ($contact['firstname'] ?? ''),
    ]);
}

function sms_notice_estimate_sent($id)
{
    $trigger = 'estimate_sent_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $estimate = $CI->db->get_where(db_prefix() . 'estimates',
        ['id' => (int) $id])->row_array();
    if (!$estimate) {
        return;
    }
    $number = function_exists('format_estimate_number')
        ? format_estimate_number((int) $id) : (string) $id;
    sms_notice_client_contacts($CI, $estimate['clientid'] ?? 0, $trigger, [
        '{estimate_id}' => (string) $id,
        '{estimate_number}' => (string) $number,
    ]);
}

function sms_notice_proposal_sent($id)
{
    sms_notice_proposal_event($id, 'proposal_sent_notice', false);
}

function sms_notice_proposal_accepted($id)
{
    sms_notice_proposal_event($id, 'proposal_accepted_notice', true);
}

function sms_notice_proposal_event($id, $trigger, $toStaff)
{
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $proposal = $CI->db->get_where(db_prefix() . 'proposals',
        ['id' => (int) $id])->row_array();
    if (!$proposal) {
        return;
    }
    $variables = [
        '{proposal_id}' => (string) $id,
        '{proposal_number}' => (string) (function_exists('format_proposal_number')
            ? format_proposal_number((int) $id) : $id),
        '{proposal_subject}' => (string) ($proposal['subject'] ?? ''),
    ];

    if ($toStaff) {
        sms_notice_staff($CI, $proposal['assigned'] ?? 0, $trigger, $variables);
    } else {
        sms_notice_dispatch($CI, $trigger, $proposal['phone'] ?? '', $variables);
    }
}

function sms_notice_task_assigned($event)
{
    $trigger = 'task_assignee_added_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    if (!is_array($event) || empty($event['staff_id']) || empty($event['task_id'])) {
        return;
    }
    // "staff_id" do hook histórico é o ID da associação, NÃO o staffid.
    $assigned = $CI->db->get_where(db_prefix() . 'task_assigned',
        ['id' => (int) $event['staff_id']])->row_array();
    $task = $CI->db->get_where(db_prefix() . 'tasks',
        ['id' => (int) $event['task_id']])->row_array();
    if (!$assigned || !$task) {
        return;
    }
    sms_notice_staff($CI, $assigned['staffid'] ?? 0, $trigger, [
        '{task_id}' => (string) $event['task_id'],
        '{task_name}' => (string) ($task['name'] ?? ''),
    ]);
}

function sms_notice_task_completed($event)
{
    $trigger = 'task_completed_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    if (!is_array($event) || (int) ($event['status'] ?? 0) !== 5) {
        return;
    }
    $task = $CI->db->get_where(db_prefix() . 'tasks',
        ['id' => (int) ($event['task_id'] ?? 0)])->row_array();
    if (!$task) {
        return;
    }
    sms_notice_staff($CI, $task['addedfrom'] ?? 0, $trigger, [
        '{task_id}' => (string) ($task['id'] ?? ''),
        '{task_name}' => (string) ($task['name'] ?? ''),
    ]);
}

function sms_notice_project_finished($event)
{
    $trigger = 'project_finished_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    if (!is_array($event) || (int) ($event['status'] ?? 0) !== 4) {
        return;
    }
    $project = $CI->db->get_where(db_prefix() . 'projects',
        ['id' => (int) ($event['project_id'] ?? 0)])->row_array();
    if (!$project) {
        return;
    }
    sms_notice_client_contacts($CI, $project['clientid'] ?? 0, $trigger, [
        '{project_id}' => (string) ($project['id'] ?? ''),
        '{project_name}' => (string) ($project['name'] ?? ''),
    ]);
}

function sms_notice_invoice_cancelled($id)
{
    $trigger = 'invoice_cancelled_notice';
    if (!$CI = sms_notice_gateway($trigger)) {
        return;
    }
    $invoice = $CI->db->get_where(db_prefix() . 'invoices',
        ['id' => (int) $id])->row_array();
    if (!$invoice) {
        return;
    }
    $number = function_exists('format_invoice_number')
        ? format_invoice_number((int) $id) : (string) $id;
    sms_notice_client_contacts($CI, $invoice['clientid'] ?? 0, $trigger, [
        '{invoice_id}' => (string) $id,
        '{invoice_number}' => (string) $number,
    ]);
}
