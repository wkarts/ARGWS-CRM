<?php
/** The native company settings partial never exposes a foreign/unpermitted certificate action. */
define('BASEPATH', __DIR__);
final class CompanyRequestScope {
    public static $current = null;
    public static function context() { return self::$current; }
    public static function adminUrl($owner, string $path): string { return '/admin/'.$path; }
}
final class CompanySettingsRuntime {
    public static bool $enabled = false;
    public static bool $edit = false;
    public static function active(): bool { return self::$enabled; }
    public static function can(string $capability): bool { return $capability === 'edit' && self::$edit; }
}
function _l($key) { return $key; }
function render_input(...$args) { return ''; }
function get_option($key) { return ''; }
function render_custom_fields(...$args) { return ''; }
function render_textarea(...$args) { return ''; }
function clear_textarea_breaks($value) { return $value; }
function get_company_custom_fields() { return []; }
$renderer = new class {
    public function render(): string { ob_start(); include __DIR__.'/../application/views/admin/settings/includes/company.php'; return ob_get_clean(); }
};
$context = new class { public ?int $issuer = 41; public function issuerId(): ?int { return $this->issuer; } };
$cases = [[null, false, false, false], [$context, false, true, false], [$context, true, false, false], [$context, true, true, true]];
foreach ($cases as [$current, $enabled, $edit, $expected]) {
    CompanyRequestScope::$current = $current;
    CompanySettingsRuntime::$enabled = $enabled;
    CompanySettingsRuntime::$edit = $edit;
    $html = $renderer->render();
    if (str_contains($html, 'href="/admin/settings?group=certificates"') !== $expected) { throw new RuntimeException('Official Settings certificate link authorization mismatch'); }
    if (str_contains($html, 'company_certificates')) { throw new RuntimeException('Parallel certificate route must not be rendered'); }
}
$context->issuer = null;
if (str_contains($renderer->render(), 'Certificado A1')) { throw new RuntimeException('Missing selected company must not expose certificate action'); }
echo "PASS: native company settings certificate link permission/context matrix.\n";
