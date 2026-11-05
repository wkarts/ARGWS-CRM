<?php

// Public product identity, also read before the framework by the PWA bootstrap.
// No secrets, database access or fiscal/issuer settings belong in this file.
// A nonempty APP_DISPLAY_NAME environment value takes precedence.
$config['app_display_name'] = 'CRM';

// Public installation metadata only. Do not put customer/private names here.
// Empty name uses APP_DISPLAY_NAME/app_display_name, then ARGWS CRM for the default.
$config['pwa_installation_name'] = '';
$config['pwa_theme_color'] = '#2563eb';
