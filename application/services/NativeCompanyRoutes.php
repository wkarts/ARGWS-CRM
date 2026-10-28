<?php

declare(strict_types=1);

/**
 * Native browser entrypoints whose data contracts have explicit company adapters.
 * This is not a router alias: controller names, URLs and native views stay native.
 * Never use this catalogue for jobs, webhooks, public portals or provider callbacks.
 */
final class NativeCompanyRoutes
{
    private const ACTIONS = [
        'dashboard' => [
            'index' => ['GET', 'HEAD'],
            'weekly_payments_statistics' => ['GET'],
            'monthly_payments_statistics' => ['GET'],
            'ticket_widget' => ['GET'],
        ],
        'issuers' => [
            'index' => ['GET', 'HEAD'], 'select' => ['POST'],
            'onboarding' => ['GET', 'HEAD', 'POST'],
            'save' => ['POST'],
        ],
        'todo' => [
            'index'=>['GET','HEAD','POST'], 'todo'=>['POST'], 'get_by_id'=>['GET'],
            'change_todo_status'=>['POST'], 'update_todo_items_order'=>['POST'], 'delete_todo_item'=>['POST'],
        ],
        'announcements' => [
            'index'=>['GET','HEAD','POST'], 'announcement'=>['GET','HEAD','POST'], 'view'=>['GET','HEAD'], 'delete'=>['POST'],
        ],
        'taxes' => [
            'index'=>['GET','HEAD','POST'], 'manage'=>['POST'], 'delete'=>['POST'], 'tax_name_exists'=>['POST'],
        ],
        'departments' => [
            'index'=>['GET','HEAD','POST'], 'department'=>['POST'], 'delete'=>['POST'], 'email_exists'=>['POST'],
            'folders'=>['POST'], 'test_imap_connection'=>['POST'],
        ],
        'tickets' => [
            'index'=>['GET','HEAD','POST'], 'add'=>['GET','HEAD','POST'], 'ticket'=>['GET','HEAD','POST'],
            'delete'=>['POST'], 'delete_attachment'=>['POST'], 'delete_ticket_reply'=>['POST'],
            'edit_message'=>['POST'], 'change_status_ajax'=>['POST'],
            'update_single_ticket_settings'=>['POST'], 'update_staff_replying'=>['POST'],
            'check_staff_replying'=>['GET'], 'block_sender'=>['POST'], 'bulk_action'=>['POST'],
            'ticket_change_data'=>['POST'],
            'priorities'=>['GET','HEAD'], 'priority'=>['POST'], 'delete_priority'=>['POST'],
            'statuses'=>['GET','HEAD'], 'status'=>['POST'], 'delete_ticket_status'=>['POST'],
            'ai'=>['GET','HEAD','POST'], 'connect'=>['GET','HEAD','POST'], 'connect_api'=>['GET','HEAD','POST'],
            'services'=>['GET','HEAD','POST'], 'service'=>['POST'], 'delete_service'=>['POST'],
            'predefined_replies'=>['GET','HEAD','POST'], 'predefined_reply'=>['GET','HEAD','POST'],
            'get_predefined_reply_ajax'=>['GET'], 'delete_predefined_reply'=>['POST'],
        ],
        // Settings::index is the original native Settings route. Its bounded
        // form adapters decide which groups and writes are available.
        'settings' => [
            'index'=>['GET','HEAD','POST'], 'remove_company_logo'=>['POST'],
            'remove_signature_image'=>['POST'], 'remove_fv'=>['POST'],
            'company_asset'=>['GET','HEAD'],
            'pusher'=>['GET','HEAD','POST'], 'asaas'=>['GET','HEAD','POST'], 'imports'=>['GET','HEAD','POST'], 'efi'=>['GET','HEAD','POST'],
            'microsoft_mail_oauth'=>['GET','HEAD','POST'], 'google_mail_oauth'=>['GET','HEAD','POST'], 'api_tokens'=>['GET','HEAD','POST'],
        ],
        'emails' => [
            'sent_smtp_test_email'=>['POST'], 'delete_queued_email'=>['POST'], 'clear_queued_emails'=>['POST'],
        ],
        'misc' => [
            'dismiss_announcement'=>['POST'], 'staff_email_exists'=>['POST'],
            'add_note'=>['POST'], 'edit_note'=>['POST'], 'delete_note'=>['POST'],
            'upload_sales_file'=>['POST'], 'add_sales_external_attachment'=>['POST'],
            'toggle_file_visibility'=>['POST'],
        ],
        'filters' => [
            'create'=>['POST'], 'update'=>['POST'], 'delete'=>['POST'],
            'mark_as_default'=>['POST'], 'unmark_as_default'=>['POST'],
            'validate_dynamic_date'=>['POST'],
        ],
        'tasks'=>[
            'index'=>['GET','HEAD'], 'list_tasks'=>['GET','HEAD'], 'table'=>['POST'],
            'init_relation_tasks'=>['POST'], 'task'=>['GET','HEAD','POST'],
            'get_task_data'=>['GET'],
            'timer_tracking'=>['POST'],
            // This original picker has its own issuer/actor-bound reader and
            // native CSRF guard; it does not admit the remaining Tasks actions.
            'ajax_search_assign_task_to_timer'=>['POST'],
            // Original invoice selectors are read-only, independently
            // authorize invoice editing and resolve all customer/task lineage.
            'get_billable_tasks_by_project'=>['GET'],
            'get_billable_tasks_by_customer_id'=>['GET'],
            // Read-only chart for a task whose company and access are resolved by the reader.
            'task_tracking_stats'=>['GET'],
            'get_staff_names_for_mentions'=>['GET'],
            'get_task_by_id'=>['GET'],
            'get_staff_started_timers'=>['GET'],
            'get_billable_task_data'=>['GET'],
            'save_checklist_item_template'=>['POST'],
            'remove_checklist_item_template'=>['POST'],
            'init_checklist_items'=>['POST'],
            'checklist_visibility'=>['POST'],
            'checklist_command'=>['POST'],
            'add_task_comment'=>['POST'],
            'upload_file'=>['POST'],
            'remove_task_attachment'=>['POST'],
            'mark_as'=>['POST'],
            'change_priority'=>['POST'],
            'copies'=>['GET','HEAD','POST'], 'recurring'=>['GET','HEAD','POST'], 'standalone'=>['GET','HEAD','POST'],
            'billing'=>['GET','HEAD','POST'], 'time_batches'=>['GET','HEAD','POST'],
        ], 'projects'=>[
            'index'=>['GET','HEAD'],
            'staff_projects'=>['POST'],
            'table'=>['POST'],
            'project'=>['GET','HEAD','POST'],
            'view'=>['GET','HEAD'],
            'upload_file'=>['POST'],
            'remove_file'=>['POST'],
            'add_edit_members'=>['POST'],
            'remove_team_member'=>['POST'],
            // The task form's scoped project metadata selector, not project navigation.
            'get_rel_project_data'=>['GET'],
            'get_staff_names_for_mentions'=>['GET'],
            'extras'=>['GET','HEAD','POST'], 'portal_rules'=>['GET','HEAD','POST'], 'billing_rates'=>['GET','HEAD','POST'],
            'fixed_billing'=>['GET','HEAD','POST'], 'copies'=>['GET','HEAD','POST'], 'time_billing'=>['GET','HEAD','POST'],
        ],
        // Read-only private billing projection; customer pages and mutations
        // remain closed until their complete native contracts are admitted.
        'clients'=>[
            'index'=>['GET','HEAD'], 'table'=>['POST'], 'client'=>['GET','HEAD','POST'],
            'form_contact'=>['GET','HEAD','POST'], 'contacts'=>['POST'],
            'all_contacts'=>['GET','POST'],
            'consents'=>['GET','HEAD'],
            'contact_email_exists'=>['POST'], 'change_contact_status'=>['POST'],
            'lookup'=>['POST'], 'check_duplicate_customer_name'=>['POST'],
            'change_client_status'=>['POST'],
            'delete_contact'=>['POST'], 'confirm_registration'=>['POST'],
            'delete_contact_profile_image'=>['POST'],
            'upload_attachment'=>['POST'], 'delete_attachment'=>['POST'],
            'add_external_attachment'=>['POST'],
            'update_file_share_visibility'=>['POST'], 'assign_admins'=>['POST'],
            'delete_customer_admin'=>['POST'], 'save_longitude_and_latitude'=>['POST'],
            'get_vault_entry'=>['GET','HEAD'], 'vault_entry_create'=>['POST'],
            'vault_entry_update'=>['POST'], 'vault_entry_delete'=>['POST'],
            'vault_encrypt_password'=>['POST'],
            'share_identity'=>['POST'], 'contact_invoice_access'=>['POST'],
            'groups'=>['GET','HEAD','POST'], 'group'=>['POST'], 'delete_group'=>['POST'],
            'import'=>['GET','HEAD','POST'],
            'get_customer_billing_and_shipping_details'=>['GET','HEAD'],
            // Private, owner-checked native contact avatar; no general contact access.
            'contact_profile_image'=>['GET','HEAD'],
        ],
        'leads'=>[
            'index'=>['GET','HEAD','POST'],
            'file'=>['GET','HEAD'],
            'export'=>['GET','HEAD'],
        ],
        'contracts'=>[
            'index'=>['GET','HEAD'],
            'contract'=>['GET','HEAD','POST'],
            'pdf'=>['GET','HEAD'],
            'file'=>['GET','HEAD'],
        ],
        'gdpr'=>['contact_consent_opt_action'=>['POST']],
        'invoices'=>[
            'index'=>['GET','HEAD'], 'list_invoices'=>['GET','HEAD'], 'table'=>['POST'],
            'invoice'=>['GET','HEAD','POST'], 'client_change_data'=>['GET'],
            'drafts'=>['GET','HEAD','POST'], 'copy'=>['GET','HEAD','POST'],
            'recurring'=>['GET','HEAD','POST'], 'standalone_billing'=>['GET','HEAD','POST'],
            'validate_invoice_number'=>['POST'], 'get_invoice_data_ajax'=>['GET','HEAD'],
            'add_note'=>['POST'], 'get_notes'=>['GET','HEAD'],
            'pause_overdue_reminders'=>['POST'], 'resume_overdue_reminders'=>['POST'],
            'mark_as_sent'=>['POST'], 'mark_as_cancelled'=>['POST'], 'unmark_as_cancelled'=>['POST'],
            'get_invoices_total'=>['POST'], 'record_invoice_payment_ajax'=>['GET','HEAD'],
            'record_payment'=>['POST'], 'delete_attachment'=>['POST'],
            'pdf'=>['GET','HEAD'], 'get_due_date'=>['POST'],
        ],
        'estimates'=>[
            // NativeCompanySalesAdminHttp owns the complete official workspace
            // for the list/form/detail mutations; no legacy estimates_model is
            // loaded when this route is admitted.
            'index'=>['GET','HEAD','POST'], 'list_estimates'=>['GET','HEAD','POST'],
            'estimate'=>['GET','HEAD','POST'], 'pdf'=>['GET','HEAD'], 'get_due_date'=>['POST'],
        ],
        'proposals'=>[
            'index'=>['GET','HEAD','POST'], 'list_proposals'=>['GET','HEAD','POST'],
            'proposal'=>['GET','HEAD','POST'], 'pdf'=>['GET','HEAD'], 'get_due_date'=>['POST'],
        ],
        'credit_notes'=>[
            'index'=>['GET','HEAD','POST'], 'list_credit_notes'=>['GET','HEAD','POST'],
        ],
        'expenses'=>[
            'index'=>['GET','HEAD','POST'], 'list_expenses'=>['GET','HEAD','POST'],
            'copy'=>['GET','HEAD','POST'], 'purchases'=>['GET','HEAD','POST'],
        ],
        'reports'=>['index'=>['GET','HEAD']],
        'payments'=>[
            'post_refund'=>['GET','HEAD','POST'], 'post_refund_manual'=>['GET','HEAD','POST'],
        ],
        'staff'=>[
            'index'=>['GET','HEAD','POST'], 'member'=>['GET','HEAD','POST'],
            'role_changed'=>['GET'],
            'save_dashboard_widgets_order'=>['POST'], 'save_dashboard_widgets_visibility'=>['POST'],
            'reset_dashboard'=>['POST'], 'save_hidden_table_columns'=>['POST'],
            'timesheets'=>['GET','HEAD'], 'delete'=>['POST'],
            'edit_profile'=>['GET','HEAD','POST'], 'remove_staff_profile_image'=>['POST'],
            'hr'=>['GET','HEAD','POST'], 'hr_file'=>['GET','HEAD'], 'hr_pdf'=>['GET','HEAD'], 'hr_export'=>['GET','HEAD'], 'hr_mine'=>['GET','HEAD','POST'], 'hr_mine_file'=>['GET','HEAD'],
            'change_password_profile'=>['POST'], 'profile'=>['GET','HEAD'],
            'change_staff_status'=>['POST'], 'notifications'=>['POST'],
            'update_two_factor'=>['POST'], 'verify_google_two_factor'=>['POST'],
        ],
        'invoice_items' => [
            'index'=>['GET','HEAD'], 'table'=>['POST'], 'manage'=>['POST'], 'import'=>['GET','HEAD','POST'],
            'add_group'=>['POST'], 'update_group'=>['POST'], 'delete_group'=>['POST'], 'delete'=>['POST'],
            'bulk_action'=>['POST'], 'search'=>['POST'], 'get_item_by_id'=>['GET'], 'copy'=>['POST'],
        ],
        // The original Utilities calendar controller and views have explicit
        // issuer scoped readers/writers; keep every action and verb finite.
        'utilities' => [
            'calendar'=>['GET','HEAD','POST'], 'get_calendar_data'=>['GET'],
            'view_event'=>['GET','HEAD'], 'delete_event'=>['POST'],
        ],
    ];

