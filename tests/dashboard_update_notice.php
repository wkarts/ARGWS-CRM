<?php
// Synthetic messages only: no application bootstrap, database, session or network.
define('BASEPATH', __DIR__);
require __DIR__ . '/../application/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
require __DIR__ . '/../application/helpers/misc_helper.php';

$GLOBALS['notice'] = '';
$GLOBALS['admin'] = false;
$GLOBALS['writes'] = 0;
$checks = 0;

function get_option($name)
{
    check_notice($name === 'update_info_message', 'Read only the update notice option');
    return $GLOBALS['notice'];
}

function update_option($name, $value)
{
    check_notice($name === 'update_info_message', 'Write only the update notice option');
    $GLOBALS['notice'] = $value;
    ++$GLOBALS['writes'];
}

function is_admin()
{
    return $GLOBALS['admin'];
}

function hooks()
{
    throw new RuntimeException('Notice sanitization must not be disabled by a hook');
}

function check_notice($condition, $message)
{
    global $checks;
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function notice_document($fragment)
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>'
        . '<div id="wrapper"><div class="content"><div id="dashboard-row" class="row">'
        . $fragment . '<div id="dashboard">Cards</div></div></div></div></body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($dom);
}

function notice_contains_dashboard($fragment)
{
    return notice_document($fragment)->query(
        '//*[@id="dashboard"]/ancestor::*[contains(concat(" ",normalize-space(@class)," ")," alert ")]'
    )->length > 0;
}

function check_notice_containment($fragment)
{
    $xpath = notice_document($fragment);
    check_notice($xpath->query('//*[@id="dashboard-row"]/*[@id="dashboard"]')->length === 1,
        'The dashboard stays a direct sibling of the notice');
    check_notice(!notice_contains_dashboard($fragment), 'The notice cannot capture dashboard cards');
}

function render_notice()
{
    ob_start();
    _maybe_show_just_updated_message();
    return ob_get_clean();
}

$broken = '<div class="col-md-12"><div class="alert alert-success">OI';
$fixed = app_safe_update_message($broken);
check_notice(notice_contains_dashboard($broken), 'Reproduce an unclosed alert capturing the dashboard');
check_notice_containment($fixed);
check_notice(substr_count($fixed, '<div') === substr_count($fixed, '</div>'), 'Balance the native divs');
check_notice(strpos($fixed, 'OI') !== false, 'Keep legitimate message text');
check_notice(strpos($fixed, 'alert-success') !== false, 'Keep native success alert styling');

foreach ([
    '</div></div><div class="alert alert-success">Oi',
    '<table><tr><td><div class="alert">Oi',
    '<p><div class="alert alert-info">Atualização <b>concluída',
    '<!-- unclosed comment',
    '<svg><style>*{color:red}</style><script>window.noticeInjected=1</script></svg>Texto',
    '<math><mtext><table><mglyph><style><!--</style><img src=x onerror="window.noticeInjected=1">',
] as $malformed) {
    check_notice_containment(app_safe_update_message($malformed));
}

$attack = '<script>window.noticeInjected=1</script><style>body{display:none}</style>'
    . '<div id="wrapper" class="alert alert-warning hidden modal" style="position:fixed" onclick="window.noticeInjected=1">Aviso '
    . '<a href="javascript:window.noticeInjected=1">ruim</a><a href="https://example.org/help" title="Ajuda">Ajuda</a>'
    . '<img src="https://example.org/track" onerror="window.noticeInjected=1">'
    . '<form action="/admin/delete"><input name="x"><button>Enviar</button></form>'
    . '<iframe src="https://example.org"></iframe><object data="/remote"></object>'
    . '<link rel="stylesheet" href="/remote.css"><meta http-equiv="refresh" content="0;url=/remote"></div>';
$safe = app_safe_update_message($attack);
foreach (['<script', '<style', 'onclick', 'onerror', 'javascript:', '<img', '<form', '<input', '<button',
    '<iframe', '<object', '<link', '<meta', 'style=', 'id=', 'hidden', 'modal', 'noticeInjected'] as $forbidden) {
    check_notice(stripos($safe, $forbidden) === false, 'Reject unsafe markup: ' . $forbidden);
}
check_notice(strpos($safe, 'href="https://example.org/help"') !== false, 'Keep HTTPS documentation links');
check_notice(strpos($safe, 'title="Ajuda"') !== false, 'Keep link titles');
check_notice(strpos($safe, 'Aviso') !== false, 'Keep the notice text');
check_notice(strpos($safe, 'alert-warning') !== false, 'Keep native warning class');
check_notice_containment($safe);

