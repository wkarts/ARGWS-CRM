<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Real TCPDF, with a test-only DB proxy simulating revocation/state change after the first committed read. */
require __DIR__.'/credits_reader.php';$start=$GLOBALS['essentialChecks'];
final class PdfBoundaryTestDb
{
 public int $commits=0;public $afterFirstCommit;
 public PDO $pdo;public PDO $conn_id;
 function __construct(private $inner){$this->pdo=$inner->pdo;$this->conn_id=$inner->pdo;}
 function __get($name){return $this->inner->{$name};}
 function __call($name,$args){return $this->inner->{$name}(...$args);}
 function trans_commit(){ $result=$this->inner->trans_commit();$this->commits++;if($this->commits===1)($this->afterFirstCommit)();return$result; }
}
foreach(['revocation','stale'] as $mode){$proxy=new PdfBoundaryTestDb($db);$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_credit_pdf_downloaded'")->row()->n;$proxy->afterFirstCommit=function()use($db,$p,$mode,$credit,$writer,$a){if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");else $writer->moveCompanyCredit($a,7,'refund',['credit_id'=>$credit,'amount'=>'1.00','date'=>'2026-10-19','reference'=>'pdf-render-boundary','reason'=>'Synthetic PDF boundary'],hash('sha256','pdf-render-boundary'));};
 try{essentialThrows(fn()=>(new CompanyCredits($proxy,$p))->pdf($a,7,$credit),$mode==='revocation'?'company_credit_access_denied':'company_credit_pdf_stale','post-read '.$mode.' denies before any PDF bytes are returned');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_credit_pdf_downloaded'")->row()->n===$before,'failed final confirmation does not record a completed download');}finally{$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}}
essentialCheck(str_starts_with($reader->pdf($a,7,$credit),'%PDF-'),'normal retry completes native PDF and final committed audit');
echo 'Credit PDF final authorization/state confirmation: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
