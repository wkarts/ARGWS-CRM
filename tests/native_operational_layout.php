<?php
/** Real imports/head/tail views; framework presentation I/O is synthetic, no HTTP or authorization proof. */
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
function form_open($action):string{return '<form method="post" action="'.e($action).'"><input type="hidden" name="synthetic_framework_csrf" value="framework">';}
function form_open_multipart($action):string{return str_replace('<form ', '<form enctype="multipart/form-data" ',form_open($action));}
function form_close():string{return '</form>';}
final class OperationalLayoutView
{
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
echo "Native operational layout: $checks presentation checks passed.\n";
