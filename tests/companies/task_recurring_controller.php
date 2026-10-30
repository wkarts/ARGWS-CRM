<?php
/** Actual controller and templates with real tenant writers; no service stub. */
require __DIR__.'/task_recurring_fixture.php';
$controllerChecks = $GLOBALS['essentialChecks'];
class CompanyRequestScope
{
    public static bool $sealed = true;
    public static int $issuer;
    public static int $actor = 7;
    public static function nativeFor($owner): bool { return true; }
    public static function sealedFor($owner): bool { return self::$sealed; }
    public static function context() { return new class {
        public function issuerId() { return CompanyRequestScope::$issuer; }
        public function staffId() { return CompanyRequestScope::$actor; }
        public function isPrimary() { return false; }
        public function available() { return []; }
        public function csrf() { return 'synthetic-switch'; }
    }; }
    public static function token() { return 'synthetic-context'; }
    public static function invalidateCaches($owner): void {}
    public static function adminUrl($owner, string $path=''): string { return 'https://synthetic.invalid/admin/'.$path; }
}
class AdminController { public function __construct() {} public function __get($name) { return $GLOBALS['CI']->$name; } }
function show_error($message, $status=500) { $GLOBALS['CI']->output->status = $status; }
function redirect($url, $method='location', $code=302) { $GLOBALS['recurringRedirect']=$url; $GLOBALS['CI']->output->status=$code; }
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function base_url($path='') { return 'https://synthetic.invalid/'.$path; }
function form_open($url, $attributes=[]) {
    $attrs=''; foreach ($attributes as $key=>$value) { $attrs.=' '.e($key).'="'.e($value).'"'; }
    return '<form method="post" action="'.e($url).'"'.$attrs.'><input type="hidden" name="synthetic_csrf" value="native"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'">';
}
function form_close() { return '</form>'; }
$ci=$GLOBALS['CI'];
$ci->input=new class {
    public string $verb='GET'; public array $gets=[], $posts=[];
    public function method($upper=false) { return $upper?$this->verb:strtolower($this->verb); }
    public function get($key,$filter=false) { return $this->gets[$key]??null; }
    public function post($key,$filter=false) { return $this->posts[$key]??null; }
};
$ci->output=new class {
    public int $status=200; public array $headers=[];
    public function set_header($value) { $this->headers[]=$value; return $this; }
    public function set_status_header($value) { $this->status=$value; return $this; }
};
$ci->session=new class {
    public array $values=[];
    public function userdata($key) { return $this->values[$key]??null; }
    public function set_userdata($key,$value) { $this->values[$key]=$value; }
};
$ci->load=new class { public array $views=[]; public function view($name,$data) { $this->views[]=[$name,$data]; } };
$render=new class {
    public function __get($name) { return $GLOBALS['CI']->$name; }
    public function view(array $data): string {
        extract($data); ob_start();
        try { include APPPATH.'views/admin/tasks/native_recurring.php'; return ob_get_clean(); }
        catch (Throwable $e) { ob_end_clean(); throw $e; }
    }
};
require APPPATH.'controllers/admin/Tasks.php';
$http=new Tasks(); $schedules=[];
foreach ([$a,$b,$primary] as $issuer) {
    CompanyRequestScope::$issuer=$issuer;
    foreach (['project','standalone'] as $kind) {
        $source=$sources[$kind][$issuer];
        $ci->input->verb='GET'; $ci->input->gets=['kind'=>$kind,'source'=>$source]; $ci->input->posts=[]; $ci->output->status=200;
        $http->recurring(); $view=end($ci->load->views); $html=$render->view($view[1]);
        rrCheck($ci->output->status===200 && $view[1]['source']['id']==$source, 'own source is usable in actual controller, all three companies and both namespaces');
        rrCheck(str_contains($html,'id="task-recurring-create"') && str_contains($html,'synthetic_csrf') && str_contains($html,'company_task_recurring_csrf') && str_contains($html,'_company_context'), 'native, dedicated and context CSRF remain in the actual form');
        $fields=['kind'=>$kind,'action'=>'create','source_id'=>$source,'source_revision'=>$view[1]['source']['revision'],'calendar'=>rrCalendar(['first_date'=>gmdate('Y-m-d'),'cycles'=>1]),'request_key'=>hash('sha256','controller-recurring-'.$issuer.'-'.$kind)];
        $before=rrSnapshot(); $ci->input->verb='POST'; $ci->input->gets=[]; $ci->input->posts=$fields; $ci->output->status=200; $http->recurring();
        rrCheck($ci->output->status===403 && rrSnapshot()===$before, 'missing dedicated CSRF creates no task, schedule or receipt');
        $fields['company_task_recurring_csrf']=$ci->session->userdata('company_task_recurring_csrf'); $ci->input->posts=$fields; $ci->output->status=200; $http->recurring();
        rrCheck($ci->output->status===303, 'actual positive schedule POST confirms and redirects');
        parse_str(parse_url($GLOBALS['recurringRedirect'],PHP_URL_QUERY),$query); $id=(int)$query['schedule']; $schedules[$kind][$issuer]=$id;
        $http->recurring(); rrCheck($ci->output->status===303 && str_contains($GLOBALS['recurringRedirect'],'schedule='.$id), 'same confirmed command redirects to same schedule');
        $ci->input->verb='GET'; $ci->input->gets=['kind'=>$kind,'schedule'=>$id]; $ci->input->posts=[]; $ci->output->status=200; $http->recurring(); $view=end($ci->load->views); $html=$render->view($view[1]);
        rrCheck($ci->output->status===200 && $view[1]['detail']['id']===$id && str_contains($html,'Processar próximo ciclo elegível'), 'reopened own detail exposes an actual executable operation');
        $ci->input->verb='POST'; $ci->input->gets=[];
        $ci->input->posts=['kind'=>$kind,'action'=>'run','schedule_id'=>$id,'cycle'=>'0','request_key'=>hash('sha256','run-'.$issuer.'-'.$kind),'company_task_recurring_csrf'=>$fields['company_task_recurring_csrf']];
        $ci->output->status=200; $http->recurring(); $detail=$rr->get($issuer,7,$kind,$id);
        rrCheck($ci->output->status===303 && $detail['state']==='completed' && count($detail['runs'])===1, 'GUI command executes one real due child');
        $child=$detail['runs'][0]['task_id']; $http->recurring(); $again=$rr->get($issuer,7,$kind,$id);
        rrCheck($ci->output->status===303 && count($again['runs'])===1 && $again['runs'][0]['task_id']===$child, 'posted cycle pin prevents a duplicate child on retry');
        $ci->input->verb='GET'; $ci->input->gets=['kind'=>$kind,'schedule'=>$id]; $ci->input->posts=[]; $ci->output->status=200; $http->recurring(); $html=$render->view(end($ci->load->views)[1]);
        rrCheck(str_contains($html,'Concluída') && str_contains($html,'Abrir tarefa #'.$child) && !str_contains($html,'Processar próximo ciclo elegível'), 'completed controller template contains confirmed child and no further run button');
    }
}
CompanyRequestScope::$issuer=$b;
foreach (['project','standalone'] as $kind) {
    foreach ([['source'=>$sources[$kind][$a]],['schedule'=>$schedules[$kind][$a]]] as $query) {
        $ci->input->gets=['kind'=>$kind]+$query; $ci->output->status=200; $count=count($ci->load->views); $http->recurring();
        rrCheck($ci->output->status===403 && count($ci->load->views)===$count, 'cross-company source/detail denied before view data');
    }
}
$ci->input->gets=['kind'=>['project']]; $ci->output->status=200; $http->recurring(); rrCheck($ci->output->status===422, 'array-valued modality rejected');
$ci->input->verb='DELETE'; $ci->output->status=200; $http->recurring(); rrCheck($ci->output->status===405, 'unsupported verb rejected');
CompanyRequestScope::$sealed=false; $ci->input->verb='GET'; $ci->output->status=200; $http->recurring(); rrCheck($ci->output->status===503, 'sealed native context required');
echo 'Task recurrence actual controller/templates: '.($GLOBALS['essentialChecks']-$controllerChecks).' added / '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
