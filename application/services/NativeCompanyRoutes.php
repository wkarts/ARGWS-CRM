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
            'index' => ['GET', 'HEAD'],
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
            'index'=>['GET','HEAD','POST'], 'bulk_action'=>['POST'],
        ],
        // Settings::index is the original native Settings route. Its bounded
        // form adapters decide which groups and writes are available.
        'settings' => [
            'index'=>['GET','HEAD','POST'],
        ],
        'emails' => [
            'sent_smtp_test_email'=>['POST'], 'delete_queued_email'=>['POST'], 'clear_queued_emails'=>['POST'],
        ],
        'misc' => [
            'dismiss_announcement'=>['POST'],
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
            foreach(['save_channel_preferences','register_connection','register_layout','import_return','declare_contract','generate_remittance','retry_remittance','download_remittance','report_transmission','link_event','review_event','settle_event','settle_components_event','settle_tariff_event','declare_external_reversal','declare_api_profile','save_api_credentials','issue_api','resume_api_issue','query_api','cancel_api','settle_api','amend_api_due','amend_api_abatement','amend_api_reference','amend_api_address','amend_api_discount','amend_api_receipt_window','cancel_api_discount','assign_api_discount','configure_api_webhook','query_api_movements','preview_checkout','publish_checkout']as$action)$actions[$action]=['POST'];
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
        return in_array($route, ['', 'dashboard', 'dashboard/index', 'issuers', 'todo', 'announcements', 'taxes', 'departments', 'tickets', 'invoice_items', 'invoice_items/import', 'settings', 'utilities/calendar', 'banco_brasil'], true);
    }
}
