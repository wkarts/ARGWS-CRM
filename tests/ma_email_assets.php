<?php
/** Real PDO fixtures. No SMTP, production data, network requests or live migration are used. */
declare(strict_types=1);
require __DIR__.'/issuers_core_support.php';
require dirname(__DIR__).'/modules/ma/services/MaEmailTemplateAssets.php';
class App_Model { public $db; }
require dirname(__DIR__).'/modules/ma/models/Ma_model.php';
function get_staff_user_id() { return 7; }
function get_option($key)
{
    if ($key === 'active_language') return $GLOBALS['ma_assets_language'] ?? 'portuguese_br';
    return get_instance()->db->query('SELECT value FROM '.db_prefix().'options WHERE name=?', [$key])->row_array()['value'] ?? '';
}
function update_option($key, $value)
{
    $db = get_instance()->db;
    if ($db->query('SELECT name FROM '.db_prefix().'options WHERE name=?', [$key])->num_rows()) return $db->query('UPDATE '.db_prefix().'options SET value=? WHERE name=?', [(string) $value, $key]) !== false;
    return $db->query('INSERT INTO '.db_prefix().'options(name,value) VALUES(?,?)', [$key, (string) $value]) !== false;
}
function base_url($uri = '') { return MA_ASSETS_BASE.ltrim($uri, '/'); }

const MA_ASSETS_BASE = 'https://crm.example.test:8443/customer-portal/';
const MA_ASSETS_PATH = 'modules/ma/assets/email-templates/ptbr/';

