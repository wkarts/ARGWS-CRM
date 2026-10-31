<?php

// Public product identity, also read before the framework by the PWA bootstrap.
// No secrets, database access or fiscal/issuer settings belong in this file.
// A nonempty APP_DISPLAY_NAME environment value takes precedence.
$config['app_display_name'] = 'CRM';

// Optional public product icons: exact-size PNGs under assets/, not tenant uploads.
// Invalid/missing files fall back to neutral CRM icons; no DB/session is consulted.
$config['pwa_icon_192'] = '';
$config['pwa_icon_512'] = '';
// Maskable artwork must fit its foreground inside the central 80% safe circle.
$config['pwa_icon_maskable_512'] = '';
$config['pwa_theme_color'] = '#2563eb';
// Optional installation-level PWA name. Empty means company identity first.
$config['pwa_installation_name'] = '';
