<?php
require __DIR__.'/primary_catalog.php';
$db->query("CREATE TABLE {$p}currencies(id INTEGER PRIMARY KEY,name TEXT,symbol TEXT,isdefault INT,decimal_separator TEXT,thousand_separator TEXT,placement TEXT)");
$db->query("INSERT INTO {$p}currencies VALUES(1,'BRL','R$',1,',','.','before'),(2,'USD','$',0,'.',',','before')");
if(!$db->field_exists('default_currency',$p.'clients'))$db->query('ALTER TABLE '.$p.'clients ADD default_currency INT');
$db->query("INSERT INTO {$p}clients(userid,company,default_currency) VALUES(101,'Synthetic owned',2)");
$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(101,41,NULL,7,'2026-10-25 00:00:00')");
$ref=new NativeCompanyCurrencyReference($db,$p,41,7);$row=$ref->currency(1);essentialCheck(array_key_exists('isdefault',$row)&&(int)$row['isdefault']===1,'original currency DTO includes real default marker');
$ids=$ref->clientCurrencyIds();essentialCheck($ids===[2],'issuer currency usage from explicit customer ownership');
$other=new NativeCompanyCurrencyReference($db,$p,82,7);essentialCheck($other->clientCurrencyIds()===[],'other issuer never inherits currency from unrelated customer');
echo "Native item currency references: three real SQLite checks passed.\n";

// Exercise the ORIGINAL controller against this real catalogue/ACL. Only the
// CodeIgniter renderer/output are doubles; they expose a revocation during render.
if(class_exists('CompanyRequestScope',false)||class_exists('AdminController',false))throw new LogicException('catalogue controller fixture has unexpected framework owner');
if(!function_exists('_l')){function _l($key,...$arguments){return$key;}}
class CompanyRequestScope {
    public static int $issuer=41;
    static function nativeFor($ci){return$ci===($GLOBALS['catalogPageOwner']??null);}
    static function context(){return new class {function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
}
class AdminController {
    public $db,$load,$output;
    function __construct(){
        $GLOBALS['catalogPageOwner']=$this;$this->db=$GLOBALS['catalogPageDb'];
        $this->output=new class {public array$headers=[];public string$body='';function set_header($header){$this->headers[]=$header;return$this;}function set_output($body){$this->body=$body;return$this;}};
        $this->load=new class {
            public $onRender=null;public array$views=[];
            function model($name){throw new LogicException('global model forbidden');}
            function view($name,$data,$return=false){
                essentialCheck($name==='admin/invoice_items/manage'&&$return===true,'original catalogue view must be buffered');
                essentialCheck(array_column($data['taxes'],'name')===(CompanyRequestScope::$issuer===41?['Secondary tax']:[])&&array_column($data['items_groups'],'name')===(CompanyRequestScope::$issuer===41?['Secondary group']:[]),'original controller reads the real selected catalogue only');
                $this->views[]=$name;ob_start();echo 'synthetic-private-catalogue';
                if($this->onRender!==null)($this->onRender)($data);
                return ob_get_clean();
            }
        };
    }
}
$GLOBALS['catalogPageDb']=$db;
require APPPATH.'controllers/admin/Invoice_items.php';
$catalogPage=new Invoice_items();$catalogPage->index();
essentialCheck($catalogPage->output->body==='synthetic-private-catalogue'&&in_array('Cache-Control: no-store',$catalogPage->output->headers,true),'successful original catalogue response emits only confirmed buffered HTML without caching');
$revokedPage=new Invoice_items();
$revokedPage->load->onRender=static function()use($db,$p){$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=41");};
try{settingThrows(fn()=>$revokedPage->index(),'company_catalog_denied','real issuer revocation during render rejects the entire response');}
finally{$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=41");}
essentialCheck($revokedPage->output->body==='','revoked catalogue emits no buffered private HTML');
$failedPage=new Invoice_items();$beforeLevel=ob_get_level();
$failedPage->load->onRender=static function(){ob_start();echo 'synthetic-partial-private-catalogue';throw new RuntimeException('synthetic_catalog_render_failure');};
settingThrows(fn()=>$failedPage->index(),'synthetic_catalog_render_failure','render exception keeps its actual failure rather than a success receipt');
essentialCheck($failedPage->output->body===''&&ob_get_level()===$beforeLevel,'render exception discards all partial view buffers and restores caller buffer depth');
echo "Original item catalogue controller: real selected data, no global models, buffered success, current issuer revocation and partial-render refusal passed.\n";

// Real shared modal executed with a distinct loader host, as MX_Loader does.
// Pure form-helper doubles do not authorize data; currency usage reads real PDO.
if(!function_exists('get_instance')){function get_instance(){return$GLOBALS['catalogPageOwner'];}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('form_open')){function form_open($action,$attrs=[]){return'<form action="'.e($action).'" id="'.e($attrs['id']??'').'">';}}
if(!function_exists('form_close')){function form_close(){return'</form>';}}
if(!function_exists('form_hidden')){function form_hidden($name,$value=''){return'<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_input')){function render_input($name,$label,$value='',$type='text'){return'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_textarea')){function render_textarea($name,$label){return'<textarea name="'.e($name).'"></textarea>';}}
if(!function_exists('render_select')){function render_select($name,$rows,$fields,$label){return'<select name="'.e($name).'"></select>';}}
if(!function_exists('render_custom_fields')){function render_custom_fields($field){essentialCheck($field==='items','only the original item custom-field slot');return'';}}
if(!function_exists('hooks')){function hooks(){throw new LogicException('catalog_global_hooks_forbidden');}}
if(!function_exists('total_rows')){function total_rows(...$arguments){throw new LogicException('catalog_global_customer_count_forbidden');}}
foreach([41,82]as$modalIssuer){
    CompanyRequestScope::$issuer=$modalIssuer;$modalPage=new Invoice_items();$modalHtml=null;
    $modalPage->load->onRender=static function($data)use($modalPage,&$modalHtml){
        $host=new class($modalPage){
            function __construct(private $owner){}
            function __get($name){return$this->owner->$name;}
            function render($data){extract($data);ob_start();try{require APPPATH.'views/admin/invoice_items/item.php';return ob_get_contents();}finally{ob_end_clean();}}
        };
        essentialCheck(!CompanyRequestScope::nativeFor($host)&&CompanyRequestScope::nativeFor(get_instance()),'loader identity stays untrusted; only canonical controller owns the request');
        $modalHtml=$host->render($data);
    };
    $modalPage->index();
    essentialCheck(str_contains($modalHtml,'name="revision"'),'original modal emits its revision even in actual loader scope');
    essentialCheck(str_contains($modalHtml,'name="rate_currency_2"')===($modalIssuer===41),'non-default currency fields use only the active company customer catalogue');
    essentialCheck(substr_count($modalHtml,'<form ')===1&&substr_count($modalHtml,'</form>')===1,'shared original modal retains one balanced action form');
}
CompanyRequestScope::$issuer=41;
echo "Original item modal in distinct loader scope: canonical owner/revision, A/B real currency usage and forbidden global hooks/customer counts passed.\n";
