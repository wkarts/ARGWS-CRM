<?php
/** Actual JSON/TCPDF API output; late mutations occur in the committed authorization audit. */
require __DIR__.'/api_tokens.php';
$legacyOutputBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array();
$requestsOutputBefore=$db->query("SELECT * FROM {$p}company_invoice_requests ORDER BY request_key")->result_array();
$receiptsOutputBefore=$db->query("SELECT * FROM {$p}company_payment_receipts ORDER BY request_key")->result_array();
// Give this non-master only the native API/read grants; the token itself remains immutable.
foreach([['company_api','manage'],['invoices','view']] as [$feature,$cap])$db->query("INSERT IGNORE INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)",[$a,$feature,$cap]);
$modeOutput=getenv('API_OUTPUT_GUARD_MODE');$kindsOutput=in_array($modeOutput,['pdf','json'],true)?[$modeOutput]:['pdf','json'];
foreach($kindsOutput as $kindOutput){
    $scopeOutput=$kindOutput==='pdf'?'invoices:pdf':'invoices:read';$actionOutput=$kindOutput==='pdf'?'invoice_pdf':'invoice_read';
    foreach(['token','actor','origin'] as $mutationOutput){
        $actorOutput=$mutationOutput==='actor'?12:7;
        $freshOutput=$tokens->issue($a,$actorOutput,'Output guard '.$kindOutput.' '.$mutationOutput,[$scopeOutput],1,$ck('output-guard-'.$kindOutput.'-'.$mutationOutput));$tokenOutput=$freshOutput['token_id'];
        $changeOutput=match($mutationOutput){'token'=>"UPDATE {$p}company_api_tokens SET active=0 WHERE token_id='{$tokenOutput}'",'actor'=>"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id={$a} AND staff_id=12 AND feature='invoices' AND capability='view'",'origin'=>"UPDATE {$p}invoices SET issuer_id={$b} WHERE id={$invoice}"};
        $conditionOutput="NEW.event='company_api_request_authorized' AND NEW.metadata_json LIKE '%\"token_id\":\"{$tokenOutput}\"%' AND NEW.metadata_json LIKE '%\"action\":\"{$actionOutput}\"%'";
        $triggerOutput=$p.'api_output_guard';
        $auditOutputBefore=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized' AND metadata_json LIKE ?",['%"token_id":"'.$tokenOutput.'"%'])->row()->n;
        $db->query($db->driver()==='mysql'?"CREATE TRIGGER {$triggerOutput} AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF {$conditionOutput} THEN {$changeOutput}; END IF; END":"CREATE TRIGGER {$triggerOutput} AFTER INSERT ON {$p}issuers_audit WHEN {$conditionOutput} BEGIN {$changeOutput}; END");
        try{
            $failureOutput=null;$resultOutput=null;try{$resultOutput=$kindOutput==='pdf'?$api->invoicePdf($freshOutput['secret'],$invoice):$api->invoice($freshOutput['secret'],$invoice);}catch(Throwable $errorOutput){$failureOutput=$errorOutput->getMessage();}
            $expectedOutput=match($mutationOutput){'token'=>'company_api_unauthenticated','actor'=>'company_api_scope_denied','origin'=>'issuer_document_scope_mismatch'};
            essentialCheck($failureOutput===$expectedOutput&&$resultOutput===null,$kindOutput.' releases no document/bytes after late '.$mutationOutput.' revocation (actual audit trigger)');
            $auditOutputAfter=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized' AND metadata_json LIKE ?",['%"token_id":"'.$tokenOutput.'"%'])->row()->n;
            $usedOutput=$db->query("SELECT last_used_at FROM {$p}company_api_tokens WHERE token_id=?",[$tokenOutput])->row_array();
            essentialCheck($auditOutputAfter===$auditOutputBefore+1&&$usedOutput['last_used_at']!==null,'late '.$mutationOutput.' output denial preserves exactly one committed authorization/last-used receipt');
            essentialCheck(!$db->pdo->inTransaction(),'late output denial does not leak or join a transaction');
        }finally{
            $db->query('DROP TRIGGER '.$triggerOutput);$db->query("UPDATE {$p}company_api_tokens SET active=0 WHERE token_id=?",[$tokenOutput]);$db->query("INSERT IGNORE INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'invoices','view')",[$a]);$db->query("UPDATE {$p}invoices SET issuer_id=? WHERE id=?",[$a,$invoice]);
        }
    }
}
// Fault injection at the first DB access in invoicePdf after invoiceData has returned.
// The renderer remains the actual final Issuer_invoice_pdf/TCPDF, which performs no DB I/O.
$afterRenderOutput=$tokens->issue($a,7,'Post-render revocation',['invoices:pdf'],1,$ck('after-render-output'));
$afterRenderDb=new class($db,$p,$afterRenderOutput['token_id']) {
    public PDO $pdo;public bool $fired=false;public bool $armed=false;
    public function __construct(private $inner,private string $prefix,private string $token){$this->pdo=$inner->pdo;}
    public function &__get($name){return $this->inner->$name;}
    public function __isset($name):bool{return isset($this->inner->$name);}
    public function __call($name,$args){return $this->inner->$name(...$args);}
    public function query($sql,$args=[]){
        if($this->armed&&!$this->fired){$frames=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);$inPdf=false;$building=false;foreach($frames as $frame){if(($frame['class']??'')==='CompanyApiService'){if($frame['function']==='invoicePdf')$inPdf=true;if($frame['function']==='invoiceData')$building=true;}}if($inPdf&&!$building){$this->fired=true;$this->inner->query('UPDATE '.$this->prefix.'company_api_tokens SET active=0 WHERE token_id=?',[$this->token]);}}
        return $this->inner->query($sql,$args);
    }
};
$afterRenderApi=new CompanyApiService($afterRenderDb,$p);$afterRenderDb->armed=true;
essentialThrows(fn()=>$afterRenderApi->invoicePdf($afterRenderOutput['secret'],$invoice),'company_api_unauthenticated','token revoked after actual TCPDF rendering is denied before bytes return');
essentialCheck($afterRenderDb->fired&&!$db->pdo->inTransaction(),'post-render injection reached final release confirmation and leaked no transaction');
$afterRenderAudits=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized' AND metadata_json LIKE ?",['%"token_id":"'.$afterRenderOutput['token_id'].'"%'])->row()->n;
essentialCheck($afterRenderAudits===1,'post-render veto preserves a single committed authorization receipt');
$positiveOutput=$tokens->issue($a,7,'Positive output regression',['invoices:read','invoices:pdf'],1,$ck('positive-output'));
$auditsPositiveBefore=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized' AND metadata_json LIKE ?",['%"token_id":"'.$positiveOutput['token_id'].'"%'])->row()->n;
$jsonOutput=$api->invoice($positiveOutput['secret'],$invoice);$pdfOutput=$api->invoicePdf($positiveOutput['secret'],$invoice);
essentialCheck($jsonOutput['issuer_id']===$a&&$jsonOutput['document']['invoice_id']===$invoice,'positive JSON GET returns its canonical private document');
essentialCheck(str_starts_with($pdfOutput,'%PDF-')&&strlen($pdfOutput)>1000,'positive PDF uses actual TCPDF and releases valid bytes after final confirmation');
$api->invoice($positiveOutput['secret'],$invoice);$api->invoicePdf($positiveOutput['secret'],$invoice);
$auditsPositiveAfter=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized' AND metadata_json LIKE ?",['%"token_id":"'.$positiveOutput['token_id'].'"%'])->row()->n;
essentialCheck($auditsPositiveAfter===$auditsPositiveBefore+4,'JSON/PDF retry records exactly one authorization per call; final confirmation is read-only');
essentialThrows(fn()=>$api->invoice($positiveOutput['secret'],$ib),'issuer_document_scope_mismatch','final confirmation retains A/B private ownership');
essentialThrows(fn()=>$api->invoicePdf($positiveOutput['secret'],42),'issuer_document_scope_mismatch','NULL legacy cannot acquire a company through positive PDF API');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyOutputBefore&&$db->query("SELECT * FROM {$p}company_invoice_requests ORDER BY request_key")->result_array()===$requestsOutputBefore&&$db->query("SELECT * FROM {$p}company_payment_receipts ORDER BY request_key")->result_array()===$receiptsOutputBefore,'output checks preserve NULL history and canonical invoice/payment receipts');
echo 'API invoice final output guard: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'); actual TCPDF, no external calls.'.PHP_EOL;