function maAssetsFixture(bool $globalLock = false): object
{
    $f = essentialFixture(); $db = $f->db; $p = $f->prefix;
    $db->query("CREATE TABLE {$p}ma_email_templates(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, name TEXT NOT NULL, color TEXT NOT NULL, category INT NOT NULL DEFAULT 0, published INT NOT NULL DEFAULT 1, language TEXT, data_html LONGTEXT, data_design LONGTEXT, description TEXT, addedfrom INT, dateadded DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_email_template_designs(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_template_id INT NOT NULL, language VARCHAR(40), data_html LONGTEXT, data_design LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_emails(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_template INT, name TEXT, subject TEXT, description TEXT, published INT DEFAULT 1, addedfrom INT, dateadded DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_email_designs(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_id INT NOT NULL, language VARCHAR(40), data_html LONGTEXT, data_design LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (['ma_campaigns', 'ma_email_logs', 'ma_marketing_messages'] as $table) $db->query("CREATE TABLE {$p}{$table}(id INT PRIMARY KEY, data LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->insert($p.'modules', ['module_name'=>'ma', 'installed_version'=>'1.1.1', 'active'=>0]);
    // The earlier real installer DDL section initializes this option before the seed block.
    $db->insert($p.'options', ['name'=>'ma_insert_email_template_default', 'value'=>'0']);
    $db->query("INSERT INTO {$p}ma_emails(id,email_template,name,subject,description,published,addedfrom,dateadded) VALUES(400,1,'Existing campaign','Assunto escolhido','Original',0,7,'2026-10-21 00:00:00')");
    foreach (['english', 'portuguese_br'] as $language) $db->insert($p.'ma_email_designs', ['email_id'=>400, 'language'=>$language, 'data_html'=>json_encode('Existing '.$language.' {client_name}'), 'data_design'=>'"{}"']);
    foreach (['ma_campaigns', 'ma_email_logs', 'ma_marketing_messages'] as $table) $db->insert($p.$table, ['id'=>1, 'data'=>'{"email":400,"active":true,"future":"2030-01-01"}']);
    if ($globalLock) {
        $db->query("CREATE TABLE {$p}issuers_lock(id INT PRIMARY KEY) ENGINE=InnoDB");
        $db->query("INSERT INTO {$p}issuers_lock VALUES(1)");
    }
    unset($db->data_cache['table_names']);
    $f->ci->app_modules = new class { public function get($name) { return ['headers'=>['version'=>'1.1.2']]; } };
    $f->model = (new ReflectionClass(Ma_model::class))->newInstanceWithoutConstructor();
    $f->model->db = $db;
    return $f;
}

function maAssetsRows(object $f, string $table): array
{
    return $f->db->query('SELECT * FROM '.$f->prefix.$table.' ORDER BY '.($table === 'modules' ? 'module_name' : 'id'))->result_array();
}

function maAssetsSnapshot(object $f): array
{
    $snapshot = [];
    foreach (['ma_email_templates', 'ma_email_template_designs', 'ma_emails', 'ma_email_designs', 'ma_campaigns', 'ma_email_logs', 'ma_marketing_messages', 'modules'] as $table) $snapshot[$table] = maAssetsRows($f, $table);
    return $snapshot;
}

function maAssetsAddDesign(object $f, int $id, array $body, string $language): void
{
    $f->db->insert($f->prefix.'ma_email_template_designs', ['email_template_id'=>$id, 'language'=>$language, 'data_html'=>$body['data_html'], 'data_design'=>$body['data_design']]);
}

function maAssetsSeed(object $f, array $item, array $overrides = [], ?array $portuguese = null): int
{
    $f->db->insert($f->prefix.'ma_email_templates', array_replace(array_merge($item['source'], $item['origin_metadata']), $overrides));
    $id = $f->db->insert_id();
    maAssetsAddDesign($f, $id, $item['source'], 'english');
    maAssetsAddDesign($f, $id, $portuguese ?? $item['target'], 'portuguese_br');
    return $id;
}

function maAssetsDesign(object $f, int $id, string $language): array
{
    return $f->db->query('SELECT * FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=? AND language=?', [$id, $language])->row_array();
}

function maAssetsThrows(callable $operation, string $expected, string $label): void
{
    $actual = null;
    try { $operation(); } catch (Throwable $error) { $actual = $error->getMessage(); }
    essentialCheck($actual === $expected, $label.' (actual: '.var_export($actual, true).')');
}

function maAssetsVersion(object $f): string
{
    return (string) $f->db->query('SELECT installed_version FROM '.$f->prefix.'modules WHERE module_name=?', ['ma'])->row_array()['installed_version'];
}

/** Execute the actual installer seed block without unrelated legacy installation DDL. */
function maAssetsInstallerSeed(object $f): void
{
    essentialUse($f);
    $CI = $f->ci;
    $source = file_get_contents(dirname(__DIR__).'/modules/ma/install.php');
    $offset = strpos($source, '// One catalogue for new installs and upgrades');
    essentialCheck($offset !== false, 'actual MA installer seed block found');
    $source = str_replace('__DIR__', var_export(dirname(__DIR__).'/modules/ma', true), substr($source, $offset));
    eval($source);
}

/** Composition keeps the real PDO database while injecting a failure on an exact update. */
final class MaAssetsFaultDb
{
    public PDO $pdo;
    public array $data_cache;
    public bool $db_debug = true;
    public bool $save_queries = true;
    public int $updates = 0;
    public function __construct(private EssentialTestDb $db, private int $failAt, private bool $ignore = false)
    {
        $this->pdo = $db->pdo;
        $this->data_cache =& $db->data_cache;
    }
    public function query($sql, $params = [])
    {
        if (str_starts_with($sql, 'UPDATE '.$this->db->prefix.'ma_email_template_designs') && ++$this->updates === $this->failAt) return $this->ignore ? new EssentialTestResult([]) : false;
        return $this->db->query($sql, $params);
    }
    public function __call($name, $args) { return $this->db->$name(...$args); }
}

$cataloguePath = dirname(__DIR__).'/modules/ma/resources/email_templates_ptbr.json';
$catalogueHash = hash_file('sha256', $cataloguePath);
$catalogue = MaEmailTemplateDefaults::catalogue();
$plan = json_decode(file_get_contents(dirname(__DIR__).'/modules/ma/docs/ptbr-image-plan.json'), true, 512, JSON_THROW_ON_ERROR);
$approvedMap = (new ReflectionClass(MaEmailTemplateAssets::class))->getConstant('ASSETS');
$approvedSources = [];
$approvedFiles = [];
foreach ($approvedMap as $entries) foreach ($entries as $entry) { $approvedSources[] = $entry[0]; $approvedFiles[] = $entry[1]; }
$affectedTemplateCount = count($approvedMap);
$assets = array_values(array_filter($plan['assets'], static fn($asset) => in_array($asset['source_url'], $approvedSources, true)));
essentialCheck(count($catalogue) === 7 && count($assets) === count($approvedSources), 'published catalogue and approved source manifest agree');
essentialCheck(count($approvedSources) === 7 && $affectedTemplateCount === 6, 'approved seven-image release changes six template designs');
foreach (['fitness-logo-ptbr.png', 'valentine-for-you-ptbr.png', 'black-friday-company-logo-ptbr.png', 'black-friday-super-sale-ptbr.png', 'google-opinion-ptbr.png', 'birthday-your-logo-ptbr.png', 'branding-analysis-ptbr.png'] as $filename) essentialCheck(in_array($filename, $approvedFiles, true), 'required approved asset remains covered '.$filename);
essentialCheck(!in_array('https://cdn.templates.unlayer.com/assets/1596365663073-rr.png', $approvedSources, true), 'brand image cannot enter translation allowlist');
// The analysis source was JPEG; the translated local deliverable is intentionally PNG.
foreach ($assets as &$asset) if ($asset['text_observed'] === 'Analysis') $asset['proposed_asset'] = MA_ASSETS_PATH.'branding-analysis-ptbr.png';
unset($asset);

foreach ($catalogue as $index => $item) {
    $target = MaEmailTemplateAssets::transform($item['target'], MA_ASSETS_BASE);
    $html = json_decode($target['data_html'], true, 512, JSON_THROW_ON_ERROR);
    $designString = json_decode($target['data_design'], true, 512, JSON_THROW_ON_ERROR);
    $design = json_decode($designString, true, 512, JSON_THROW_ON_ERROR);
    essentialCheck(is_string($html) && is_string($designString) && is_array($design), 'HTML and editor retain original storage layers '.$index);
    $reverse = $target;
    $expectedDesign = json_decode(json_decode($item['target']['data_design'], true, 512, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    $affected = 0;
    foreach ($assets as $asset) {
        foreach ($asset['design_references'] as $reference) {
            if ($reference['template'] !== $item['source']['name']) continue;
            ++$affected;
            $source = $asset['source_url'];
            $local = MA_ASSETS_BASE.$asset['proposed_asset'];
            $originalHtml = json_decode($item['target']['data_html'], true, 512, JSON_THROW_ON_ERROR);
            essentialCheck(substr_count($html, $local) === substr_count($originalHtml, $source) && !str_contains($html, $source), 'all paired HTML/VML URLs changed '.$asset['proposed_asset']);
            $leaf =& $expectedDesign;
            foreach (explode('.', $reference['path']) as $key) $leaf =& $leaf[$key];
            essentialCheck($leaf === $source, 'reviewed editor provenance matches '.$asset['proposed_asset']);
            $leaf = $local;
            unset($leaf);
            $reverse['data_html'] = str_replace(substr(json_encode($local, JSON_THROW_ON_ERROR), 1, -1), substr(json_encode($source, JSON_THROW_ON_ERROR), 1, -1), $reverse['data_html']);
            $reverse['data_design'] = str_replace(substr(json_encode(json_encode($local, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR), 1, -1), substr(json_encode(json_encode($source, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR), 1, -1), $reverse['data_design']);
            $path = dirname(__DIR__).'/'.$asset['proposed_asset'];
            essentialCheck(realpath($path) === $path && !is_link($path) && dirname($path) === dirname(__DIR__).'/'.rtrim(MA_ASSETS_PATH, '/'), 'public PNG stays in approved directory '.$asset['proposed_asset']);
            $size = getimagesize($path);
            essentialCheck($size !== false && $size[0] > 0 && $size[1] > 0 && $size[2] === IMAGETYPE_PNG, 'public asset is a real PNG '.$asset['proposed_asset']);
        }
    }
    essentialCheck($design === $expectedDesign, 'editor keys IDs counters numbers and unrelated URLs unchanged '.$index);
    essentialCheck($reverse === $item['target'], 'only approved encoded URL tokens changed in either field '.$index);
    if (!$affected) essentialCheck($target === $item['target'], 'unaffected or unapproved image template is byte-identical');
}
$branding = MaEmailTemplateAssets::transform($catalogue[6]['target'], MA_ASSETS_BASE);
essentialCheck(str_contains(json_decode($branding['data_html'], true), 'https://cdn.templates.unlayer.com/assets/1596365663073-rr.png'), 'Brand Specialist brand image is untouched');
$google = MaEmailTemplateAssets::transform($catalogue[4]['target'], MA_ASSETS_BASE);
essentialCheck(str_contains(json_decode($google['data_html'], true), MA_ASSETS_BASE.MA_ASSETS_PATH.'google-opinion-ptbr.png'), 'approved Google translation uses the local site URL');
foreach (['https://crm.example.test', 'https://crm.example.test/', 'http://localhost:8080/crm', 'https://crm.example.test/sub%20portal/'] as $base) {
    $target = MaEmailTemplateAssets::transform($catalogue[0]['target'], $base);
    essentialCheck(str_contains(json_decode($target['data_html'], true), rtrim($base, '/').'/'.MA_ASSETS_PATH.'fitness-logo-ptbr.png'), 'valid absolute application base '.$base);
}
foreach (['', '/crm/', '//example.test/', 'file:///tmp/crm/', 'javascript:alert(1)', 'ftp://example.test/', 'https://user:secret@example.test/', 'https://example.test/?query=1', 'https://example.test/#fragment', "https://example.test/\n", 'https://example.test/a/../b', 'https://example.test/./b', 'https://example.test/%2e%2e/', 'https://example.test/%252e%252e/', 'https://example.test/a%2fb/', 'https://example.test/a%5cb/', 'https://example.test/%0a/', 'https://example.test/a//b/', 'https://example.test/%zz/', 'https://example.test/a\\b/'] as $base) maAssetsThrows(fn() => MaEmailTemplateAssets::transform($catalogue[0]['target'], $base), 'ma_email_asset_base_url_invalid', 'unsafe or non-HTTP base rejected');
foreach (['../fitness-logo-ptbr.png', '/tmp/fitness-logo-ptbr.png', 'bad.php', 'not-a-packaged-image.png'] as $filename) maAssetsThrows(fn() => (new ReflectionMethod(MaEmailTemplateAssets::class, 'assertAsset'))->invoke(null, $filename), 'ma_email_asset_file_invalid', 'unapproved or absent public file rejected');
maAssetsThrows(fn() => MaEmailTemplateAssets::transform($catalogue[0]['source'], MA_ASSETS_BASE), 'ma_email_asset_target_unrecognized', 'English cannot be transformed');
foreach (['data_html', 'data_design'] as $field) {
    $custom = $catalogue[0]['target']; $custom[$field] .= ' ';
    maAssetsThrows(fn() => MaEmailTemplateAssets::transform($custom, MA_ASSETS_BASE), 'ma_email_asset_target_unrecognized', 'even one customized byte protects the whole pair '.$field);
}

foreach ([false, true] as $globalLock) {
    $f = maAssetsFixture($globalLock);
    essentialCheck(MaEmailTemplateDefaults::install($f->db, $f->prefix) === 21, 'normal 111 seed prepares factory targets');
    $f->db->query('UPDATE '.$f->prefix.'ma_email_templates SET published=0,color=?,category=42', ['#123456']);
    $before = maAssetsSnapshot($f);
    $f->db->data_cache['table_names'] = [$f->prefix.'modules'];
    $f->db->queries = [];
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === $affectedTemplateCount, 'only approved existing designs receive images with '.($globalLock ? 'global lock' : 'standalone transaction'));
    $queries = $f->db->queries;
    $after = maAssetsSnapshot($f);
    foreach ($before as $table => $rows) if ($table !== 'ma_email_template_designs') essentialCheck($after[$table] === $rows, 'parents publication module disabled state links and campaigns preserved '.$table);
    foreach ($before['ma_email_template_designs'] as $index => $row) {
        $actual = $after['ma_email_template_designs'][$index];
        foreach (['id', 'email_template_id', 'language'] as $field) essentialCheck($actual[$field] === $row[$field], 'design identity preserved '.$field);
        if ($row['language'] === 'english') essentialCheck($actual === $row, 'English design byte-identical');
    }
    essentialCheck(count(array_filter($queries, static fn($q) => str_contains($q[0], 'ORDER BY id FOR UPDATE'))) === 2, 'both complete library reads are locked');
    essentialCheck(count(array_filter($queries, static fn($q) => str_contains($q[0], 'issuers_lock WHERE id=1 FOR UPDATE'))) === ($globalLock ? 1 : 0), 'global lock used exactly when present');
    essentialCheck(count(array_filter($queries, static fn($q) => preg_match('/^(?:INSERT|DELETE|ALTER|CREATE|DROP)\b/', $q[0]))) === 0, 'upgrade never inserts removes or alters schema');
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 0 && maAssetsSnapshot($f) === $after, 'asset replay is an exact no-op');
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, 'https://another.example.test/') === 0 && maAssetsSnapshot($f) === $after, 'replay never rewrites a previously localized site URL');
    essentialCheck(MaEmailTemplateDefaults::upgrade($f->db, $f->prefix) === 0 && maAssetsSnapshot($f) === $after, '111 replay does not replace later image URLs');
    $newEmail = $f->model->add_email(['email_template'=>$before['ma_email_templates'][0]['id'], 'name'=>'Future draft', 'subject'=>'Assunto escolhido', 'description'=>'', 'published'=>0]);
    $localized = MaEmailTemplateAssets::transform($catalogue[0]['target'], MA_ASSETS_BASE);
    essentialCheck($f->model->get_email_content_by_contact($newEmail, ['lead'=>['default_language'=>'english']]) === $catalogue[0]['source']['data_html'], 'future draft respects explicit English');
    essentialCheck($f->model->get_email_content_by_contact($newEmail, ['lead'=>['default_language'=>'portuguese_br']]) === $localized['data_html'], 'future draft respects explicit Portuguese');
    essentialCheck($f->model->get_email_content_by_contact($newEmail, ['client'=>['default_language'=>'']]) === $localized['data_html'], 'future draft uses configured Portuguese fallback');
    $GLOBALS['ma_assets_language'] = 'english';
    essentialCheck($f->model->get_email_content_by_contact($newEmail, ['client'=>['default_language'=>'']]) === $catalogue[0]['source']['data_html'], 'configured English fallback remains English');
    unset($GLOBALS['ma_assets_language']);
    essentialCheck($f->model->get_email_content_by_contact(400, ['lead'=>['default_language'=>'portuguese_br']]) === json_encode('Existing portuguese_br {client_name}'), 'existing campaign payload remains untouched');
}

// Default A receives assets; custom B and a user-created exact-body clone stay untouched.
$f = maAssetsFixture();
$defaultId = maAssetsSeed($f, $catalogue[0], ['published'=>0]);
$custom = $catalogue[1]['target']; $custom['data_design'] .= ' ';
$customId = maAssetsSeed($f, $catalogue[1], [], $custom);
$cloneId = maAssetsSeed($f, $catalogue[0], ['name'=>'My copied Fitness', 'addedfrom'=>7]);
maAssetsAddDesign($f, $defaultId, $catalogue[0]['target'], 'french');
$before = maAssetsSnapshot($f);
essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 1, 'A/B fixture updates only factory A');
essentialCheck(maAssetsDesign($f, $defaultId, 'portuguese_br')['data_html'] !== $catalogue[0]['target']['data_html'], 'factory A received local image');
essentialCheck(maAssetsDesign($f, $customId, 'portuguese_br')['data_design'] === $custom['data_design'], 'custom B whole pair protected');
essentialCheck(maAssetsDesign($f, $cloneId, 'portuguese_br')['data_html'] === $catalogue[0]['target']['data_html'], 'custom clone protected despite exact copied PTBR bytes');
essentialCheck(maAssetsDesign($f, $defaultId, 'french')['data_html'] === $catalogue[0]['target']['data_html'], 'other language protected even with exact PTBR bodies');
essentialCheck(maAssetsRows($f, 'ma_email_templates') === $before['ma_email_templates'], 'unpublished state and both custom/default parents retained');

foreach (['data_html', 'data_design'] as $field) {
    $f = maAssetsFixture();
    $body = $catalogue[0]['target']; $body[$field] .= ' ';
    maAssetsSeed($f, $catalogue[0], [], $body);
    $before = maAssetsSnapshot($f);
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 0 && maAssetsSnapshot($f) === $before, 'custom Portuguese '.$field.' protects both representations');
}

