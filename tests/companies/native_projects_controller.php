<?php
/** Real controller/service/SQLite/row markup; only CI request/output and sealed-context adapter are doubled. */
require __DIR__.'/native_project_rows.php';
function show_error($message,$status){throw new RuntimeException('HTTP '.$status);}
class CompanyRequestScope
{
    public static int$issuer;public static int$actor=7;public static bool$admitted=true;
    public static function nativeFor($ci){return self::$admitted;}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}public function token(){return'synthetic-project-context';}};}
    public static function nativeCsrfToken($ci){return'synthetic-project-csrf';}
    public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||($ci->input->post('_native_company_csrf')??'')!=='synthetic-context-proof')throw new DomainException('native_mutation_denied');}
}
class AdminController
{
    public$db;public$input;public$output;public$load;public$security;
    public function __construct(){
        $this->db=$GLOBALS['db'];$this->input=new class{public string$verb='POST';public array$body=[],$query=[];public function method($upper=true){return$this->verb;}public function post($key=null,$clean=false){return$key===null?$this->body:($this->body[$key]??null);}public function get($key){return$this->query[$key]??null;}public function is_ajax_request(){return true;}};
        $this->output=new class{public string$body='';public array$headers=[];public function set_header($v){$this->headers[]=$v;return$this;}public function set_content_type($v){return$this;}public function set_output($v){$this->body=$v;return$this;}};
        $this->load=new class{public array$views=[];public function view($path,$data,$return=false){$this->views[]=[$path,$data];return 'framework-rendered-native-projects';}public function model($name){throw new RuntimeException('Global model hydrated');}public function helper($name){if($name!=='date')throw new RuntimeException('Unexpected helper');}};
        $this->security=new class{public function get_csrf_token_name(){return'framework_csrf';}};
    }
}
require APPPATH.'controllers/admin/Projects.php';
CompanyRequestScope::$issuer=$a;$controller=new Projects();$controller->input->body=$request+['_native_company_csrf'=>'synthetic-context-proof','framework_csrf'=>'synthetic'];$controller->table();$wire=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($wire['iTotalRecords']===$result['recordsTotal']&&count($wire['aaData'])===count($result['rows']),'Real Projects::table uses native row renderer and scoped data');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true),'Authenticated table response is not cached');
$reject=static function(callable$f,int$status){try{$f();}catch(RuntimeException$e){essentialCheck($e->getMessage()==='HTTP '.$status,'Controller returns safe failure status');return;}throw new RuntimeException('Expected failure');};
$controller->input->verb='GET';$reject(fn()=>$controller->table(),403);$controller->input->verb='POST';unset($controller->input->body['_native_company_csrf']);$reject(fn()=>$controller->table(),403);
$controller->input->body['_native_company_csrf']='synthetic-context-proof';CompanyRequestScope::$actor=52;$reject(fn()=>$controller->table(),403);CompanyRequestScope::$actor=7;
$controller->input->body['order']=[['column'=>0,'dir'=>'DROP TABLE']];$reject(fn()=>$controller->table(),422);
$controller->input->verb='GET';$controller->index();$view=$controller->load->views[0];essentialCheck($view[0]==='admin/projects/manage'&&$view[1]['table'] instanceof NativeCompanyProjectTable&&$view[1]['native_project_summary']['total']===$result['recordsTotal'],'Index dispatches existing native view with actual scoped filter/summary data');
essentialCheck($controller->output->body==='framework-rendered-native-projects','Index renders only after actual authority/catalog recheck');
echo 'Native Projects controller and table composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
