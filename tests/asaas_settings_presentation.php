<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require __DIR__ . '/../modules/asaas/libraries/Asaas/SettingsPresentation.php';
use Asaas\SettingsPresentation;

$admin = true;
function is_admin(): bool { return $GLOBALS['admin']; }
function admin_url($path): string { return 'https://crm.example.test/admin/' . $path; }
function db_prefix(): string { return 'test_'; }
function html_escape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
final class CompanySettingsRuntime
{
    public static bool $active = false;
    public static bool $primary = false;
    public static function active(): bool { return self::$active; }
    public static function isPrimary(): bool { return self::$primary; }
}
final class PresentationDb
{
    public array $rows = [];
    public array $calls = [];
    public bool $ready = true;
    public function table_exists($name): bool { $this->calls[] = ['table', $name]; return $this->ready; }
    public function field_exists($field, $table): bool { return $this->ready; }
    public function select($columns): self { $this->calls[] = ['select', $columns]; return $this; }
    public function where($field, $value): self { $this->calls[] = ['where', $field, $value]; return $this; }
    public function order_by($field, $order): self { return $this; }
    public function get($table): self { $this->calls[] = ['get', $table]; return $this; }
    public function result_array(): array { return $this->rows; }
}
$checks = 0;
function check($condition, string $label): void { global $checks; ++$checks; if (!$condition) { throw new RuntimeException($label); } }
$db = new PresentationDb();
$base = ['id' => 1, 'issuer_id' => null, 'provider' => 'asaas', 'label' => '<script>unsafe label</script>',
    'account_id' => 'synthetic-wallet', 'environment' => 'production', 'enabled' => 1, 'public_id' => str_repeat('a', 32),
    'token_hash' => 'NEVER_RENDER_HASH', 'token_cipher' => 'NEVER_RENDER_CIPHER', 'payload' => 'NEVER_RENDER_PAYLOAD'];
$db->rows = [$base, array_replace($base, ['id' => 2, 'environment' => 'sandbox', 'enabled' => 0, 'public_id' => str_repeat('b', 32)]),
    array_replace($base, ['id' => 3, 'issuer_id' => 7]), array_replace($base, ['id' => 4, 'provider' => 'other']),
    array_replace($base, ['id' => 5, 'environment' => 'invalid']), array_replace($base, ['id' => 6, 'public_id' => '../invalid'])];
$_SERVER['HTTP_HOST'] = 'untrusted.example'; $_SERVER['HTTP_X_FORWARDED_HOST'] = 'untrusted.example';
$data = SettingsPresentation::legacy($db, 'https://crm.example.test/subdir/', 'index.php');
check($data['state'] === 'ready' && count($data['integrations']) === 2, 'both valid legacy environments only');
check($data['integrations'][0]['callback_url'] === 'https://crm.example.test/subdir/index.php/asaas/gateways/callback/integration/' . str_repeat('a', 32), 'trusted base and index page preserved');
check($data['integrations'][1]['enabled'] === false, 'disabled receivers remain explicitly identified');
check(!str_contains(json_encode($data), 'NEVER_RENDER') && !str_contains(json_encode($data), 'untrusted.example'), 'DTO excludes secrets and hostile proxy headers');
check(in_array(['where', 'issuer_id', null], $db->calls, true) && in_array(['where', 'provider', 'asaas'], $db->calls, true), 'database query enforces legacy provider scope');
check(!str_contains(json_encode(array_filter($db->calls, fn($call) => $call[0] === 'select')), 'token'), 'query never hydrates hash or cipher');
check(array_keys($data['integrations'][0]) === ['id','label','account_id','environment','enabled','callback_url'], 'exact public DTO allowlist');
check(SettingsPresentation::apiUrls() === ['production' => 'https://api.asaas.com/v3', 'sandbox' => 'https://api-sandbox.asaas.com/v3'], 'API URLs match bundled SDK enum');
$before = count($db->calls); $admin = false;
check(SettingsPresentation::legacy($db, 'https://crm.example.test/')['state'] === 'restricted' && count($db->calls) === $before, 'settings viewer cannot read admin receivers');
$admin = true; CompanySettingsRuntime::$active = true;
check(SettingsPresentation::legacy($db, 'https://crm.example.test/')['state'] === 'restricted' && count($db->calls) === $before, 'secondary company cannot load global receivers even as admin');
CompanySettingsRuntime::$primary = true;
check(SettingsPresentation::legacy($db, 'https://crm.example.test/')['state'] === 'ready', 'primary admin sees legacy receivers');
CompanySettingsRuntime::$active = false;
foreach (['http://crm.example.test/', 'https://user:pass@crm.example.test/', 'https://crm.example.test/?token=x', 'https://crm.example.test/#fragment', 'https://crm.example.test/a/../'] as $invalid) {
    check(SettingsPresentation::legacy($db, $invalid)['state'] === 'invalid_base', 'invalid configured URL never replaced with guessed URL');
}
$db->rows = []; check(SettingsPresentation::legacy($db, 'https://crm.example.test/')['state'] === 'empty', 'missing receiver has explicit empty state');
$db->ready = false; check(SettingsPresentation::legacy($db, 'https://crm.example.test/')['state'] === 'schema_pending', 'missing schema remains pending');
$asaas_summary = $data; ob_start(); require __DIR__ . '/../modules/asaas/views/settings_summary.php'; $html = ob_get_clean();
check(str_contains($html, '&lt;script&gt;unsafe label&lt;/script&gt;') && !str_contains($html, '<script>unsafe label</script>'), 'view escapes account labels');
check(!preg_match('/\bname\s*=|<form\b/', $html), 'presentation has no posted names or nested forms');
check(substr_count($html, 'data-asaas-copy=') === 4 && substr_count($html, 'readonly') === 4, 'both API and receiver URLs are readonly copyable');
check(!str_contains($html, 'NEVER_RENDER') && !str_contains($html, '?token='), 'render has no credential or token URL');
echo "Asaas settings presentation: {$checks} checks passed\n";
