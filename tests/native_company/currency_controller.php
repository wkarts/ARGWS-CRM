<?php
/** Real controller/ACL storage; model and HTTP seam synthetic for negative ordering proofs. */
require __DIR__.'/auxiliary_contract.php';
$GLOBALS['currencyActor']=12;$GLOBALS['currencyModels']=0;$GLOBALS['currencyWrites']=0;
function get_staff_user_id(){return$GLOBALS['currencyActor'];}
function is_admin(){return true;}
final class CompanyRequestScope
{
    public static bool $native=false;public static string $method='GET';public static bool $csrf=false;
    public static function nativeFor($ci):bool{return self::$native;}
    public static function assertNativeMutation($ci):void{if(!self::$native||self::$method!=='POST'||!self::$csrf)throw new DomainException('native_company_csrf_invalid');}
}
class AdminController
{
    public $db;public $input;public $load;public $currencies_model;
    public function __construct(){
        $this->db=$GLOBALS['db'];$this->input=new class{public array $data=[];public function post($key=null){return$key===null?$this->data:($this->data[$key]??null);}};
        $this->load=new class($this){public function __construct(private $ci){}public function model($name){$GLOBALS['currencyModels']++;$this->ci->currencies_model=new class{public function add($data){$GLOBALS['currencyWrites']++;return false;}public function delete($id){$GLOBALS['currencyWrites']++;return false;}};}};
    }
}
require APPPATH.'controllers/admin/Currencies.php';
settingThrows(fn()=>new Currencies(),'company_currency_owner_required','ordinary global admin denied even without native dispatch');
essentialCheck($GLOBALS['currencyModels']===0,'owner denied before global currency model hydration');
$GLOBALS['currencyActor']=7;$c=new Currencies();
$c->input->data=['currencyid'=>'','name'=>'USD','symbol'=>'$','placement'=>'before','decimal_separator'=>'.','thousand_separator'=>','];
settingThrows(fn()=>$c->manage(),'native_company_csrf_invalid','unsealed principal write cannot rely on old APP_CSRF flag');
CompanyRequestScope::$native=true;settingThrows(fn()=>$c->delete(1),'native_company_csrf_invalid','GET currency deletion denied');
CompanyRequestScope::$method='POST';settingThrows(fn()=>$c->manage(),'native_company_csrf_invalid','missing CSRF denied');
CompanyRequestScope::$csrf=true;$c->input->data['rate_currency_99']='injected';settingThrows(fn()=>$c->manage(),'company_catalog_invalid','unknown fields rejected before model');unset($c->input->data['rate_currency_99']);
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");settingThrows(fn()=>$c->manage(),'company_currency_owner_required','owner revocation rechecked on action');
essentialCheck($GLOBALS['currencyWrites']===0,'all negative paths leave catalogue untouched');
echo "Native currency controller owner/POST/CSRF/field/revocation preflight: passed.\n";
