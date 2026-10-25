<?php
/** Executes real Settings model/controller against synthetic PDO storage and isolated framework I/O. */
require __DIR__ . '/company_settings.php';
require_once dirname(__DIR__).'/support/StrictViewFixture.php';

class CompanyRequestScope { public static function context() { return null; } public static function sealedFor($owner): bool { return $GLOBALS['settingsSealEnabled']; } public static function token(): string { return 'synthetic-context-token'; } public static function adminUrl($owner,string $path = ''): string { return admin_url($path); } }
$GLOBALS['settingsSealEnabled'] = true;

#[AllowDynamicProperties]
class CI_Model
{
    public function __construct() {}
    public function __get($name) { return $GLOBALS['CI']->{$name}; }
}
class App_Model extends CI_Model
{
    public function __construct() { ++$GLOBALS['legacyModelConstructors']; parent::__construct(); }
}
#[AllowDynamicProperties]
class AdminController
{
    public function __construct() {}
    public function __get($name) { return $GLOBALS['CI']->{$name}; }
}
function staff_can($capability,$feature): bool { return false; }
function access_denied($feature): void { throw new DomainException('http_access_denied'); }
function show_error($message,$status=500): void { throw new DomainException('http_' . $status); }
function get_option($name) { throw new RuntimeException('unexpected_global_get_option'); }
function update_option($name,$value) { throw new RuntimeException('unexpected_global_update_option'); }
function admin_url($path): string { return 'https://synthetic.invalid/admin/' . $path; }
function base_url($path): string { return 'https://synthetic.invalid/' . $path; }
function _l($name) { return $name; }
function nl2br_save_html($value) { return nl2br($value); }