foreach (['name', 'description', 'language', 'data_html', 'data_design', 'addedfrom', 'dateadded'] as $field) {
    $f = maAssetsFixture();
    maAssetsSeed($f, $catalogue[0], [$field=>$field === 'addedfrom' ? 7 : ($field === 'dateadded' ? '2026-10-21 00:00:00' : 'custom '.$field)]);
    $before = maAssetsSnapshot($f);
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 0 && maAssetsSnapshot($f) === $before, 'factory parent proof rejects modified '.$field);
}
foreach (['data_html', 'data_design', 'deleted_english', 'deleted_portuguese', 'english_under_portuguese', 'empty_portuguese'] as $scenario) {
    $f = maAssetsFixture(); $id = maAssetsSeed($f, $catalogue[0]);
    if (in_array($scenario, ['data_html', 'data_design'], true)) $f->db->query('UPDATE '.$f->prefix.'ma_email_template_designs SET '.$scenario.'=? WHERE email_template_id=? AND language=?', ['custom English', $id, 'english']);
    elseif (str_starts_with($scenario, 'deleted_')) $f->db->query('DELETE FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=? AND language=?', [$id, $scenario === 'deleted_english' ? 'english' : 'portuguese_br']);
    else {
        $body = $scenario === 'empty_portuguese' ? ['data_html'=>'', 'data_design'=>''] : $catalogue[0]['source'];
        $f->db->query('UPDATE '.$f->prefix.'ma_email_template_designs SET data_html=?,data_design=? WHERE email_template_id=? AND language=?', [$body['data_html'], $body['data_design'], $id, 'portuguese_br']);
    }
    $before = maAssetsSnapshot($f);
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 0 && maAssetsSnapshot($f) === $before, 'non-target or missing language remains untouched '.$scenario);
}
foreach ([false, true] as $globalLock) {
    foreach (['english', 'portuguese_br'] as $language) {
        $f = maAssetsFixture($globalLock);
        maAssetsSeed($f, $catalogue[0]);
        $second = maAssetsSeed($f, $catalogue[1]);
        maAssetsAddDesign($f, $second, $catalogue[1][$language === 'english' ? 'source' : 'target'], $language);
        $before = maAssetsSnapshot($f);
        maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE), 'ma_email_design_ambiguous', 'duplicate '.$language.' fails closed after earlier eligible row');
        essentialCheck(maAssetsSnapshot($f) === $before && !$f->db->pdo->inTransaction(), 'ambiguity rolls back all earlier writes');
    }
    $f = maAssetsFixture($globalLock);
    maAssetsSeed($f, $catalogue[0]); maAssetsSeed($f, $catalogue[0]);
    $before = maAssetsSnapshot($f);
    maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE), 'ma_email_asset_origin_ambiguous', 'indistinguishable factory clone fails closed');
    essentialCheck(maAssetsSnapshot($f) === $before, 'indistinguishable clone never updated');
    $f = maAssetsFixture($globalLock); maAssetsSeed($f, $catalogue[0]); maAssetsSeed($f, $catalogue[1]);
    $before = maAssetsSnapshot($f);
    $fault = new MaAssetsFaultDb($f->db, 2);
    maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($fault, $f->prefix, MA_ASSETS_BASE), 'issuer_storage_failed', 'second design write failure propagated');
    essentialCheck($fault->updates === 2 && maAssetsSnapshot($f) === $before && !$f->db->pdo->inTransaction(), 'second-write failure rolls back first complete paired update');
    essentialCheck($fault->db_debug && $fault->save_queries, 'framework error and logging flags restored after failure');
    $ignored = new MaAssetsFaultDb($f->db, 2, true);
    maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($ignored, $f->prefix, MA_ASSETS_BASE), 'issuer_storage_failed', 'unconfirmed silent write is rejected');
    essentialCheck(maAssetsSnapshot($f) === $before, 'silent second-write failure rolls back first update');
    essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 2, 'retry after rollback succeeds');
    $before = maAssetsSnapshot($f);
    $f->db->trans_begin();
    $f->db->query('UPDATE '.$f->prefix.'ma_email_templates SET published=0');
    maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE), 'issuer_outer_transaction_not_allowed', 'caller transaction always rejected');
    essentialCheck($f->db->pdo->inTransaction() && (int) maAssetsRows($f, 'ma_email_templates')[0]['published'] === 0, 'caller transaction still owns its pending change');
    $f->db->trans_rollback();
    essentialCheck(maAssetsSnapshot($f) === $before, 'caller can roll back its own change');
}

