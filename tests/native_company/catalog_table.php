<?php
/** Original controller + actual catalogues/SQL, with synthetic framework request/response. */
require __DIR__.'/primary_catalog.php';

class CompanyRequestScope
{
    public static int $issuer=41,$actor=7;
    public static bool $csrf=true;
    public static function nativeFor($ci):bool{return $ci===$GLOBALS['CI'];}
    public static function sealedFor($ci):bool{return self::nativeFor($ci);}
    public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
    public static function token(){return 'synthetic-context';}
    public static function nativeCsrfToken($ci){return 'synthetic-csrf';}
    public static function assertNativeMutation($ci):void{if(!self::$csrf||$ci->input->method(true)!=='POST')throw new DomainException('native_company_csrf_invalid');}
}
class AdminController
{
    public $db,$input,$output;
    public function __construct(){
        $GLOBALS['CI']=$this;$this->db=$GLOBALS['catalogTableDb'];
        $this->input=new class{public array $data=[];public string $verb='POST';function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($name=null,$clean=false){return $name===null?$this->data:($this->data[$name]??null);}};
        $this->output=new class{public array $headers=[];public string $mime='';function set_header($header){$this->headers[]=$header;return $this;}function set_content_type($type){$this->mime=$type;return $this;}};
    }
}
function _l($key,...$args){return $key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($path=''){return '/admin/'.$path;}
function get_option($key){return '0';}
function get_custom_fields($to){return(new NativeCompanyCustomFields($GLOBALS['catalogTableDb'],db_prefix()))->definitions(CompanyRequestScope::$issuer,CompanyRequestScope::$actor,$to,['active'=>1]);}
function get_custom_field_value($id,$field,$to){return(new NativeCompanyCustomFields($GLOBALS['catalogTableDb'],db_prefix()))->values(CompanyRequestScope::$issuer,CompanyRequestScope::$actor,$to,$id)[$field]??'';}
$GLOBALS['catalogTableDb']=$db;
require APPPATH.'controllers/admin/Invoice_items.php';
$checks=$GLOBALS['essentialChecks'];

$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Order number','type'=>'number']);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(41,?,?,?,1)',['items','table_order_number',json_encode($definition,JSON_THROW_ON_ERROR)]);
$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$catalog=new NativeCompanyCatalogUi($db,$p,41,7);$created=[];
foreach([['Zulu Order probe','2.0000','100'],['Alpha Order probe','1000.2500','2'],['Middle Order probe','20.0000','20']] as [$description,$rate,$number]){
    $created[]=$catalog->saveItem(['description'=>$description,'long_description'=>'Long text needle','rate'=>$rate,'unit'=>'kg','custom_fields'=>['items_pr'=>[$field=>$number]]]);
}
(new NativeCompanyCatalogUi($db,$p,82,7))->saveItem(['description'=>'Foreign Order probe','rate'=>'0.0001']);
// More than one server page is necessary to prove the original All/export choice.
for($n=0;$n<505;$n++)$db->query('INSERT INTO '.$p.'company_items(issuer_id,description,long_description,rate,currency_rates_json,unit,revision) VALUES(41,?,?,?,?,?,1)',[
    'Bulk row '.str_pad((string)$n,4,'0',STR_PAD_LEFT),'Bulk literal %_','1.0000','{}','un',
]);
$snapshot=$db->query('SELECT * FROM '.$p.'company_items ORDER BY id')->result_array();
$wire=static function(string $search='',int $column=1,string $direction='asc',int $length=25):array{
    $columns=[];foreach(array_merge(array_fill(0,8,''),array_column(get_custom_fields('items'),'type'))as $i=>$type)$columns[]=['data'=>(string)$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
    return ['draw'=>'7','start'=>'0','length'=>(string)$length,'columns'=>$columns,'order'=>[['column'=>(string)$column,'dir'=>$direction]],'search'=>['value'=>$search,'regex'=>'false'],'last_order_identifier'=>'invoice-items'];
};
$call=static function(array $request):array{
    $controller=new Invoice_items();$controller->input->data=$request;ob_start();
    try{$controller->table();$body=ob_get_contents();}finally{ob_end_clean();}
    essentialCheck($controller->output->mime==='application/json'&&in_array('Cache-Control: no-store',$controller->output->headers,true),'Item table returns JSON without cache');
    return json_decode($body,true,64,JSON_THROW_ON_ERROR);
};

$result=$call($wire('Order probe',3,'asc',2));
essentialCheck($result['draw']===7&&$result['iTotalDisplayRecords']===3&&count($result['aaData'])===2,'SQL search/limit count all matching selected-company rows');
essentialCheck(str_contains($result['aaData'][0][1],'Zulu Order probe')&&str_contains($result['aaData'][1][1],'Middle Order probe'),'Native numeric rate sorting occurs before pagination, not description sorting or lexical decimals');
$custom=$call($wire('Order probe',8,'asc',-1));
essentialCheck(str_contains($custom['aaData'][0][1],'Alpha Order probe')&&str_contains($custom['aaData'][2][1],'Zulu Order probe'),'Authorized numeric custom field sorts numerically using its own source namespace');
$long=$call($wire('Long text needle',1,'asc',-1));
essentialCheck($long['iTotalDisplayRecords']===3,'Original long description participates in global item search');
$all=$call($wire('Bulk row',1,'asc',-1));
essentialCheck($all['iTotalDisplayRecords']===505&&count($all['aaData'])===505,'All/export spans multiple server pages without truncating at 100 or 500');
$literal=$call($wire('%_',1,'asc',-1));
essentialCheck($literal['iTotalDisplayRecords']===505,'Percent and underscore are literal search text, not wildcard escalation');
CompanyRequestScope::$issuer=82;
$foreign=$call($wire('Long text needle',1,'asc',-1));
essentialCheck($foreign['iTotalDisplayRecords']===0&&$foreign['aaData']===[],'Changing company clears other-company item results');
CompanyRequestScope::$issuer=$principal;
$historical=$call($wire('Kept',3,'desc',-1));
essentialCheck($historical['iTotalDisplayRecords']===1&&str_contains($historical['aaData'][0][1],'Historical item'),'Principal still searches its native catalogue with unchanged IDs');
CompanyRequestScope::$issuer=41;
$bad=$wire();$bad['order'][0]['dir']='asc; DROP TABLE items';
settingThrows(fn()=>$call($bad),'native_company_table_request_invalid','Submitted sort expression is rejected before SQL');
$bad=$wire();$bad['search']['regex']='true';
settingThrows(fn()=>$call($bad),'native_company_table_request_invalid','Unsupported regex cannot silently change search semantics');
CompanyRequestScope::$csrf=false;
settingThrows(fn()=>$call($wire()),'native_company_csrf_invalid','Original item table validates native POST context');
CompanyRequestScope::$csrf=true;
CompanyRequestScope::$actor=99;
settingThrows(fn()=>new Invoice_items(),'company_catalog_denied','An unrelated actor cannot construct the catalogue reader');
CompanyRequestScope::$actor=7;
essentialCheck($db->query('SELECT * FROM '.$p.'company_items ORDER BY id')->result_array()===$snapshot,'Table reads and rejected requests preserve the catalogue exactly');
echo 'Original item DataTables: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed with original controller and real SQL.'.PHP_EOL;