    public static function admits(string $directory, string $module, string $controller, string $method, string $verb): bool
    {
        if(strtolower($module)==='banco_brasil'&&strtolower($controller)==='banco_brasil'
            &&in_array(trim($directory,'/'),['admin','../../modules/banco_brasil/controllers'],true)){
            $actions=['index'=>['GET','HEAD'],'local_noncash'=>['GET','HEAD','POST']];
            foreach(['save_channel_preferences','register_connection','register_layout','import_return','declare_contract','generate_remittance','retry_remittance','download_remittance','download_return','report_transmission','link_event','compare_cnab400','prepare_cnab400','recover_cnab400','reserve_cnab400','candidate_cnab400','settle_cnab400','declare_cnab400_approval','generate_cnab400','download_cnab400','report_cnab400','review_event','settle_event','settle_components_event','settle_tariff_event','declare_external_reversal','declare_api_profile','save_api_credentials','issue_api','resume_api_issue','query_api','cancel_api','settle_api','amend_api_due','amend_api_abatement','amend_api_reference','amend_api_address','amend_api_discount','amend_api_receipt_window','cancel_api_discount','assign_api_discount','configure_api_webhook','query_api_movements','preview_checkout','publish_checkout']as$action)$actions[$action]=['POST'];
            return in_array(strtoupper($verb),$actions[strtolower($method)]??[],true);
        }
        if (trim($directory, '/') !== 'admin' || $module !== '') { return false; }
        return in_array(strtoupper($verb), self::ACTIONS[strtolower($controller)][strtolower($method)] ?? [], true);
    }

