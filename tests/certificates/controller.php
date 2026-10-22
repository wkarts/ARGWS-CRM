<?php
require __DIR__.'/support.php';
class A1Context
{
    public int $issuer=10; public int $actor=2;
    public function issuerId(){return $this->issuer;} public function staffId(){return $this->actor;}
    public function assertToken($token){if($token!=='synthetic-context')throw new DomainException('company_context_mismatch');}
}
class CompanyRequestScope
{
    public static $context; public static bool $sealed=true; public static $submitted='synthetic-context';
    public static function sealedFor($ci){return self::$sealed;} public static function context(){return self::$context;}
    public static function submittedToken(){return self::$submitted;} public static function adminUrl($ci,$route=''){return '/admin/'.$route;}
}
function db_prefix(){return 'a1_';}
function redirect($url,$unused='location',$status=302){throw new RuntimeException('redirect_'.$status);}
class A1Input{public string $verb='GET';public array $post=[];public function method($unused){return $this->verb;}public function post($key,$unused=false){return $this->post[$key]??null;}}
class A1Session{public array $values=['issuer_certificate_csrf'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];public function userdata($key){return $this->values[$key]??null;}public function set_userdata($key,$v){$this->values[$key]=$v;}public function set_flashdata($key,$v){$this->values[$key]=$v;}public function flashdata($key){return $this->values[$key]??null;}}
class A1Output{public array $headers=[];public int $status=200;public function set_header($v){$this->headers[]=$v;return $this;}public function set_status_header($v){$this->status=$v;return $this;}}
class A1Config{public function item($key){return config_item($key);}}
class A1Loader{public array $views=[];public function library($unused){}public function view($path,$data){$this->views[]=[$path,$data];}}
class AdminController
{
    public $db,$input,$session,$output,$config,$load,$encryption;
    public function __construct(){ $this->db=$GLOBALS['a1_db'];$this->input=new A1Input();$this->session=new A1Session();$this->output=new A1Output();$this->config=new A1Config();$this->load=new A1Loader();$this->encryption=a1encryption(); }
}
require APPPATH.'controllers/admin/Company_certificates.php';
$GLOBALS['a1_db']=a1db(); CompanyRequestScope::$context=new A1Context();
$ci=new Company_certificates();$ci->index('10');
a1check(count($ci->load->views)===1&&$ci->load->views[0][1]['page']['status']==='unconfigured','GET has only unconfigured safe descriptor');
a1check(in_array('Cache-Control: no-store',$ci->output->headers,true),'GET no-store header');
$ci=new Company_certificates();$ci->input->verb='DELETE';a1reject(fn()=>$ci->index(),'http_405','mutation HTTP methods restricted');
$ci=new Company_certificates();a1reject(fn()=>$ci->index('20'),'http_409','URL target must equal active company');
$ci=new Company_certificates();$ci->input->verb='POST';$ci->input->post=['action'=>'remove','revision'=>'0'];a1reject(fn()=>$ci->index(),'http_403','missing CSRF denied before mutation');
$ci=new Company_certificates();$ci->input->verb='POST';$ci->input->post=['action'=>'replace','revision'=>'0','issuer_certificate_csrf'=>str_repeat('a',64),'certificate_password'=>'DO-NOT-ECHO'];CompanyRequestScope::$submitted='stale';a1reject(fn()=>$ci->index(),'http_409','stale context denied before file read');CompanyRequestScope::$submitted='synthetic-context';
$ci->index();a1check($ci->output->status===422&&!str_contains(json_encode($ci->load->views),'DO-NOT-ECHO'),'missing upload error never echoes password');
CompanyRequestScope::$context->actor=3;$ci=new Company_certificates();a1reject(fn()=>$ci->index(),'http_403','contextual ACL enforced for GET');CompanyRequestScope::$context->actor=2;
CompanyRequestScope::$sealed=false;$ci=new Company_certificates();a1reject(fn()=>$ci->index(),'http_503','unsealed request denied');CompanyRequestScope::$sealed=true;
$source=file_get_contents(APPPATH.'services/CompanyRequestScope.php');a1check(str_contains($source,"'company_certificates'],true)"),'route is admitted only through isolated company boundary');
$reflection=new ReflectionClass(Company_certificates::class);$own=[];foreach($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as$m)if($m->getDeclaringClass()->getName()===Company_certificates::class)$own[]=$m->getName();a1check($own===['index'],'no public export/download action');
echo "Certificate controller tests complete. Framework I/O is adapted; no socket opened.\n";
