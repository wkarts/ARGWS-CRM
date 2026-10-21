<?php
require __DIR__.'/task_copies.php';$controllerTaskCopyStart=$GLOBALS['essentialChecks'];
class CompanyRequestScope
{
    public static bool $sealed=true;public static int $issuer;public static int $actor=7;
    public static function sealedFor($owner):bool{return self::$sealed;}
    public static function context(){return new class {public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}public function isPrimary(){return false;}public function available(){return [];}public function csrf(){return 'synthetic-switch';}};}
    public static function token(){return 'synthetic-pusher-context';}public static function invalidateCaches($owner):void{}
    public static function adminUrl($owner,string $path=''):string{return 'https://synthetic.invalid/admin/'.$path;}
}
class AdminController{public function __get($name){return $GLOBALS['CI']->$name;}}
function show_404(){$GLOBALS['CI']->output->status=404;}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;}
function redirect($url,$method='location',$code=302){$GLOBALS['pusherRedirect']=$url;$GLOBALS['CI']->output->status=$code;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($path=''){return 'https://synthetic.invalid/'.$path;}
function form_open($url,$attributes=[]){$attrs='';foreach($attributes as$k=>$v)$attrs.=' '.htmlspecialchars($k,ENT_QUOTES,'UTF-8').'="'.htmlspecialchars($v,ENT_QUOTES,'UTF-8').'"';return '<form method="post" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"'.$attrs.'><input type="hidden" name="synthetic_csrf" value="native"><input type="hidden" name="_company_context" value="'.CompanyRequestScope::token().'">';}
function form_close(){return '</form>';}
$ci=$GLOBALS['CI'];
$ci->input=new class {public string $verb='GET';public array $gets=[],$posts=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function get($k,$f=false){return $this->gets[$k]??null;}public function post($k,$f=false){return $this->posts[$k]??null;}};
$ci->output=new class {public int $status=200;public array $headers=[];public string $body='';public function set_header($v){$this->headers[]=$v;return $this;}public function set_status_header($v){$this->status=$v;return $this;}public function set_content_type($v){$this->headers[]='Content-Type: '.$v;return $this;}public function set_output($v){$this->body=$v;return $this;}};
$ci->session=new class {public array $values=[];public function userdata($k){return $this->values[$k]??null;}public function set_userdata($k,$v){$this->values[$k]=$v;}};
$ci->load=new class {public array $views=[];public function library($name){if($name!=='encryption')throw new LogicException('unexpected framework library');}public function view($n,$data){$this->views[]=[$n,$data];}};
$ci->encryption=new class {public function encrypt($v){return 'sealed:'.base64_encode($v);}public function decrypt($v){return str_starts_with($v,'sealed:')?base64_decode(substr($v,7),true):false;}};
$harness=new class {public function __get($name){return $GLOBALS['CI']->$name;}public function render(array $data):string{extract($data);ob_start();try{include APPPATH.'views/admin/companies/task_copies.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}}};

require APPPATH.'controllers/admin/Company_task_copies.php';$http=new Company_task_copies();CompanyRequestScope::$issuer=$b;
foreach(['project'=>$tb,'standalone'=>$sourceStandalone[$b]]as$kind=>$source){
 $ci->input->verb='GET';$ci->input->posts=[];$ci->input->gets=['kind'=>$kind,'source'=>$source];$ci->output->status=200;$http->index();$view=end($ci->load->views);$html=$harness->render($view[1]);
 essentialCheck($view[0]==='admin/companies/task_copies'&&$view[1]['preview']['source']['task']['id']==$source,'real controller resolves both finite own namespaces');
 essentialCheck(str_contains($html,'id="task-copy-review"')&&str_contains($html,'company_task_copies_csrf')&&str_contains($html,'synthetic_csrf')&&str_contains($html,'_company_context'),'real task-copy form retains dedicated/native/context CSRF');
 essentialCheck(str_contains($html,'name="review[assignee_id]"')&&!str_contains($html,'name="review[files]"')&&!str_contains($html,'name="review[copy_dependencies]"'),'GUI requires current assignee and offers no legacy inheritance options');
 $fields=['action'=>'copy_reviewed_task','kind'=>$kind,'source_task_id'=>$source,'review'=>taskCopyReview($view[1]['preview'],'Controller copy '.$kind),'request_key'=>hash('sha256','individual-task-controller-'.$kind)];$ci->input->verb='POST';$ci->input->gets=[];$ci->input->posts=$fields;$ci->output->status=200;$http->index();essentialCheck($ci->output->status===403,'controller rejects missing dedicated CSRF before writer');
 $fields['company_task_copies_csrf']=$ci->session->userdata('company_task_copies_csrf');$ci->input->posts=$fields;$ci->output->status=200;$http->index();essentialCheck($ci->output->status===303&&str_contains($GLOBALS['pusherRedirect'],'task_copies?receipt='.$fields['request_key']),'canonical copy POST redirects to stable own receipt');
 $http->index();essentialCheck($ci->output->status===303,'same POST replays only same task receipt');$ci->input->verb='GET';$ci->input->posts=[];$ci->input->gets=['receipt'=>$fields['request_key']];$ci->output->status=200;$http->index();$view=end($ci->load->views);$html=$harness->render($view[1]);essentialCheck(str_contains($html,'id="task-copy-receipt"')&&str_contains($html,'id="task-copy-result"'),'actual receipt renders authenticated new task link');
}
foreach([[],['kind'=>'project','source'=>[$tb]],['receipt'=>['bad']],['kind'=>'project','source'=>$tb,'receipt'=>$fields['request_key']]]as$query){$ci->input->gets=$query;$ci->output->status=200;$http->index();essentialCheck($ci->output->status===404,'finite controller rejects malformed/conflicting queries');}
$ci->input->gets=['kind'=>'project','source'=>$ta];$ci->output->status=200;$http->index();essentialCheck($ci->output->status===403,'foreign source never reaches template');CompanyRequestScope::$actor=12;$ci->input->gets=['kind'=>'project','source'=>$tb];$ci->output->status=200;$http->index();essentialCheck($ci->output->status===403,'restricted actor revoked create grant cannot render review');CompanyRequestScope::$actor=7;$ci->input->verb='DELETE';$ci->output->status=200;$http->index();essentialCheck($ci->output->status===405,'unsupported verbs never dispatch');$ci->input->verb='POST';$ci->input->posts=['company_task_copies_csrf'=>$fields['company_task_copies_csrf'],'action'=>'copy'];$ci->output->status=200;$http->index();essentialCheck($ci->output->status===404,'legacy copy action is not a dispatcher');$ci->output->status=200;$http->index('child');essentialCheck($ci->output->status===404,'unexpected subroute is not admitted');CompanyRequestScope::$sealed=false;$ci->output->status=200;$http->index();essentialCheck($ci->output->status===503,'native company sealed context is mandatory');
echo 'Task copy actual controller/templates: '.($GLOBALS['essentialChecks']-$controllerTaskCopyStart).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