    /** Navigation targets only; record IDs and submitted return URLs are never accepted. */
    public static function navigation(string $route): bool
    {
        // Do not decode or normalize attacker-controlled destinations into an admitted route.
        if (str_contains($route, '\\') || str_contains($route, '%') || str_contains($route, '?')
            || str_contains($route, '#') || preg_match('/[\x00-\x20\x7f]/', $route)) { return false; }
        return in_array($route, ['', 'dashboard', 'dashboard/index', 'issuers', 'todo', 'announcements', 'taxes', 'departments', 'tickets', 'tickets/add', 'tickets/priorities', 'tickets/statuses', 'tickets/services', 'tickets/predefined_replies', 'tickets/predefined_reply', 'tasks', 'tasks/task', 'projects', 'projects/project', 'clients', 'clients/client', 'clients/import', 'clients/groups', 'clients/all_contacts', 'leads', 'leads/index', 'contracts', 'contracts/contract', 'invoices', 'invoices/list_invoices', 'invoices/invoice', 'invoices/drafts', 'invoices/copy', 'invoices/recurring', 'estimates', 'estimates/list_estimates', 'estimates/estimate', 'proposals', 'proposals/list_proposals', 'proposals/proposal', 'credit_notes', 'credit_notes/list_credit_notes', 'expenses', 'expenses/list_expenses', 'expenses/copy', 'reports', 'payments/post_refund', 'payments/post_refund_manual', 'invoice_items', 'invoice_items/import', 'settings', 'settings/pusher', 'settings/asaas', 'settings/imports', 'settings/efi', 'settings/google_mail_oauth', 'settings/microsoft_mail_oauth', 'settings/api_tokens', 'utilities/calendar', 'banco_brasil', 'staff', 'staff/member', 'staff/hr', 'staff/hr_mine', 'projects/extras', 'projects/portal_rules', 'projects/billing_rates', 'projects/fixed_billing', 'projects/copies', 'projects/time_billing', 'tasks/copies', 'tasks/recurring', 'tasks/standalone', 'tasks/billing', 'tasks/time_batches', 'tickets/ai', 'tickets/connect', 'tickets/connect_api', 'invoices/standalone_billing', 'expenses/purchases'], true);
    }
}
