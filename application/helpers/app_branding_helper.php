<?php

// Standalone: the PWA bootstrap and the application must resolve the same name.
function crm_resolve_app_display_name(array $config, $environmentValue): string
{
    $name = is_string($environmentValue) ? trim($environmentValue) : '';
    if ($name !== '') {
        return $name;
    }

    $name = $config['app_display_name'] ?? '';
    $name = is_string($name) ? trim($name) : '';

    return $name !== '' ? $name : 'CRM';
}

function crm_app_display_name(): string
{
    static $branding;
    if ($branding === null) {
        $branding = (static function (): array {
            $config = [];
            require __DIR__ . '/../config/branding.php';

            return is_array($config) ? $config : [];
        })();
    }

    return crm_resolve_app_display_name($branding, getenv('APP_DISPLAY_NAME'));
}

function crm_app_display_name_html(): string
{
    return htmlspecialchars(crm_app_display_name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function crm_app_page_title(?string $pageTitle = null): string
{
    $name = crm_app_display_name();
    $pageTitle = trim($pageTitle ?? '');

    return $pageTitle !== '' && $pageTitle !== $name ? $pageTitle . ' | ' . $name : $name;
}
