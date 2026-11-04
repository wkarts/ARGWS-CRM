<?php
/** Official Settings parent and owned SMTP partial; synthetic framework I/O. */
ob_start();
require __DIR__.'/native_company/settings_email_view.php';
ob_end_clean();
require_once __DIR__.'/support/StrictViewFixture.php';
require_once APPPATH.'services/NativeSettingsSections.php';
class CompanyRequestScope
{
    public static function nativeFor($ci): bool { return true; }
    public static function context() { return new class { public function issuerId(){return 41;} public function staffId(){return 12;} }; }
    public static function token(): string { return 'synthetic-context'; }
    public static function nativeCsrfToken($ci): string { return 'synthetic-native-csrf'; }
}
function init_head(): void {
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    foreach(['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/builds/vendor-admin.css','assets/plugins/font-awesome/css/fontawesome.min.css','assets/plugins/font-awesome/css/solid.min.css','assets/plugins/font-awesome/css/regular.min.css','assets/builds/tailwind.css','assets/css/style.min.css'] as $asset) echo '<link rel="stylesheet" href="https://synthetic.invalid/'.$asset.'">';
    echo '<script src="https://synthetic.invalid/assets/plugins/jquery/jquery.min.js"></script></head><body class="admin hide-sidebar">';
}
function init_tail(): void {}
function site_url($path): string { return 'https://synthetic.invalid/'.$path; }
function form_hidden($name,$value): string { return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">'; }
function form_open_multipart($url,$attributes): string { return '<form method="post" enctype="multipart/form-data" action="'.e($url).'" id="'.e($attributes['id']).'" class="'.e($attributes['class']).'">'.form_hidden('synthetic_csrf','synthetic'); }
function form_close(): string { return '</form>'; }
$sections=NativeSettingsSections::collect();
$supported=array_fill_keys(NativeCompanySettingsForms::availableGroups(),true);
foreach($sections as $key=>$section){
    $section['children']=array_values(array_filter($section['children'],static fn($child)=>isset($supported[$child['id']])));
    if($section['children']===[])unset($sections[$key]);else $sections[$key]=$section;
}
$renderData=['sections'=>$sections,'group'=>['id'=>'email','name'=>'Servidor SMTP','view'=>'admin/settings/includes/email'],
    'native_settings'=>true,'native_settings_email'=>$native_settings_email,'native_settings_revision'=>$native_settings_email['revision'],'native_settings_can_edit'=>true];
$viewHarness=new class {
    public object $session; public object $uri; public object $input; public object $load;
    public function __construct(){
        $this->session=new class { public function flashdata($key){return null;} };
        $this->uri=new class { public function uri_string(){return 'admin/settings';} };
        $this->input=new class { public function get($key){return null;} };
    }
    public function render(array $data): string {
        $this->load=new class($data) {
            public function __construct(private array $data){}
            public function view($name): void {
                if($name!=='admin/settings/includes/email')throw new LogicException('Unexpected Settings partial');
                extract($this->data,EXTR_SKIP);require APPPATH.'views/admin/settings/includes/email.php';
            }
        };
        return strictFixtureRender(function()use($data):void{extract($data,EXTR_SKIP);require APPPATH.'views/admin/settings/all.php';});
    }
};
$html=$viewHarness->render($renderData);
$level=ob_get_level();$incomplete=clone $viewHarness;unset($incomplete->session);
try{$incomplete->render($renderData);throw new RuntimeException('Missing session was not rejected');}
catch(Error $error){essentialCheck(str_contains($error->getMessage(),'session'),'missing renderer dependency fails before HTML');}
essentialCheck(ob_get_level()===$level,'failed renderer restores output buffer boundary');
try{strictFixtureRender(static function():void{echo 'must not leak';throw new Error('synthetic-render-fatal');});}
catch(Error $error){essentialCheck($error->getMessage()==='synthetic-render-fatal','fatal renderer error remains fatal');}
essentialCheck(ob_get_level()===$level,'fatal renderer restores output buffer boundary');
$count=0;
foreach($sections as $section)foreach($section['children'] as $child){$count++;essentialCheck(substr_count($html,'settings-group-'.$child['id'].'"')===1,'every admitted group appears once: '.$child['id']);}
essentialCheck(substr_count($html,'aria-current="page"')===1,'exactly one selected group');
$doc=new DOMDocument();@$doc->loadHTML($html);$xp=new DOMXPath($doc);
essentialCheck($xp->query('//form')->length===1&&$xp->query('//form[@id="settings-form"]')->length===1,'one official form without nested forms');
foreach(['_company_context','_native_company_csrf','synthetic_csrf','native_settings_revision'] as $field)essentialCheck($xp->query('//form[@id="settings-form"]//input[@name="'.$field.'"]')->length===1,'token stays inside form: '.$field);
essentialCheck($xp->query('//form[@id="settings-form"]//button[@type="submit"]')->length===1,'save stays inside form');
essentialCheck(!str_contains($html,'synthetic-smtp-secret')&&!str_contains($html,'synthetic-google-secret')&&!str_contains($html,'synthetic-microsoft-secret'),'saved provider secrets never enter parent HTML');
$readOnly=$renderData;$readOnly['native_settings_can_edit']=false;$readOnlyHtml=$viewHarness->render($readOnly);
essentialCheck(str_contains($readOnlyHtml,'<fieldset disabled')&&!str_contains($readOnlyHtml,'type="submit"'),'readonly controls disabled and no submit');
$future=$renderData;$future['sections']['general']['children'][]=['id'=>'future_safe','name'=>'Nova seção <script>unsafe</script>','icon'=>'fa fa-gears'];
$futureHtml=$viewHarness->render($future);
essentialCheck(str_contains($futureHtml,'settings-group-future_safe')&&!str_contains($futureHtml,'<script>unsafe</script>'),'future admitted group retained and escaped');
if(in_array('--html-json',$argv??[],true))echo json_encode(['editable'=>$html,'readOnly'=>$readOnlyHtml,'groupsCount'=>$count],JSON_THROW_ON_ERROR);
else echo 'PASS: official Settings parent, owned SMTP partial, navigation, form/CSRF, readonly and strict rendering ('.$GLOBALS['essentialChecks'].' cumulative checks).'.PHP_EOL;