foreach (['javascript:alert(1)', 'jav&#x61;script:alert(1)',
    'vbscript:alert(1)', 'data:text/html,example', 'file:///etc/passwd', 'ftp://example.org/file'] as $uri) {
    $html = app_safe_update_message('<a href="' . $uri . '">Orientação</a>');
    check_notice(strpos($html, 'href=') === false, 'Reject unsafe URI: ' . $uri);
    check_notice(strpos($html, 'Orientação') !== false, 'Keep link text after rejecting its URI');
}
foreach (['java&#x09;script:alert(1)', 'java&#x0a;script:alert(1)'] as $obfuscated) {
    $html = app_safe_update_message('<a href="' . $obfuscated . '">Orientação</a>');
    check_notice($html === '<a href="java%20script%3Aalert(1)">Orientação</a>',
        'Neutralize control-character schemes as an inert relative URL');
}
foreach (['http://example.org/help', 'https://example.org/help', '/admin/settings', '#details'] as $uri) {
    check_notice(strpos(app_safe_update_message('<a href="' . $uri . '">Ajuda</a>'), 'href="' . $uri . '"') !== false,
        'Keep safe absolute, relative and fragment links');
}

$legitimate = '<div class="col-md-12"><div class="alert alert-info bold"><h4 class="bold">Atualização 3.1.1</h4>'
    . '<p>Olá! Confira a <strong>configuração</strong> e o <em>cache</em>.</p>'
    . '<ul><li>Primeiro passo</li><li><a href="https://example.org/help">Documentação</a></li></ul></div></div>';
check_notice(app_safe_update_message($legitimate) === $legitimate, 'Preserve supported legitimate notice markup');
check_notice(app_safe_update_message('Atualização concluída &amp; revisada.') === 'Atualização concluída &amp; revisada.',
    'Preserve accented text and HTML entities');
check_notice(app_safe_update_message('') === '', 'Empty notice stays empty');
check_notice(app_safe_update_message('0') === '0', 'A non-empty numeric message is preserved');

// Historical migrations only used script for reload. Never execute stored script,
// including previously legitimate reloads; preserve all surrounding message text.
$legacy = $legitimate . '<script>setTimeout(function(){window.location.reload();},10000);</script>';
check_notice(app_safe_update_message($legacy) === $legitimate, 'Remove legacy reload but retain the whole notice');
check_notice(app_safe_update_message('<script>window.location.reload();</script>') === '', 'Script-only legacy notice is inert');

app_set_update_message_info('311');
$current = $GLOBALS['notice'];
check_notice(strpos($current, 'versão 3.1.1') !== false, 'Keep native framework version formatting');
check_notice(strpos($current, 'Olá!') !== false && strpos($current, 'Você está') !== false, 'Current generator emits UTF-8');
check_notice(strpos($current, 'recarregue a página') !== false, 'Give manual reload guidance');
check_notice(stripos($current, '<script') === false && strpos($current, 'setTimeout') === false, 'New notices never contain reload code');
check_notice(str_contains(app_safe_update_message($current), 'Atualização concluída.'), 'Current notice survives sanitization');
check_notice_containment(app_safe_update_message($current));

$GLOBALS['notice'] = $broken;
$GLOBALS['writes'] = 0;
check_notice(render_notice() === '', 'Non-admin cannot see the notice');
check_notice($GLOBALS['notice'] === $broken && $GLOBALS['writes'] === 0, 'Non-admin cannot consume the notice');
$GLOBALS['admin'] = true;
check_notice(render_notice() === $fixed, 'Admin receives only the sanitized notice');
check_notice($GLOBALS['notice'] === '' && $GLOBALS['writes'] === 1, 'Admin consumes the notice once');
check_notice(render_notice() === '' && $GLOBALS['writes'] === 1, 'No repeat or extra write on the next request');
$GLOBALS['notice'] = '<script>window.location.reload();</script>';
check_notice(render_notice() === '' && $GLOBALS['notice'] === '', 'Legacy script-only notice is consumed without executing');

// Optional local fixtures for a browser smoke test; all content above is synthetic.
if (isset($argv[1])) {
    $written = file_put_contents($argv[1], json_encode([
        'before' => $broken, 'after' => $fixed, 'hostile' => $safe, 'current' => app_safe_update_message($current),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    check_notice($written !== false, 'Write synthetic browser fixture JSON');
}
echo "PASS $checks dashboard update notice checks\n";
