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
        return in_array($route, ['', 'dashboard', 'dashboard/index'], true);
    }
}
