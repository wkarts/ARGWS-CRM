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

$catalogHooksBefore=$GLOBALS['essentialHooks'];
$GLOBALS['essentialHooks']=new class {function __call($name,$arguments){throw new LogicException('catalog_global_hooks_forbidden');}};
// Exercise the ORIGINAL controller against this real catalogue/ACL. Only the
// CodeIgniter renderer/output are doubles; they expose a revocation during render.
if(class_exists('CompanyRequestScope',false)||class_exists('AdminController',false))throw new LogicException('catalogue controller fixture has unexpected framework owner');
if(!function_exists('_l')){function _l($key,...$arguments){return$key;}}
if(!class_exists('CompanyRequestScope',false)){
class CompanyRequestScope {
    public static int $issuer=41;
    static function nativeFor($ci){return$ci===($GLOBALS['catalogPageOwner']??null);}
    static function context(){return new class {function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
}
}
if(!class_exists('AdminController',false)){
class AdminController {
    public $db,$load,$output,$input;
    function __construct(){
        $GLOBALS['catalogPageOwner']=$this;$GLOBALS['CI']=$this;$this->db=$GLOBALS['catalogPageDb'];
        $this->output=new class {public array$headers=[];public string$body='';public string$mime='text/html';function set_header($header){$this->headers[]=$header;return$this;}function set_output($body){$this->body=$body;return$this;}function set_content_type($value){$this->mime=$value;return$this;}};
        $this->input=new class {public array$data=[];function get($name=null,$xss=false){return$this->data[$name]??null;}};
        $this->load=new class {
            public $onRender=null;public array$views=[];
            function model($name){throw new LogicException('global model forbidden');}
            function view($name,$data,$return=false){
                essentialCheck($name==='admin/invoice_items/manage'&&$return===true,'original catalogue view must be buffered');
                foreach(['taxes'=>['company_taxes','company_tax'],'items_groups'=>['company_item_groups','company_item_group']]as$field=>[$table,$kind]){
                    $expected=[];foreach($GLOBALS['catalogPageDb']->query('SELECT id,name FROM '.db_prefix().$table.' WHERE issuer_id=?',[CompanyRequestScope::$issuer])->result_array()as$row)$expected[NativeCompanyResourceId::encode($kind,$row['id'])]=$row['name'];
                    $actual=array_column($data[$field],'name','id');ksort($expected,SORT_STRING);ksort($actual,SORT_STRING);
                    essentialCheck($actual===$expected&&count($actual)===count($data[$field]),'original controller returns every selected-company '.$field.' identity/name and no foreign or aliased row');
                }
                $this->views[]=$name;ob_start();echo 'synthetic-private-catalogue';
                if($this->onRender!==null)($this->onRender)($data);
                return ob_get_clean();
            }
        };
    }
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
if(!function_exists('get_instance')){function &get_instance(){return$GLOBALS['catalogPageOwner'];}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('form_open')){function form_open($action,$attrs=[]){return'<form action="'.e($action).'" id="'.e($attrs['id']??'').'">';}}
if(!function_exists('form_close')){function form_close(){return'</form>';}}
if(!function_exists('form_hidden')){function form_hidden($name,$value=''){return'<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_input')){function render_input($name,$label,$value='',$type='text'){return'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'">';}}
if(!function_exists('render_textarea')){function render_textarea($name,$label){return'<textarea name="'.e($name).'"></textarea>';}}
if(!function_exists('render_select')){function render_select($name,$rows,$fields,$label){return'<select name="'.e($name).'"></select>';}}
if(!function_exists('form_error')){function form_error($name){return'';}}
if(!function_exists('admin_url')){function admin_url($path=''){return'http://fixture.test/admin/'.$path;}}
if(!function_exists('hooks')){function hooks(){throw new LogicException('catalog_global_hooks_forbidden');}}
if(!function_exists('total_rows')){function total_rows(...$arguments){throw new LogicException('catalog_global_customer_count_forbidden');}}
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
require APPPATH.'helpers/custom_fields_helper.php';
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
    essentialCheck(preg_match('/<input[^>]*type="number"[^>]*name="rate"[^>]*step="any"/',$modalHtml)===1,'original base price retains fractional HTML input validity');
    essentialCheck(str_contains($modalHtml,'name="rate_currency_2"')===($modalIssuer===41),'non-default currency fields use only the active company customer catalogue');
    essentialCheck(substr_count($modalHtml,'<form ')===1&&substr_count($modalHtml,'</form>')===1,'shared original modal retains one balanced action form');
}
CompanyRequestScope::$issuer=41;
echo "Original item modal in distinct loader scope: canonical owner/revision, A/B real currency usage and forbidden global hooks/customer counts passed.\n";

// Catalogue metadata and invoice-line metadata share definitions, not identities.
CompanyRequestScope::$issuer=41;$catalogFieldsPage=new Invoice_items();
$catalogDescription='Literal <tag> & "quote"'.PHP_EOL.'Next line';
$catalogItemId=$namedCatalog->saveItem(['description'=>'Catalogue helper resource','rate'=>'4.25','long_description'=>$catalogDescription]);
essentialCheck(render_custom_fields('items',$catalogItemId,[],['items_pr'=>true])==='','actual production helper can read a catalogue item even when it has no custom fields');
$customDefinition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Catalogue metadata','type'=>'input','active'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[41,'items','catalogue_metadata_probe',json_encode($customDefinition,JSON_THROW_ON_ERROR)]);
$fieldSource=(int)$db->insert_id();$fieldWire=NativeCompanyResourceId::encode('company_custom_field',$fieldSource);
$catalogReference=NativeCompanyResourceId::decode('item',$catalogItemId);
$db->query('INSERT INTO '.$p.'company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,?,?,?,1)',[41,'company_custom_field',$fieldSource,'company_item',$catalogReference['source_id'],'Catalogue <literal>']);
$catalogHtml=render_custom_fields('items',$catalogItemId,[],['items_pr'=>true]);
essentialCheck(str_contains($catalogHtml,'name="custom_fields[items]['.$fieldWire.']"')&&str_contains($catalogHtml,'value="Catalogue &lt;literal&gt;"'),'real helper renders only the authorized catalogue value with the shared form taxonomy and escaping');
settingThrows(fn()=>render_custom_fields('items',$catalogItemId),'native_resource_namespace_mismatch','catalogue identity cannot masquerade as an invoice line');
settingThrows(fn()=>render_custom_fields('items',NativeCompanyResourceId::encode('company_invoice_draft_line',1),[],['items_pr'=>true]),'native_resource_namespace_mismatch','invoice draft line cannot masquerade as a catalogue item');
settingThrows(fn()=>render_custom_fields('items','1',[],['items_pr'=>true]),'native_custom_field_denied','secondary helper cannot use a principal raw catalogue ID');
CompanyRequestScope::$issuer=82;$foreignCatalogPage=new Invoice_items();
settingThrows(fn()=>render_custom_fields('items',$catalogItemId,[],['items_pr'=>true]),'native_custom_field_denied','another company cannot read the item custom-field definitions or values');
CompanyRequestScope::$issuer=41;$activeCatalogPage=new Invoice_items();
$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=41");
try{settingThrows(fn()=>render_custom_fields('items',$catalogItemId,[],['items_pr'=>true]),'native_custom_field_denied','revoked issuer cannot hydrate catalogue custom fields');}
finally{$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=41");}
echo "Real catalogue custom-field helper: empty/value reads, escaped values, disjoint invoice namespace, principal/foreign IDs and revoked issuer passed.\n";

// Real native JSON endpoint: raw textarea text and escaped preview coexist.
$catalogJsonPage=new Invoice_items();ob_start();
try{$catalogJsonPage->get_item_by_id($catalogItemId);$jsonWire=ob_get_contents();}finally{ob_end_clean();}
$catalogJson=json_decode($jsonWire,true,32,JSON_THROW_ON_ERROR);
essentialCheck($catalogJsonPage->output->mime==='application/json'&&in_array('Cache-Control: no-store',$catalogJsonPage->output->headers,true),'native catalogue read emits non-cacheable JSON');
essentialCheck($catalogJson['long_description_text']===$catalogDescription&&str_contains($catalogJson['long_description'],'&lt;tag&gt;'),'native read keeps exact edit text and a separately escaped preview');
essentialCheck(!str_contains($jsonWire,'<'),'JSON wire never embeds executable HTML delimiters');
CompanyRequestScope::$issuer=82;$foreignJsonPage=new Invoice_items();ob_start();
try{settingThrows(fn()=>$foreignJsonPage->get_item_by_id($catalogItemId),'company_catalog_denied','native JSON controller refuses a foreign company item');$foreignJson=ob_get_contents();}finally{ob_end_clean();}
essentialCheck($foreignJson==='','foreign JSON read emits no payload');
CompanyRequestScope::$issuer=41;
$GLOBALS['essentialHooks']=$catalogHooksBefore;