$f = maAssetsFixture(); $before = maAssetsSnapshot($f);
essentialCheck(MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE) === 0 && maAssetsSnapshot($f) === $before, 'empty library is never seeded or resurrected');
foreach (['', 'bad-prefix_', 'prefix.;DROP_TABLE_', '1prefix_'] as $prefix) maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $prefix, MA_ASSETS_BASE), 'ma_email_prefix_invalid', 'invalid SQL prefix rejected');
maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $f->prefix.'missing_', MA_ASSETS_BASE), 'ma_email_library_schema_missing', 'unavailable library schema fails closed');
$f = maAssetsFixture(true); maAssetsSeed($f, $catalogue[0]);
$f->db->query('DELETE FROM '.$f->prefix.'issuers_lock'); $before = maAssetsSnapshot($f);
maAssetsThrows(fn() => MaEmailTemplateAssets::upgrade($f->db, $f->prefix, MA_ASSETS_BASE), 'issuer_storage_failed', 'missing global lock row cannot fall back to unlocked writes');
essentialCheck(maAssetsSnapshot($f) === $before, 'missing lock leaves whole library unchanged');

// Real native runner: persisted 111 -> 112, rollback, version-write failure and safe replay.
foreach ([false, true] as $globalLock) {
    $f = maAssetsFixture($globalLock);
    MaEmailTemplateDefaults::install($f->db, $f->prefix);
    $before = maAssetsSnapshot($f);
    $f->db->queries = [];
    essentialCheck((string) (new App_module_migration('ma'))->version(112) === '112' && maAssetsVersion($f) === '1.1.2', 'native MA111 to MA112 stores confirmed version');
    essentialCheck(count(array_filter($f->db->queries, static fn($q) => str_starts_with($q[0], 'UPDATE '.$f->prefix.'ma_email_template_designs'))) === $affectedTemplateCount, 'native runner invokes the complete approved asset upgrade');
    $after = maAssetsSnapshot($f);
    foreach (['ma_email_templates', 'ma_emails', 'ma_email_designs', 'ma_campaigns', 'ma_email_logs', 'ma_marketing_messages'] as $table) essentialCheck($after[$table] === $before[$table], 'native MA112 preserves '.$table);
    essentialCheck((new App_module_migration('ma'))->version(112) === true && maAssetsSnapshot($f) === $after, 'native MA112 replay has no content or tracking delta');

    $f = maAssetsFixture($globalLock);
    MaEmailTemplateDefaults::install($f->db, $f->prefix);
    $before = maAssetsSnapshot($f);
    $fault = new MaAssetsFaultDb($f->db, 2);
    $f->ci->db = $fault;
    maAssetsThrows(fn() => (new App_module_migration('ma'))->version(112), 'issuer_storage_failed', 'native migration propagates second-write failure');
    $f->ci->db = $f->db;
    essentialCheck($fault->updates === 2 && maAssetsVersion($f) === '1.1.1' && maAssetsSnapshot($f) === $before, 'native second-write failure rolls back assets and retains MA111');
    essentialCheck((string) (new App_module_migration('ma'))->version(112) === '112' && maAssetsVersion($f) === '1.1.2', 'native retry succeeds after asset rollback');

    $f = maAssetsFixture($globalLock);
    MaEmailTemplateDefaults::install($f->db, $f->prefix);
    $f->db->failVersion = '1.1.2';
    $runner = new App_module_migration('ma');
    essentialCheck($runner->version(112) === false && $runner->error_string() === 'module_migration_version_write_failed' && maAssetsVersion($f) === '1.1.1', 'native tracking failure is reported and retains MA111');
    $committed = maAssetsSnapshot($f);
    essentialCheck(maAssetsDesign($f, (int) $committed['ma_email_templates'][0]['id'], 'portuguese_br')['data_html'] === MaEmailTemplateAssets::transform($catalogue[0]['target'], MA_ASSETS_BASE)['data_html'], 'completed asset transaction survives later tracking failure');
    $f->db->failVersion = null;
    $f->db->queries = [];
    essentialCheck((string) (new App_module_migration('ma'))->version(112) === '112' && maAssetsVersion($f) === '1.1.2', 'tracking retry confirms MA112');
    essentialCheck(count(array_filter($f->db->queries, static fn($q) => str_starts_with($q[0], 'UPDATE '.$f->prefix.'ma_email_template_designs'))) === 0, 'tracking replay does not repeat already committed asset writes');
    $retried = maAssetsSnapshot($f);
    unset($committed['modules'], $retried['modules']);
    essentialCheck($retried === $committed, 'tracking replay preserves all IDs languages and campaign data without duplication');
}

