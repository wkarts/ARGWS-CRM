<?php

declare(strict_types=1);

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('EXT', '.php');
require BASEPATH . 'core/Lang.php';
require APPPATH . 'core/App_Lang.php';
require APPPATH . 'libraries/App.php';
require APPPATH . 'helpers/ui_language_helper.php';
require APPPATH . 'helpers/admin_helper.php';
require APPPATH . 'helpers/clients_helper.php';
require APPPATH . 'helpers/pdf_helper.php';

class CI { public static $APP; }
class Modules
{
    public static function find($file, $module, $path) { return [false, $file]; }
}
function &get_instance() { return CI::$APP; }
function log_message($level, $message) {}
function get_locale_key($language) { return $language === 'portuguese_br' ? 'pt-BR' : 'en'; }
function load_custom_lang_file($language)
{
    if ($language !== 'portuguese_br') { throw new RuntimeException('Unexpected custom language.'); }
    get_instance()->lang->language['test_local_customization'] = 'Personalização local';
}
function set_cookie($name, $value, $duration) { $GLOBALS['testCookie'] = [$name, $value, $duration]; }
function get_cookie($name) { return $GLOBALS['testCookie'][1] ?? null; }
class UiHooks
{
    public array $events = [];
    public function apply_filters($hook, $value, ...$rest) { return $value; }
    public function do_action($hook, $value) { $this->events[] = [$hook, $value]; }
}
function hooks() { static $hooks; return $hooks ??= new UiHooks(); }

CI::$APP = (object) [
    'lang' => new App_Lang(),
    'router' => new class { public function fetch_module() { return ''; } },
    'config' => new class { public function item($key) { return 'english'; } },
    'load' => new class { public function get_package_paths($all) { return [APPPATH]; } },
    'app' => (new ReflectionClass(App::class))->newInstanceWithoutConstructor(),
];
$options = new ReflectionProperty(App::class, 'options');
$options->setValue(CI::$APP->app, [
    'active_language' => 'english', 'enabled_languages' => '["english","spanish"]',
    'disable_language' => '0', 'companyname' => 'Cliente ARGWS personalizado',
]);
$checks = 0;
function same($expected, $actual, $context)
{
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException($context . ': ' . var_export($actual, true)); }
}

same(['portuguese_br'], CI::$APP->app->get_available_languages(), 'Selectable language');
same(['portuguese_br'], CI::$APP->app->get_all_languages(), 'Language catalog');
same('portuguese_br', CI::$APP->app->get_option('active_language'), 'Legacy active language normalized without DB write');
same('1', CI::$APP->app->get_option('disable_language'), 'Selector disabled');
same('["portuguese_br"]', CI::$APP->app->get_options()['enabled_languages'], 'Bulk option read normalized');
same('Cliente ARGWS personalizado', CI::$APP->app->get_option('companyname'), 'Customer company name preserved');
same('english', $options->getValue(CI::$APP->app)['active_language'], 'Persisted legacy option not mutated');

// Simulate framework autoload before any area/PDF loader. PT-BR is cached first.
CI::$APP->lang->load('portuguese_br_lang', 'portuguese_br');
CI::$APP->lang->language['module_specific_key'] = 'Texto do módulo';
crm_load_ui_language();
same('Nome', CI::$APP->lang->line('name'), 'Cold start fallback never overwrites cached PT-BR');
same('Texto do módulo', CI::$APP->lang->line('module_specific_key'), 'Module keys survive non-reset load');

foreach (['load_admin_language', 'load_client_language'] as $loader) {
    same('portuguese_br', $loader(123), 'Runtime language');
    same('Nome', CI::$APP->lang->line('name'), 'PT-BR overrides English');
    same('Personalização local', CI::$APP->lang->line('test_local_customization'), 'Custom language preserved');
    same('pt-BR', $GLOBALS['locale'], 'Locale normalized');
    same('portuguese_br', CI::$APP->lang->last_loaded, 'Last language normalized');
    same(true, (bool) CI::$APP->lang->line('checkpoint'), 'Missing PT-BR key falls back to English');
}
load_pdf_language('english');
same('Nome', CI::$APP->lang->line('name'), 'PDF keeps PT-BR despite legacy customer language');
foreach (['english', '../english', '', ['english'], null] as $legacy) {
    set_contact_language($legacy, 120);
    same(['contact_language', 'portuguese_br', 120], $GLOBALS['testCookie'], 'Cookie normalized');
    same('portuguese_br', get_contact_language(), 'Normalized cookie read');
}
same(3, count(hooks()->events), 'Existing language hooks preserved');
echo "CRM language: {$checks} checks passed (real CI language loader, no database).\n";