require APPPATH . 'models/Settings_model.php';
require APPPATH . 'controllers/admin/Settings.php';
final class SettingsTestInput
{
    public string $group = 'email'; public array $payload = [];
    public function get($name) { return $name === 'group' ? $this->group : null; }
    public function post($name=null,$filter=true) { return $name === null ? $this->payload : ($this->payload[$name] ?? null); }
}
final class SettingsTestLoader
{
    public array $models = []; public array $views = [];
    public function library($name): void { if ($name !== 'encryption') { throw new RuntimeException('unexpected_library'); } }
    public function model($name): void
    {
        $this->models[] = $name;
        if ($name !== 'settings_model') { throw new RuntimeException('unexpected_legacy_model'); }
        $GLOBALS['CI']->settings_model = new Settings_model();
    }
    public function helper($names): void { if($names!==['fields','form'])throw new RuntimeException('unexpected_helper'); }
    public function view($name,$data): void { $this->views[] = [$name,$data]; }
}
$GLOBALS['legacyModelConstructors'] = 0;
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$GLOBALS['CI']->load = new SettingsTestLoader();
$GLOBALS['CI']->input = new SettingsTestInput();
$GLOBALS['CI']->output = new class { public int $status=200; public function set_status_header($status) { $this->status=$status; } };
$GLOBALS['CI']->encryption = new class {
    public bool $fail = false;
    public function encrypt($value) { return $this->fail ? false : 'synthetic-cipher:' . base64_encode($value); }
    public function decrypt($value) { return str_starts_with($value,'synthetic-cipher:') ? base64_decode(substr($value,17)) : false; }
};
$_FILES = [];
CompanySettingsRuntime::run($db,$p,41,12,function () use ($db,$p) {
    $controller = new Settings();
    essentialCheck($GLOBALS['legacyModelConstructors'] === 0, 'isolated model does not execute legacy App_Model initialization hooks');
    essentialCheck($GLOBALS['CI']->load->models === ['settings_model'], 'isolated controller never loads gateway catalog');
    $GLOBALS['CI']->input->payload = ['settings'=>['smtp_host'=>'a.invalid','smtp_port'=>'587','smtp_email'=>'a@example.invalid','smtp_username'=>'user-a','smtp_encryption'=>'tls','smtp_password'=>'  password-a  ']];
    $controller->index();
    $view = end($GLOBALS['CI']->load->views);
    essentialCheck($view[0] === 'admin/settings/company_scoped', 'same native Settings route renders isolated view');
    essentialCheck($view[1]['saved'] === true && $view[1]['can_edit'] === true, 'company ACL authorizes despite absent global settings grant');
    essentialCheck(!array_key_exists('smtp_password',$view[1]['values']), 'controller never passes password ciphertext or plaintext to view');
    essentialCheck(CompanySettingsRuntime::get('smtp_host') === 'a.invalid', 'controller writes A SMTP host');
    $cipher = CompanySettingsRuntime::get('smtp_password');
    essentialCheck($cipher !== '  password-a  ' && $GLOBALS['CI']->encryption->decrypt($cipher) === '  password-a  ', 'model encrypts secret and preserves meaningful whitespace');
    $GLOBALS['CI']->input->payload = ['settings'=>['smtp_password'=>'','smtp_host'=>'a2.invalid']]; $controller->index();
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === $cipher, 'blank password in isolated UI preserves existing secret');
    $GLOBALS['CI']->input->payload = ['settings'=>['smtp_password'=>'replacement'], 'clear_smtp_password'=>'1']; $controller->index();
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'explicit clear removes only selected company secret');
    $GLOBALS['CI']->input->payload = ['settings'=>['payment_account'=>'attempt-overpost']]; $controller->index();
    essentialCheck($GLOBALS['CI']->output->status === 422 && CompanySettingsRuntime::get('payment_account') === 'bank-a-test', 'unadapted keys are rejected before update');
    $GLOBALS['CI']->input->payload = ['settings'=>['smtp_host'=>'must-not-write'],'tags'=>[1=>'global']]; $controller->index();
    essentialCheck(CompanySettingsRuntime::get('smtp_host') === 'a2.invalid', 'global tag overpost blocked before side effects');
    $GLOBALS['CI']->input->payload = ['settings'=>['smtp_password'=>'will-fail']]; $GLOBALS['CI']->encryption->fail=true; $controller->index(); $GLOBALS['CI']->encryption->fail=false;
    essentialCheck(CompanySettingsRuntime::get('smtp_password') === '', 'failed encryption never writes plaintext');
    $beforeBatch=CompanySettingsRuntime::all();
    $GLOBALS['CI']->encryption->fail=true;
    $GLOBALS['CI']->input->payload=['settings'=>['smtp_host'=>'must-rollback.invalid','smtp_password'=>'synthetic-new-password']];$controller->index();
    $GLOBALS['CI']->encryption->fail=false;
    essentialCheck(CompanySettingsRuntime::all()===$beforeBatch,'encryption failure cannot partially change SMTP host or account');
    $db->failOn="INSERT INTO {$p}issuers_audit";
    $GLOBALS['CI']->input->payload=['settings'=>['smtp_host'=>'must-rollback.invalid','smtp_password'=>'synthetic-new-password']];$controller->index();
    $db->failOn=null;
    essentialCheck(CompanySettingsRuntime::all()===$beforeBatch,'audit failure rolls back the complete SMTP configuration batch');
    settingThrows(fn()=>$GLOBALS['CI']->settings_model->update(['settings'=>['companyname'=>'Partial identity','smtp_host'=>'partial.invalid']]),'company_settings_batch_identity_separate','mixed identity/options require separate atomic form');
    essentialCheck(CompanySettingsRuntime::all()===$beforeBatch,'rejected mixed batch has no partial identity mutation');
    $GLOBALS['CI']->input->group='company';
    $GLOBALS['CI']->input->payload=['settings'=>['invoice_company_name'=>'Controller A','company_vat'=>'12345678000195','invoice_company_city'=>'Controller city']]; $controller->index();
    essentialCheck(CompanySettingsRuntime::get('companyname') === 'Controller A' && CompanySettingsRuntime::get('invoice_company_city') === 'Controller city', 'controller updates canonical company identity/profile');
    $GLOBALS['CI']->input->group='payment_gateways'; $GLOBALS['CI']->input->payload=[];
    settingThrows(fn() => $controller->index(), 'http_403', 'unadapted settings section unavailable rather than global');
    settingThrows(fn() => $controller->remove_company_logo(), 'http_access_denied', 'secondary cannot remove shared branding');
    settingThrows(fn() => $controller->clear_sessions(), 'http_access_denied', 'secondary cannot terminate global sessions');
    settingThrows(fn() => $GLOBALS['CI']->settings_model->update(['settings'=>['smtp_host'=>'wrong'],'custom_fields'=>[]]), 'company_settings_global_resource_unavailable', 'model direct call also blocks shared resources');
    settingThrows(fn() => $GLOBALS['CI']->settings_model->update(['settings'=>['companyname'=>'Controller A','invoice_company_name'=>'Conflicting']]), 'company_settings_identity_alias_conflict', 'conflicting aliases fail before identity write');
});
CompanySettingsRuntime::run($db,$p,82,12,function () {
    essentialCheck(CompanySettingsRuntime::get('smtp_host') === '' && CompanySettingsRuntime::get('companyname') === 'Synthetic B', 'A controller does not modify B');
    $controller = new Settings(); $GLOBALS['CI']->input->group='email'; $GLOBALS['CI']->input->payload=['settings'=>['smtp_host'=>'b.invalid','smtp_password'=>'password-b']]; $controller->index();
    essentialCheck(CompanySettingsRuntime::get('smtp_host') === 'b.invalid', 'B positive controller write');
});
essentialCheck($store->get(41,'smtp_host') === 'a2.invalid' && $store->get(82,'smtp_host') === 'b.invalid', 'controller positive A/B values remain independent');
CompanySettingsRuntime::run($db,$p,41,14,function () {
    $controller = new Settings(); $GLOBALS['CI']->input->payload=[];
    settingThrows(fn() => $controller->index(), 'http_403', 'member without settings.view cannot view settings');
});
essentialCheck(hooks()->trace === [], 'isolated settings invokes no global module actions');
CompanySettingsRuntime::run($db,$p,41,12,function () {
    $GLOBALS['settingsSealEnabled'] = false;
    settingThrows(fn() => new Settings(), 'http_403', 'runtime scope alone cannot bypass controller lifecycle seal');
    $GLOBALS['CI']->payment_modes_model = new class { public function get_payment_gateways($all) { return []; } };
    new Settings_model();
    essentialCheck($GLOBALS['legacyModelConstructors'] === 1, 'unsealed model retains normal legacy constructor');
    $GLOBALS['settingsSealEnabled'] = true;
});
$viewHarness = new class {
    public $config; public $security; public $db; public $router;
    public function __construct() {
        $this->db = $GLOBALS['CI']->db;
        $this->router = new class { public function fetch_class(): string { return 'settings'; } };
        $this->config = new class { public function item($key) { return $key === 'csrf_protection'; } };
        $this->security = new class { public function get_csrf_token_name() { return 'synthetic_csrf'; } public function get_csrf_hash() { return 'synthetic-csrf-hash'; } };
    }
    public function render(array $data): string { return strictFixtureRender(function () use ($data): void { extract($data); include APPPATH . 'views/admin/settings/company_scoped.php'; }); }
};
$emailViews = array_values(array_filter($GLOBALS['CI']->load->views, static fn($view) => $view[1]['group'] === 'email'));
$renderData = end($emailViews)[1];
$renderData['company_identity']['legal_name'] = '<script>company-test</script>';
$renderData['values']['smtp_host'] = '" onclick="alert(1)';
$html = $viewHarness->render($renderData);
essentialCheck(str_contains($html,'name="_company_context" value="synthetic-context-token"'), 'real form retains browser company scope token');
essentialCheck(str_contains($html,'name="synthetic_csrf" value="synthetic-csrf-hash"'), 'real form retains native CSRF token');
essentialCheck(!str_contains($html,'<script>company-test</script>') && str_contains($html,'&lt;script&gt;company-test'), 'real view escapes company identity');
essentialCheck(!str_contains($html,'value="" onclick="alert(1)'), 'real view escapes option attributes');
essentialCheck(!str_contains($html,'password-b') && !str_contains($html,'synthetic-cipher:'), 'real view never renders stored password');
echo 'Settings model/controller integration: ' . $GLOBALS['essentialChecks'] . ' total checks passed (' . $db->driver() . ').' . PHP_EOL;

