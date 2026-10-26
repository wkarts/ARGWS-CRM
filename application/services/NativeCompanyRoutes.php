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
        'invoice_items' => [
            'index'=>['GET','HEAD'], 'table'=>['POST'], 'manage'=>['POST'], 'import'=>['GET','HEAD','POST'],
            'add_group'=>['POST'], 'update_group'=>['POST'], 'delete_group'=>['POST'], 'delete'=>['POST'],
            'bulk_action'=>['POST'], 'search'=>['POST'], 'get_item_by_id'=>['GET'], 'copy'=>['POST'],
        ],
    ];

    public static function admits(string $directory, string $module, string $controller, string $method, string $verb): bool
    {
        if (trim($directory, '/') !== 'admin' || $module !== '') { return false; }
        return in_array(strtoupper($verb), self::ACTIONS[strtolower($controller)][strtolower($method)] ?? [], true);
    }

    /** Navigation targets only; record IDs and submitted return URLs are never accepted. */
    public static function navigation(string $route): bool
    {
        // Do not decode or normalize attacker-controlled destinations into an admitted route.
        if (str_contains($route, '\\') || str_contains($route, '%') || str_contains($route, '?')
            || str_contains($route, '#') || preg_match('/[\x00-\x20\x7f]/', $route)) { return false; }
        return in_array($route, ['', 'dashboard', 'dashboard/index', 'todo', 'announcements', 'taxes', 'tickets', 'invoice_items', 'invoice_items/import', 'settings'], true);
    }
}