// Actual install seed retry: the seed commits first; its completion flag waits for assets.
$f = maAssetsFixture();
$campaigns = maAssetsSnapshot($f);
$fault = new MaAssetsFaultDb($f->db, 2);
$f->ci->db = $fault;
maAssetsThrows(fn() => maAssetsInstallerSeed($f), 'issuer_storage_failed', 'actual installer propagates post-seed second asset write failure');
$f->ci->db = $f->db;
essentialCheck(get_option('ma_insert_email_template_default') != 1, 'failed asset pass never marks seed completed');
essentialCheck(count(maAssetsRows($f, 'ma_email_templates')) === 7 && count(maAssetsRows($f, 'ma_email_template_designs')) === 14, 'first transaction leaves one recoverable factory seed only');
foreach (maAssetsRows($f, 'ma_email_templates') as $index => $parent) essentialCheck(maAssetsDesign($f, (int) $parent['id'], 'portuguese_br')['data_html'] === $catalogue[$index]['target']['data_html'], 'failed asset pass leaves original 111 target '.$index);
maAssetsInstallerSeed($f);
essentialCheck(get_option('ma_insert_email_template_default') == 1 && count(maAssetsRows($f, 'ma_email_templates')) === 7 && count(maAssetsRows($f, 'ma_email_template_designs')) === 14, 'actual installer retry completes without duplicate defaults');
foreach (['ma_emails', 'ma_email_designs', 'ma_campaigns', 'ma_email_logs', 'ma_marketing_messages'] as $table) essentialCheck(maAssetsRows($f, $table) === $campaigns[$table], 'seed retry preserves existing '.$table);
$seeded = maAssetsSnapshot($f);
maAssetsInstallerSeed($f);
essentialCheck(maAssetsSnapshot($f) === $seeded, 'completed seed reactivation is byte-identical');
// User-deleted variants/defaults and subsequently customized content remain user-owned.
$ids = array_column($seeded['ma_email_templates'], 'id');
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=? AND language=?', [$ids[0], 'portuguese_br']);
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=?', [$ids[1]]);
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_templates WHERE id=?', [$ids[1]]);
$f->db->query('UPDATE '.$f->prefix.'ma_email_template_designs SET data_html=? WHERE email_template_id=? AND language=?', ['"Customized after installation"', $ids[3], 'portuguese_br']);
$modified = maAssetsSnapshot($f);
maAssetsInstallerSeed($f);
essentialCheck(maAssetsSnapshot($f) === $modified, 'reactivation never recreates deleted defaults or overwrites later custom bodies');
essentialCheck((string) (new App_module_migration('ma'))->version(112) === '112', 'native upgrade can record already-seeded assets after custom changes');
$after = maAssetsSnapshot($f);
unset($modified['modules'], $after['modules']);
essentialCheck($after === $modified, 'native upgrade also preserves reactivation deletions and custom content');

