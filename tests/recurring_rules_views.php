<?php
/** Render-only contract: real CI form/escaping helpers, no browser/session/server claim. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line); return false; });
define('BASEPATH', dirname(__DIR__) . '/system/'); define('APPPATH', dirname(__DIR__) . '/application/');
$checks = 0; $canEdit = true;
function check($condition, $message): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
function config_item($name) { return $name === 'charset' ? 'UTF-8' : null; }
function init_head(): void { echo '<!doctype html><html><head><meta charset="utf-8"></head><body>'; }
function init_tail(): void {}
function admin_url($path = ''): string { return 'https://crm.example.invalid/admin/' . $path; }
function staff_can($permission, $feature): bool { global $canEdit; return $canEdit; }
function get_currency($id): object { return (object) ['name' => 'BRL']; }
$context = new stdClass();
$context->config = new class {
    public bool $globalCsrf = false;
    public function item($key) { return $key === 'csrf_protection' ? $this->globalCsrf : null; }
    public function base_url(): string { return 'https://crm.example.invalid/'; }
    public function site_url($path): string { return $this->base_url() . ltrim($path, '/'); }
};
$context->security = new class {
    public function get_random_bytes($n): string { return "\0"; }
    public function get_csrf_token_name(): string { return 'global_csrf'; }
    public function get_csrf_hash(): string { return 'synthetic-global-csrf'; }
};
function &get_instance() { global $context; return $context; }
require BASEPATH . 'core/Common.php';
require BASEPATH . 'helpers/form_helper.php';
require APPPATH . 'config/hooks.php'; // The production e() helper; hook callbacks are not run.
function render_view(string $name, array $data): string
{
    extract($data, EXTR_SKIP); ob_start();
    try { require APPPATH . 'views/admin/recurring_rules/' . $name . '.php'; return ob_get_contents(); }
    finally { ob_end_clean(); }
}
function hidden_fields(string $html): array
{
    preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"\s*\/?\>/', $html, $matches, PREG_SET_ORDER);
    return array_map(fn($m) => [html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')], $matches);
}
$csrf = str_repeat('a', 64); $requestKey = str_repeat('b', 64); $token = str_repeat('c', 64);
$attack = '\"><script>alert("synthetic")</script><img src=x onerror=alert(1)>';
$shared = ['recurring_rules_csrf' => $csrf];
foreach ([false, true] as $globalCsrf) {
    $context->config->globalCsrf = $globalCsrf;
    $html = render_view('form', $shared + ['error' => $attack, 'input' => ['title' => $attack, 'request_key' => $requestKey,
        'clientid' => '10', 'calendar_group' => $attack, 'items' => [['id' => '4', 'qty' => '0.30']]]]);
    $hidden = hidden_fields($html);
    check(in_array(['recurring_rules_csrf', $csrf], $hidden, true), 'creation own CSRF present with global setting ' . (int) $globalCsrf);
    check(in_array(['request_key', $requestKey], $hidden, true) && in_array(['emitter_key', 'legacy'], $hidden, true), 'request identity and explicit emitter preserved');
    check(str_contains($html, 'method="post"') && str_contains($html, '/admin/recurring_rules/create'), 'create uses POST target');
    check(!str_contains($html, $attack) && str_contains($html, '&lt;script&gt;'), 'creation values/errors escaped by production helper');
    check(str_contains($html, 'name="items[0][id]"') && str_contains($html, 'name="items[0][qty]" value="0.30"'), 'catalogue nested form field contract');
    check(in_array(['global_csrf', 'synthetic-global-csrf'], $hidden, true) === $globalCsrf, 'global CSRF coexists when enabled');
}
$context->config->globalCsrf = false;
$schedule = ['state' => 'active', 'anchor_date' => '2024-01-31', 'anchor_due' => '2024-02-05', 'interval_count' => 1, 'interval_unit' => 'MONTH', 'due_mode' => 'days', 'emitter_key' => 'legacy'];
$row = ['id' => 3, 'clientid' => 10, 'title' => $attack, 'calendar_group' => $attack, 'schedule' => $schedule, 'preview' => [['issue' => '2024-02-29', 'due' => '2024-03-05']], 'error' => null];
$html = render_view('list', $shared + ['source' => 'rules', 'state' => '', 'calendar_group' => $attack, 'page' => 2, 'more' => true, 'rows' => [$row, array_replace($row, ['id' => 7])]]);
preg_match_all('/name="ids\[\]" value="([0-9]+)"/', $html, $matches);
check($matches[1] === ['3', '7'], 'list exposes exact visible selection only');
check(in_array(['source', 'rules'], hidden_fields($html), true), 'source is an explicit hidden field');
check(in_array(['recurring_rules_csrf', $csrf], hidden_fields($html), true), 'list preview includes CSRF');
check(str_contains($html, 'page=1') && str_contains($html, 'page=3'), 'pagination preserves previous and next navigation');
check(!str_contains($html, $attack) && str_contains($html, '&lt;script&gt;'), 'list text and filters escaped');
$canEdit = false;
$html = render_view('list', $shared + ['source' => 'rules', 'state' => '', 'calendar_group' => '', 'page' => 1, 'more' => false, 'rows' => [$row]]);
check(!str_contains($html, 'name="ids[]"') && !str_contains($html, 'name="action"'), 'read-only staff cannot select or invoke list actions');
$canEdit = true;
$previewRow = ['id' => 3, 'before' => $schedule, 'after' => array_replace($schedule, ['due_mode' => 'fixed_day']),
    'before_dates' => $row['preview'], 'after_dates' => $row['preview'], 'error' => null];
$html = render_view('preview', $shared + ['source' => 'rules', 'action' => 'due_mode', 'mode' => 'fixed_day', 'token' => $token, 'rows' => [$previewRow]]);
check(hidden_fields($html) === [['recurring_rules_csrf', $csrf], ['token', $token]], 'apply accepts token and CSRF, not replacement IDs or policy');
check(str_contains($html, 'name="confirm_future" value="1" required'), 'explicit future-only confirmation required');
check(str_contains($html, '/admin/recurring_rules/apply') && str_contains($html, '>Cancelar</a>'), 'preview apply/cancel navigation');
$html = render_view('preview', $shared + ['source' => 'legacy', 'action' => 'migrate', 'mode' => '', 'token' => null, 'rows' => [['id' => 3, 'error' => $attack]]]);
check(!str_contains($html, '<form') && !str_contains($html, 'name="token"'), 'ineligible preview has no apply form');
check(!str_contains($html, $attack) && str_contains($html, '&lt;script&gt;'), 'ineligible reason escaped');
$rule = ['id' => 3, 'title' => $attack, 'state' => 'paused', 'clientid' => 10, 'emitter_key' => 'legacy', 'calendar_group' => $attack,
    'next_issue_date' => '2024-02-29', 'cycles_emitted' => 0, 'cycles_limit' => 0, 'due_mode' => 'days'];
$template = ['newitems' => [['description' => $attack, 'long_description' => $attack, 'qty' => '1.00', 'rate' => '0.05', 'taxname' => [$attack]]], 'subtotal' => '0.05', 'total' => '0.06', 'currency' => 1];
$detail = $shared + ['rule' => $rule, 'template' => $template, 'runs' => [['cycle_date' => '2024-02-29', 'invoice_id' => 9]],
    'audit' => [['created_at' => '2024-01-01 00:00:00', 'staff_id' => 7, 'action' => 'create', 'before_json' => $attack, 'after_json' => '{}']]];
$html = render_view('view', $detail);
check(in_array(['ids[]', '3'], hidden_fields($html), true) && in_array(['source', 'rules'], hidden_fields($html), true), 'detail submits exact one-rule selection');
check(str_contains($html, 'value="resume"') && !str_contains($html, 'value="pause"'), 'paused detail offers resume');
check(str_contains($html, '/admin/invoices/list_invoices/9'), 'generated invoice stays linked');
check(!str_contains($html, $attack) && str_contains($html, '&lt;script&gt;'), 'detail title/items/audit escaped');
$detail['rule']['state'] = 'ended';
check(!str_contains(render_view('view', $detail), '<form'), 'ended rule has no mutating form');
$html = render_view('result', ['result' => ['ids' => [3, 7], 'rule_ids' => [3, 7], 'applied_at' => '2024-01-01 00:00:00']]);
check(str_contains($html, '2 registro(s)') && str_contains($html, '/admin/recurring_rules/view/3') && str_contains($html, '/admin/recurring_rules/view/7'), 'result links exact applied rules');
echo "Recurring rule views: $checks checks passed (rendered real templates and CI form/escaping helpers; no authenticated browser QA).\n";
