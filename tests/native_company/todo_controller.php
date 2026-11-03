<?php
/** Controller + real PDO storage; framework transport is synthetic. Core CSRF has separate tests. */
require __DIR__.'/auxiliary_contract.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
final class CompanyRequestScope
{
    public static string $method='GET';public static bool $permit=false;
    public static function nativeFor($ci):bool{return true;}
    public static function context():object{return new class{public function issuerId():int{return 41;}public function staffId():int{return 12;}};}
    public static function assertNativeMutation($ci):void{if(self::$method!=='POST'||!self::$permit)throw new DomainException('synthetic_csrf_rejected');}
    public static function adminUrl($ci,string $path):string{return'https://synthetic.invalid/admin/'.$path;}
}
class AdminController
{
    public $db;public $input;public $load;
    public function __construct(){
        $this->db=$GLOBALS['db'];
        $this->input=new class{public array $data=[];public function post($key=null,$filter=null){return$key===null?$this->data:($this->data[$key]??null);}public function is_ajax_request():bool{return true;}};
        $this->load=new class{public function model($name):void{throw new RuntimeException('legacy_model_loaded');}};
    }
}
function redirect($url,$method=null,$status=null):void{$GLOBALS['redirectReceipt']=[$url,$status];}
require APPPATH.'controllers/admin/Todo.php';
$controller=new Todo();$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}company_todos")->row()->n;
$controller->input->data=['description'=>'Controller real','todoid'=>''];
settingThrows(fn()=>$controller->todo(),'synthetic_csrf_rejected','GET mutation rejected before service');
CompanyRequestScope::$method='POST';
settingThrows(fn()=>$controller->todo(),'synthetic_csrf_rejected','missing transport permit rejected before service');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_todos")->row()->n===$before,'denied transport never writes');
CompanyRequestScope::$permit=true;$controller->todo();
essentialCheck($GLOBALS['redirectReceipt']===['https://synthetic.invalid/admin/todo',303],'save returns native route with 303');
$controller->input->data=['finished'=>'0','todo_page'=>'0'];ob_start();$controller->index();$rows=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
essentialCheck(count($rows)===1&&$rows[0]['description']==='Controller real','native AJAX DTO reflects committed scoped row');
$id=$rows[0]['todoid'];essentialCheck((int)$id>4294967296,'controller emits namespace transport id');
ob_start();$controller->get_by_id($id);$detail=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);essentialCheck($detail['todoid']===$id&&$detail['revision']==1,'editor receives ownership-bound revision');
$controller->input->data=['revision'=>'1'];ob_start();$controller->delete_todo_item($id);$deleted=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);essentialCheck($deleted['success']===true,'POST deletes only named private todo');
settingThrows(fn()=>$controller->get_by_id(1),'company_auxiliary_access_denied','native raw ID cannot select unrelated storage');
echo "Native todo controller and owned PDO storage: passed.\n";
