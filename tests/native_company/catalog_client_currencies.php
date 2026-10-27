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
    static function nativeFor($ci){return$ci===($GLOBALS['catalogPageOwner']??null);}
    static function context(){return new class {function issuerId(){return 41;}function staffId(){return 7;}};}
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
                essentialCheck(array_column($data['taxes'],'name')===['Secondary tax']&&array_column($data['items_groups'],'name')===['Secondary group'],'original controller reads the real selected catalogue only');
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
