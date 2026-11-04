<?php
/** Real imports/purchases/head/tail views; framework I/O is synthetic, no HTTP or authorization proof. */
declare(strict_types=1);
define('BASEPATH', dirname(__DIR__).'/system/');
define('APPPATH', dirname(__DIR__).'/application/');
require_once APPPATH.'services/CompanyImports.php';
final class NativeCompanyAdminPresentation { public static function current($ci):array{return [];} }
final class CompanyRequestScope { public static function adminUrl($ci,string $path):string{return '/admin/'.$path;} }
function get_instance(){static $ci;return $ci??=new stdClass();}
function e($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($path):string{return '/'.$path;}
function init_head():void{echo '<!doctype html><html><head></head><body><header>CRM</header>';}
function init_tail():void{}
function form_open($action,array $attributes=[]):string{return '<form method="post" action="'.e($action).'"'.(isset($attributes['id'])?' id="'.e($attributes['id']).'"':'').'><input type="hidden" name="synthetic_framework_csrf" value="framework">';}
function form_open_multipart($action):string{return str_replace('<form ', '<form enctype="multipart/form-data" ',form_open($action));}
function form_close():string{return '</form>';}
final class OperationalLayoutView
{
    public function purchases(string $supplierName):string
    {
        $tab='suppliers';$error='';$settings=['prefix'=>'TEST'];$workspace=null;
        $company_purchases_csrf='owned-purchase-token';
        $capabilities=['report'=>true,'settings'=>true,'create'=>true,'edit'=>true];
        $editSupplier=['id'=>42,'revision'=>1,'name'=>$supplierName,'tax_id'=>'','email'=>'vendor@example.invalid','phone'=>'','address'=>'','active'=>1];
        $page=['items'=>[$editSupplier],'next'=>null];
        ob_start();
        try{require APPPATH.'views/admin/expenses/native_purchases.php';return ob_get_clean();}
        catch(Throwable $error){ob_end_clean();throw $error;}
    }
    public function render(bool $can_import,?array $batch):string
    {
        $error='';$expense_page=['items'=>[],'after'=>0,'next'=>null];$batches=[];
        $company_imports_csrf='owned-import-token';
        ob_start();
        try{require APPPATH.'views/admin/settings/native_imports.php';return ob_get_clean();}
        catch(Throwable $error){ob_end_clean();throw $error;}
    }
}
$batch=['batch_id'=>str_repeat('b',32),'state'=>'preview','expired'=>false,'expires_at'=>'2026-11-05 00:00:00',
    'totals'=>['BRL'=>'30.61'],'items'=>[],'result'=>null,'revision'=>str_repeat('r',64)];
$checks=0;
function layoutCheck(bool $ok,string $label):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($label);}
foreach([[true,null,1],[true,$batch,2],[false,null,0]] as [$canImport,$preview,$forms]){
    $html=(new OperationalLayoutView())->render($canImport,$preview);
    $dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);$xpath=new DOMXPath($dom);
    layoutCheck($xpath->query('//section')->length===1,'layout must preserve the one original functional section');
    layoutCheck($xpath->query('//div[@data-company-workspace]/section[contains(@class,"company-settings-card")]')->length===1,'workspace container owns the unchanged imports card');
    layoutCheck($xpath->query('//*[@id="wrapper"]')->length===1&&$xpath->query('//header')->length===1,'official shell remains single');
    layoutCheck($xpath->query('//form')->length===$forms,'preview/commit/readonly preserve form count');
    layoutCheck($xpath->query('//form//form')->length===0,'forms are never nested');
    foreach($xpath->query('//form') as $form){
        layoutCheck($form->getAttribute('action')==='/admin/settings/imports'&&$form->getAttribute('method')==='post','original form action and method preserved');
        layoutCheck($xpath->query('.//input[@name="company_imports_csrf" and @value="owned-import-token"]',$form)->length===1,'owned import token remains inside each form');
        layoutCheck($xpath->query('.//input[@name="synthetic_framework_csrf"]',$form)->length===1,'framework token remains inside each form');
    }
    if($canImport)layoutCheck($xpath->query('//form[@enctype="multipart/form-data"]//input[@id="import-file"]')->length===1,'multipart file belongs to preview form');
    if($preview){
        layoutCheck(str_contains($xpath->query('//section')->item(0)->textContent,'BRL 30.61'),'original preview totals remain visible');
        layoutCheck($xpath->query('//form[.//button[@id="import-commit"]]//input[@name="batch_id" and @value="'.str_repeat('b',32).'"]')->length===1,'commit keeps its exact batch identity');
    }
}
foreach(['=Vendor a <script>literal</script>', '\"><script>globalThis.purchaseInjected=true</script><input value="'] as $supplierName){
    $html=(new OperationalLayoutView())->purchases($supplierName);
    $dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($old);$xpath=new DOMXPath($dom);
    $cards=$xpath->query('//section[contains(@class,"company-settings-card") and contains(@class,"purchase-card")]');
    layoutCheck($cards->length===1,'purchase data surface exists exactly once');
    $card=$cards->item(0);
    layoutCheck($xpath->query('.//script',$card)->length===0,'untrusted supplier markup never creates a script inside the data surface');
    layoutCheck($xpath->query('.//table//a',$card)->item(0)->textContent===$supplierName,'supplier list preserves literal markup');
    layoutCheck($xpath->query('.//input[@name="purchase[name]"]',$card)->item(0)->getAttribute('value')===$supplierName,'supplier edit preserves literal markup');
    layoutCheck($xpath->query('//div[@data-company-workspace]/script')->length===1,'trusted line editor remains outside the data section but inside official wrapper');
    layoutCheck($xpath->query('//*[@id="purchase-supplier-42"]//input[@name="company_purchases_csrf"]')->length===1,'supplier form keeps its dedicated CSRF token');
    layoutCheck($xpath->query('//*[@id="purchase-supplier-42"]//input[@name="synthetic_framework_csrf"]')->length===1,'supplier form keeps its framework CSRF token');
    // The old broad selector sees this trusted script and rejects this valid view.
    layoutCheck($xpath->query('//*[contains(@class,"company-settings-card")]//script')->length===1,'reproduces the old broad-selector false positive');
}
echo "Native operational layout: $checks presentation checks passed.\n";
