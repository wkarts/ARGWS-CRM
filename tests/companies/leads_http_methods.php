<?php
/** Actual HTTP trait's finite method contract, before bearer parsing or data access. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');define('APP_MODULES_PATH',dirname(__DIR__,2).'/modules/');
require APPPATH.'services/CompanyLeadsHttpApi.php';
function log_message($level,$message):void{}
final class LeadMethodsOutput {
    public int $status=200;public array $headers=[];public string $body='';
    public function set_content_type(...$unused):self{return $this;}
    public function set_header($header):self{$this->headers[]=$header;return $this;}
    public function set_status_header($status):self{$this->status=$status;return $this;}
    public function set_output($body):self{$this->body=$body;return $this;}
}
final class LeadMethodsInput {
    public int $headerReads=0;
    public function __construct(public string $verb){}
    public function method($upper=false):string{return $upper?$this->verb:strtolower($this->verb);}
    public function get_request_header($key,$filter=false){++$this->headerReads;return null;}
}
final class LeadMethodsController {
    use CompanyLeadsHttpApi;
    private bool $ready=true;
    public LeadMethodsInput $input;public LeadMethodsOutput $output;public $db;
    public function __construct(string $method){$this->input=new LeadMethodsInput($method);$this->output=new LeadMethodsOutput();$this->db=new class{public function __call($name,$args){throw new LogicException('unexpected_data_access');}};}
}
$checks=0;$failures=[];
function lmCheck(bool $condition,string $name):void{++$GLOBALS['checks'];if(!$condition)$GLOBALS['failures'][]=$name;}
foreach(['lead_assignees'=>[],'lead_exports'=>[],'lead_files'=>['1',str_repeat('a',64)]] as $endpoint=>$args){
    foreach(['POST','PUT','DELETE','PATCH','OPTIONS'] as $verb){
        $c=new LeadMethodsController($verb);$c->$endpoint(...$args);
        lmCheck($c->output->status===405&&in_array('Allow: GET, HEAD',$c->output->headers,true)&&$c->input->headerReads===0,$endpoint.' '.$verb.' rejected before bearer/data with exact Allow');
    }
    foreach(['GET','HEAD'] as $verb){
        $c=new LeadMethodsController($verb);$c->$endpoint(...$args);
        lmCheck($c->output->status===401&&in_array('WWW-Authenticate: Bearer',$c->output->headers,true)&&($verb!=='HEAD'||$c->output->body===''),$endpoint.' '.$verb.' requires bearer; HEAD body empty');
    }
}
foreach(['leads','lead_stages','lead_sources','lead_files','lead_imports','lead_inbox'] as $endpoint){
    $args=$endpoint==='lead_files'?['1']:[];
    $c=new LeadMethodsController('DELETE');$c->$endpoint(...$args);
    lmCheck($c->output->status===405&&in_array('Allow: GET, HEAD, POST',$c->output->headers,true)&&$c->input->headerReads===0,$endpoint.' mutation methods remain finite');
    $c=new LeadMethodsController('POST');$c->$endpoint(...$args);
    lmCheck($c->output->status===401,$endpoint.' POST is a supported authenticated method');
}
echo json_encode(['checks'=>$checks,'passed'=>$checks-count($failures),'acceptance'=>$failures?'FAIL':'PASS','failures'=>$failures],JSON_PRETTY_PRINT)."\n";
exit($failures?1:0);