foreach ([41=>['d/m/Y|%d/%m/%Y','24'],82=>['Y-m-d|%Y-%m-%d','12']] as $issuer=>$formats) {
    CompanySettingsRuntime::run($db,$p,$issuer,12,function () use ($formats,$viewHarness) {
        $controller=new Settings(); $GLOBALS['CI']->input->group='localization';
        $GLOBALS['CI']->input->payload=['settings'=>['dateformat'=>$formats[0],'time_format'=>$formats[1]]];
        $controller->index(); $view=end($GLOBALS['CI']->load->views)[1];
        essentialCheck($view['saved'] && $view['values']['dateformat']===$formats[0] && $view['values']['time_format']===$formats[1], 'own company formatting saved through native controller');
        $html=$viewHarness->render($view);
        essentialCheck(str_contains($html,'name="settings[dateformat]"') && str_contains($html,'name="settings[time_format]"'), 'format controls rendered in native scoped settings');
        essentialCheck(str_contains($html,'synthetic_csrf') && str_contains($html,'_company_context'), 'format controls retain CSRF and context');
        $before=CompanySettingsRuntime::all();
        foreach ([['dateformat'=>'invalid','time_format'=>'24'],['dateformat'=>$formats[0]],['dateformat'=>$formats[0],'time_format'=>'13'],['dateformat'=>$formats[0],'time_format'=>'24','smtp_host'=>'foreign.invalid']] as $bad) {
            $GLOBALS['CI']->output->status=200; $GLOBALS['CI']->input->payload=['settings'=>$bad]; $controller->index();
            essentialCheck($GLOBALS['CI']->output->status===422 && CompanySettingsRuntime::all()===$before, 'invalid formatting rejected without partial mutation');
        }
    });
}
essentialCheck($store->get(41,'dateformat')==='d/m/Y|%d/%m/%Y' && $store->get(82,'dateformat')==='Y-m-d|%Y-%m-%d', 'A/B formatting independent after switching');
echo 'Settings formatting controller: '.$GLOBALS['essentialChecks'].' total checks passed.'.PHP_EOL;

