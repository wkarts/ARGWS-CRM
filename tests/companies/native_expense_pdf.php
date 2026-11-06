<?php
/** Actual controller, references, SQL, TCPDF and Poppler. Framework/session are the parent's explicit spies. */
ob_start();require __DIR__.'/native_expense_index_http.php';$fixtureOutput=ob_get_clean();
$start=$GLOBALS['essentialChecks'];
$pdfText=static function(string $bytes):string{
    $process=proc_open(['pdftotext','-','-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Poppler is required for actual PDF assertions');
    fwrite($pipes[0],$bytes);fclose($pipes[0]);$text=stream_get_contents($pipes[1]);fclose($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('PDF text extraction failed: '.$errors);return$text;
};
$snapshot=static function()use($db,$p):array{$rows=[];foreach(['company_expenses','company_expense_requests','company_financial_events','issuers_audit','expenses']as$table)$rows[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$rows;};
$before=$snapshot();CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;
$reset();$controller->pdf($saved['expenseid']);
essentialCheck($out->status===200&&$out->mime==='application/pdf'&&str_starts_with($out->body,'%PDF-'),'Actual expense PDF accepts the original typed link and renders real TCPDF');
$text=$pdfText($out->body);
essentialCheck(str_contains($text,'Total: BRL 0.06')&&str_contains($text,'Private A')&&!str_contains($text,'Foreign hidden expense'),'Extracted real PDF has the authorized recipient and exact two-tax total');
essentialCheck(in_array('Cache-Control: private, no-store',$out->headers,true)&&in_array('X-Content-Type-Options: nosniff',$out->headers,true),'Download sends actual no-store/nosniff HTTP headers');
CompanyRequestScope::$actor=20;$reset();$controller->pdf($saved['expenseid']);$text=$pdfText($out->body);
essentialCheck($out->status===200&&!str_contains($text,'Private A')&&str_contains($text,'Total: BRL 0.06'),'Expense reader without customer grants gets the expense PDF without private recipient fields');CompanyRequestScope::$actor=7;
CompanyRequestScope::$issuer=$b;$reset();$controller->pdf($saved['expenseid']);essentialCheck($out->status===403&&!str_starts_with($out->body,'%PDF-'),'Retained typed PDF link is denied after company switch');CompanyRequestScope::$issuer=$a;
$reset();$controller->pdf((string)$saved['source_id']);essentialCheck($out->status===403&&!str_starts_with($out->body,'%PDF-'),'A raw historical ID is never reinterpreted as a same-number current expense');
$reset();$controller->pdf(NativeCompanyResourceId::encode('company_project',1));essentialCheck($out->status===422&&!str_starts_with($out->body,'%PDF-'),'Invalid typed source cannot hydrate an expense PDF');
$reset('HEAD');$controller->pdf($saved['expenseid']);essentialCheck($out->status===200&&$out->mime==='application/pdf'&&$out->body==='','HEAD proves authority and returns no PDF bytes');
$reset('POST',['_native_company_csrf'=>'synthetic-native-token']);$controller->pdf($saved['expenseid']);essentialCheck($out->status===405&&in_array('Allow: GET, HEAD',$out->headers,true),'Download rejects a state-changing verb even with a valid CSRF token');
CompanyRequestScope::$sealed=false;$reset();$controller->pdf($saved['expenseid']);essentialCheck($out->status===403&&$out->body==='','An unsealed request cannot emit a financial PDF');CompanyRequestScope::$sealed=true;

// Forward every actual DB call; inject a context/ACL change only after the final source SELECT.
$proxy=new class($db){
    public $pdo;public array $data_cache;public bool $db_debug=false;public $observer=null;
    public function __construct(private $inner){$this->pdo=$inner->pdo;$this->data_cache=&$inner->data_cache;}
    public function query($sql,$params=[]){$result=$this->inner->query($sql,$params);if($this->observer!==null)($this->observer)($sql,$params);return$result;}
    public function __call($name,$args){return$this->inner->$name(...$args);}
    public function __get($name){return$this->inner->$name;}
};
$GLOBALS['CI']->db=$proxy;$reads=0;
$proxy->observer=static function($sql)use(&$reads,$p,$b){if(str_contains($sql,'FROM '.$p.'company_expenses WHERE id=? AND issuer_id=?')&&++$reads===2)CompanyRequestScope::$issuer=$b;};
$reset();$controller->pdf($saved['expenseid']);essentialCheck($reads===2&&$out->status===403&&$out->body==='','Company switch during final source revalidation discards the generated PDF bytes');CompanyRequestScope::$issuer=$a;
$reads=0;CompanyRequestScope::$actor=20;$proxy->observer=static function($sql)use(&$reads,$db,$p,$a){if(str_contains($sql,'FROM '.$p.'company_expenses WHERE id=? AND issuer_id=?')&&++$reads===2)$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=20 AND feature='expenses' AND capability='view'",[$a]);};
$reset();$controller->pdf($saved['expenseid']);essentialCheck($reads===2&&$out->status===403&&$out->body==='','Real SQL permission revocation before emission discards buffered PDF bytes');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,20,'expenses','view')",[$a]);$GLOBALS['CI']->db=$db;CompanyRequestScope::$actor=7;
essentialCheck($snapshot()===$before,'PDF, HEAD and denied accesses leave financial/legacy rows, receipts and audit unchanged');
echo$fixtureOutput;echo'Native expense PDF: '.($GLOBALS['essentialChecks']-$start).' added actual controller/PDF/ACL checks passed ('.$db->driver().').'.PHP_EOL;
