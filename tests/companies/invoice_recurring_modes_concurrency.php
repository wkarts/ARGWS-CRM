<?php
require_once dirname(__DIR__).'/issuers_core_support.php';if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function irmConnection(string$p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
/** Test-only canonical-boundary gate. No callback is injected into production writers. */
final class RecurringModeGatedDb
{
    public PDO$pdo;public bool$db_debug=true;private bool$committed=false,$gated=false;
    public function __construct(private EssentialTestDb$inner,private string$p,private string$gate){$this->pdo=$inner->pdo;}
    public function __call($m,$args){return$this->inner->$m(...$args);}
    public function trans_commit():bool{$ok=$this->inner->trans_commit();if($ok)$this->committed=true;return$ok;}
    public function query($sql,$args=[]){if($sql==='SELECT id FROM '.$this->p.'issuers_lock WHERE id=1 FOR UPDATE'&&$this->committed&&!$this->gated){$this->gated=true;file_put_contents($this->gate.'.ready','ready');$deadline=microtime(true)+20;while(!is_file($this->gate.'.release')){if(microtime(true)>$deadline)throw new RuntimeException('test_gate_expired');usleep(20000);}}return$this->inner->query($sql,$args);}
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$inner=irmConnection($p);$db=isset($in['gate'])?new RecurringModeGatedDb($inner,$p,$in['gate']):$inner;$GLOBALS['CI']=(object)['db'=>$db];require_once APPPATH.'services/CompanyInvoiceRecurring.php';
    try{$ir=new CompanyInvoiceRecurring($db,$p);$value=$in['action']==='create'?$ir->create($in['issuer'],7,$in['input'],$in['config'],$in['key']):$ir->run($in['issuer'],7,$in['schedule'],'2026-01-01');echo json_encode($value,JSON_THROW_ON_ERROR);}catch(DomainException$e){if(!in_array($e->getMessage(),['company_invoice_draft_denied','company_sales_denied','company_invoice_balance_review','company_invoice_recurring_principal_unpaid'],true))throw$e;echo json_encode($e->getMessage());}exit(0);
}
require __DIR__.'/invoice_recurring_modes.php';$observer=irmConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same_create','same_draft','late_actor','late_principal']as$scenario){
    $payload=array_replace($baseInput,['note'=>'Race mode '.$scenario]);$config=array_replace($draftSpec,['cycles'=>1]);$key=$mk('race-'.$scenario);$parent=null;
    if($scenario==='late_principal'){$parent=$s->create($a,7,$payload,$mk('race-parent'));$sum=(string)$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$parent])->row()->total;$writer->recordCompanyManual($a,7,$parent,['amount'=>$sum,'date'=>'2026-10-19','reference'=>'race-parent','note'=>'Synthetic'],$mk('race-parent-pay'));$config['principal_invoice_id']=$parent;}
    $schedule=$scenario==='same_create'?null:$ir->create($a,7,$payload,$config,$key);$beforeDrafts=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;$beforeInvoices=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$workers=[];$gates=[];$late=str_starts_with($scenario,'late_');
    if(!$late){$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');}
    try{
        for($n=0;$n<2;$n++){$input=['action'=>$scenario==='same_create'?'create':'run','issuer'=>$a,'input'=>$payload,'config'=>$config,'key'=>$key,'schedule'=>$schedule];if($late){$gate=tempnam(sys_get_temp_dir(),'recurring-mode-gate-');$gates[]=$gate;$input['gate']=$gate;}$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        if($late){$deadline=microtime(true)+20;do{$ready=count(array_filter($gates,fn($g)=>is_file($g.'.ready')));if($ready===2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($ready===2,'two workers committed durable occurrence before canonical draft boundary: '.$scenario);$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');foreach($gates as$g)file_put_contents($g.'.release','release');}
        $deadline=microtime(true)+20;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two real InnoDB waiters observed at owned mode boundary: '.$scenario);
        if($scenario==='late_actor')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
        if($scenario==='late_principal')$db->query('UPDATE '.$p.'invoices SET status=1 WHERE id=?',[$parent]); // Deliberate invalid current principal, not a financial reversal claim.
        $db->trans_commit();$results=[];
        foreach($workers as[$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$stderr==='','contender terminates without unexpected diagnostics: '.$scenario);$results[]=json_decode($stdout,true,32,JSON_THROW_ON_ERROR);}
        if($scenario==='same_create')essentialCheck(is_int($results[0])&&$results[0]===$results[1]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_schedules WHERE request_key=?',[$key])->row()->n===1,'concurrent mode creators freeze one schedule and one immutable mode');
        elseif($scenario==='same_draft'){$ids=array_values(array_filter($results,'is_int'));essentialCheck($ids&&count(array_unique($ids))===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$beforeDrafts+1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_schedule_drafts WHERE schedule_id=?',[$schedule])->row()->n===1,'two processors create and acknowledge exactly one real private draft');}
        else{$expected=$scenario==='late_actor'?'company_invoice_draft_denied':'company_invoice_balance_review';essentialCheck($results===[$expected,$expected]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$beforeDrafts,'both actual canonical draft writers veto changed actor/principal after durable claim: '.$scenario);}
        essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeInvoices,'no concurrent private mode consumes native invoice/number: '.$scenario);
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');if($parent!==null)$db->query('UPDATE '.$p.'invoices SET status=2 WHERE id=?',[$parent]);foreach($gates as$g)foreach([$g,$g.'.ready',$g.'.release']as$f)if(is_file($f))unlink($f);}
    if($late)essentialCheck($ir->run($a,7,$schedule,'2026-01-01')>0,'legitimate restored actor/principal resumes the original claimed occurrence: '.$scenario);
}
echo 'Invoice recurring mode real two-process claims/drafts/late actor/principal vetoes: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'). No providers.'.PHP_EOL;
