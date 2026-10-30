<?php
/** Real native Settings controller, owned PDO storage and original PDF partial. No HTTP server is claimed. */
require dirname(__DIR__).'/companies/native_settings_controller.php';
require_once dirname(__DIR__).'/support/StrictViewFixture.php';
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
$pdfService=new NativeCompanyPdfSettings($db,$p);
$pdfGet=function(int $issuer,string $group='pdf')use($db,$p,$controller):array{
 CompanyRequestScope::$issuer=$issuer;$controller->input->verb='GET';$controller->input->query=['group'=>$group];$controller->input->body=[];
 CompanySettingsRuntime::run($db,$p,$issuer,7,fn()=>$controller->index());return end($controller->load->views)[1];
};
$pdfPost=function(int $issuer,array $values,string $revision,string $group='pdf',bool $csrf=true)use($db,$p,$controller):void{
 CompanyRequestScope::$issuer=$issuer;$controller->input->verb='POST';$controller->input->query=['group'=>$group];$controller->input->body=['settings'=>$values,'native_settings_revision'=>$revision];if($csrf)$controller->input->body['_native_company_csrf']='fixture-csrf';
 CompanySettingsRuntime::run($db,$p,$issuer,7,fn()=>$controller->index());
};
$pdfError=function(callable $operation,int $status,string $label):void{
 try{$operation();throw new RuntimeException('Expected HTTP rejection: '.$label);}catch(NativeSettingsHttpError $e){essentialCheck($e->getCode()===$status,$label);}
};
$pdfView=new class{
 public object $load;
 public function __construct(){$this->load=new class{function view($path,$data){extract($data,EXTR_SKIP);include APPPATH.'views/'.$path.'.php';}};}
 public function render(array $data):string{return strictFixtureRender(function()use($data){extract($data,EXTR_SKIP);include APPPATH.'views/admin/settings/includes/pdf.php';});}
};