foreach([41=>'A',82=>'B'] as $owner=>$letter){
 CompanySettingsRuntime::run($db,$p,$owner,12,function()use($viewHarness,$letter){
  $controller=new Settings();$GLOBALS['CI']->input->group='documents';
  $GLOBALS['CI']->input->payload=['settings'=>['invoice_prefix'=>'SYN-'.$letter.'-','predefined_terms_invoice'=>'Synthetic terms '.$letter.' <script>literal</script>']];$controller->index();$view=end($GLOBALS['CI']->load->views)[1];
  essentialCheck($view['saved']&&$view['values']['invoice_prefix']==='SYN-'.$letter.'-','document preferences saved through actual isolated settings controller');
  $html=$viewHarness->render($view);essentialCheck(str_contains($html,'settings[predefined_terms_invoice]')&&!str_contains($html,'<script>literal</script>'),'document terms render as escaped text, never executable HTML');
  $before=CompanySettingsRuntime::all();$GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>['invoice_prefix'=>str_repeat('x',51),'predefined_terms_invoice'=>'must not write']];$controller->index();
  essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'invalid document batch leaves all prior preferences untouched');
 });
}
essentialCheck($store->get(41,'invoice_prefix')==='SYN-A-'&&$store->get(82,'invoice_prefix')==='SYN-B-','company switching never shares invoice prefix or terms');
echo 'Document preferences controller: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;

// Exercise the actual native input helper and common field partial; global
// get_option above deliberately throws, so scoped rendering cannot fall back.
require_once APPPATH.'helpers/fields_helper.php';
if(!function_exists('set_value')){function set_value($name,$value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
foreach([41,82] as $owner){
 CompanySettingsRuntime::run($db,$p,$owner,12,function()use($viewHarness,$owner){
  $controller=new Settings();$GLOBALS['CI']->input->group='company';$GLOBALS['CI']->input->payload=[];
  $controller->index();$view=end($GLOBALS['CI']->load->views)[1];
  $before=CompanySettingsRuntime::all();$html=$viewHarness->render($view);
  foreach(CompanySettingsCatalog::fields('company') as $field){essentialCheck(substr_count($html,'name="settings['.$field.']"')===1,'native shared field appears exactly once '.$field);}
  essentialCheck(str_contains($html,'app-field-wrapper="settings[invoice_company_name]"'),'actual native input markup reused');
  essentialCheck(str_contains($html,'class="control-label"'),'native labels reused');
  essentialCheck(!str_contains($html,'name="settings[company_info_format]"')&&!str_contains($html,'name="custom_fields'),'unsupported global fields cannot enter scoped submission');
  essentialCheck($before===CompanySettingsRuntime::all(),'rendering shared native company fields never changes settings');
  $view['values']['invoice_company_name']='"<script>scoped-fixture</script>';
  $html=$viewHarness->render($view);essentialCheck(!str_contains($html,'<script>scoped-fixture</script>')&&str_contains($html,'&lt;script&gt;scoped-fixture'),'native helper escapes scoped identity value');
 });
}
echo 'Native shared company fields A/B: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