$f = maAssetsFixture();
MaEmailTemplateDefaults::install($f->db, $f->prefix);
$ids = array_column(maAssetsRows($f, 'ma_email_templates'), 'id');
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=? AND language=?', [$ids[0], 'portuguese_br']);
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=?', [$ids[1]]);
$f->db->query('DELETE FROM '.$f->prefix.'ma_email_templates WHERE id=?', [$ids[1]]);
$f->db->query('UPDATE '.$f->prefix.'ma_email_template_designs SET data_html=? WHERE email_template_id=? AND language=?', ['"Customized during interrupted seed"', $ids[3], 'portuguese_br']);
$pending = maAssetsSnapshot($f);
essentialCheck(get_option('ma_insert_email_template_default') == 0, 'interrupted-seed fixture retains pending completion flag');
essentialCheck(MaEmailTemplateDefaults::install($f->db, $f->prefix) === 0 && maAssetsSnapshot($f) === $pending, 'occupied library seed never refills user-deleted variants during retry');
maAssetsInstallerSeed($f);
essentialCheck(get_option('ma_insert_email_template_default') == 1 && count(maAssetsRows($f, 'ma_email_templates')) === 6 && count(maAssetsRows($f, 'ma_email_template_designs')) === 11, 'pending seed retry finishes without resurrecting a deleted parent or variant');
essentialCheck(maAssetsDesign($f, (int) $ids[0], 'portuguese_br') === [], 'PTBR deletion made while seed flag was zero remains deleted');
essentialCheck(maAssetsDesign($f, (int) $ids[3], 'portuguese_br')['data_html'] === '"Customized during interrupted seed"', 'customization made during interrupted seed remains untouched');
essentialCheck(maAssetsRows($f, 'ma_email_templates') === $pending['ma_email_templates'], 'pending seed retry retains all remaining parent identities and state');
foreach (['ma_emails', 'ma_email_designs', 'ma_campaigns', 'ma_email_logs', 'ma_marketing_messages'] as $table) essentialCheck(maAssetsRows($f, $table) === $pending[$table], 'pending seed retry preserves existing '.$table);

