<?php

// Standalone helper: the application/database may not exist yet. Do not load .env.
// Installer identity is independent from runtime company/issuer/PWA branding.
function crm_installer_display_name(): string
{
    $name = getenv('INSTALLER_DISPLAY_NAME');
    $name = is_string($name) ? trim($name) : '';

    return $name !== '' ? $name : 'ARGWS CRM';
}

function crm_installer_display_name_html(): string
{
    return htmlspecialchars(crm_installer_display_name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
