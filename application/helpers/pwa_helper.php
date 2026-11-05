<?php

/** Public installation assets only; no session, database or company projection. */
function native_pwa_revision(): string
{
    static $revision;
    if (is_string($revision)) {
        return $revision;
    }
    $root = defined('FCPATH') ? FCPATH : dirname(__DIR__, 2) . '/';
    $parts = [];
    foreach (['application/config/branding.php', 'assets/pwa/worker.js',
        'assets/pwa/icon-192.png', 'assets/pwa/icon-512.png',
        'assets/pwa/icon-maskable-512.png'] as $path) {
        $parts[] = $path . ':' . hash_file('sha256', $root . $path);
    }
    return $revision = hash('sha256', implode('|', $parts));
}