$f = maAssetsFixture();
maAssetsSeed($f, $catalogue[0], ['name'=>'Only my custom library', 'addedfrom'=>7]);
$customLibrary = maAssetsSnapshot($f);
maAssetsInstallerSeed($f);
essentialCheck(get_option('ma_insert_email_template_default') == 1 && maAssetsSnapshot($f) === $customLibrary, 'initial seed attempt leaves occupied custom library unchanged');
maAssetsInstallerSeed($f);
essentialCheck(maAssetsSnapshot($f) === $customLibrary, 'custom-only reactivation never adds factory defaults');

essentialCheck(hash_file('sha256', $cataloguePath) === $catalogueHash, 'published 111 catalogue remains byte-identical');

echo 'MA PT-BR local assets: '.$GLOBALS['essentialChecks'].' checks passed ('.$f->db->driver().'); no email sent.'.PHP_EOL;
// Only this test's randomly prefixed synthetic tables are removed.
foreach ($GLOBALS['essentialFixtures'] as $f) if ($f->db->driver() === 'mysql') foreach (['ma_email_template_designs', 'ma_email_templates', 'ma_email_designs', 'ma_emails', 'ma_campaigns', 'ma_email_logs', 'ma_marketing_messages', 'issuers_lock'] as $table) $f->db->pdo->exec('DROP TABLE IF EXISTS '.$f->prefix.$table);
essentialCleanup();
