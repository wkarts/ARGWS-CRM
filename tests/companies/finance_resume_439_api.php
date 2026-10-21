<?php
/** Recreated after recovery: dedicated API delegates to canonical writers with immutable owner. */
register_shutdown_function(static function():void{if(isset($GLOBALS['db'],$GLOBALS['p'])){$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].'company_financial_api_requests');unset($GLOBALS['db']->data_cache['table_names']);}});
require __DIR__.'/api_tokens.php';
require_once APPPATH.'services/CompanyFinancialApiService.php';
if($db->driver()==='mysql')CompanyFinancialApiSchema::ensure($db,$p);else foreach(CompanyFinancialApiSchema::definitions() as $table=>$fields){$parts=[];foreach($fields as $name=>$type)$parts[]='`'.$name.'` '.$type.' NOT NULL';$parts[]='PRIMARY KEY(request_key)';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
unset($db->data_cache['table_names']);
$finance=new CompanyFinancialApiService($db,$p);$resumeKey=fn(string $tag)=>hash('sha256','resume439-api-'.$tag);$scopes=['payments:read','payments:create','payments:reverse','credits:read','credits:create','credits:apply','credits:unapply','credits:refund','credits:refund_reverse','credits:void'];
$issuedA=$tokens->issue($a,7,'FinancialA',$scopes,3,$resumeKey('tokenA'));$issuedB=$tokens->issue($b,7,'FinancialB',$scopes,3,$resumeKey('tokenB'));$bearerA=$issuedA['secret'];$bearerB=$issuedB['secret'];
$newInvoice=$s->create($a,7,array_replace($input,['client_id'=>$ca,'items'=>[['description'=>'API invoice','qty'=>'1.00','rate'=>'100.00']]]),$resumeKey('invoice'));
$manual=['amount'=>'15.00','date'=>'2026-10-20','reference'=>'resume439-api-cash-1','note'=>'Synthetic manual receipt'];
$receipt=$finance->recordManual($bearerA,$newInvoice,$manual,$resumeKey('manual'));$again=$finance->recordManual($bearerA,$newInvoice,$manual,$resumeKey('manual'));
essentialCheck($receipt['payment_id']===$again['payment_id']&&!$receipt['replayed']&&$again['replayed'],'API manual retry returns same canonical payment exactly once');
$positions=$finance->payments($bearerA,$newInvoice);essentialCheck($positions['position']['received']==='15.00'&&count($positions['items'])===1,'API payment reader reconciles canonical position');
essentialThrows(fn()=>$finance->recordManual($bearerB,$newInvoice,$manual,$resumeKey('cross')),'company_payment_access_denied','B bearer cannot post A invoice');
essentialThrows(fn()=>$finance->recordManual($bearerA,$newInvoice,array_replace($manual,['amount'=>15]),$resumeKey('typed')),'company_api_invalid_input','API forbids numeric coercion of money');
$creditInput=['client_id'=>$ca,'currency'=>1,'date'=>'2026-10-20','items'=>[['description'=>'API credit','qty'=>'1.00','rate'=>'20.00']],'note'=>'Synthetic credit'];
$newCredit=$finance->issueCredit($bearerA,$creditInput,$resumeKey('credit'));$creditAgain=$finance->issueCredit($bearerA,$creditInput,$resumeKey('credit'));essentialCheck($newCredit['credit_id']===$creditAgain['credit_id']&&$creditAgain['replayed'],'API credit issue uses durable native receipt');
$apply=['invoice_id'=>$newInvoice,'amount'=>'10.00','date'=>'2026-10-20','reference'=>'resume439-api-apply','reason'=>'Synthetic application'];$applied=$finance->moveCredit($bearerA,'apply',$newCredit['credit_id'],$apply,$resumeKey('apply'));$appliedAgain=$finance->moveCredit($bearerA,'apply',$newCredit['credit_id'],$apply,$resumeKey('apply'));
essentialCheck($applied['entry_key']===$appliedAgain['entry_key']&&$appliedAgain['replayed'],'API application reuses canonical immutable credit entry');
essentialCheck($finance->payments($bearerA,$newInvoice)['position']['applied_credit']==='10.00','API read includes exact credit application');
essentialThrows(fn()=>$finance->credit($bearerB,$newCredit['credit_id']),'company_credit_access_denied','B bearer cannot read A credit');
$unapply=['invoice_id'=>$newInvoice,'parent_key'=>$applied['entry_key'],'amount'=>'5.00','date'=>'2026-10-20','reference'=>'resume439-api-unapply','reason'=>'Synthetic correction'];$finance->moveCredit($bearerA,'unapply',$newCredit['credit_id'],$unapply,$resumeKey('unapply'));
essentialCheck($finance->payments($bearerA,$newInvoice)['position']['applied_credit']==='5.00','API unapply preserves canonical outstanding credit');
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$finance->recordManual($bearerA,$newInvoice,array_replace($manual,['reference'=>'resume439-audit-failure']),$resumeKey('audit')),'issuer_storage_failed','API audit failure rolls back canonical cash receipt');$db->failOn=null;
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$before,'API failure leaves original native payment count');
$readonly=$tokens->issue($a,7,'Read only',['payments:read'],1,$resumeKey('read-only'));essentialThrows(fn()=>$finance->recordManual($readonly['secret'],$newInvoice,$manual,$resumeKey('scope')),'company_api_scope_denied','read scope never expands to financial writes');
$tokens->revoke($a,7,$issuedA['token_id']);essentialThrows(fn()=>$finance->payments($bearerA,$newInvoice),'company_api_unauthenticated','revoked bearer cannot read cached finance');
essentialCheck($legacyBefore===$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array(),'API preserves NULL legacy invoice byte values');
echo 'Recreated439 financial API canonical writers: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No bank/refund transmission.'.PHP_EOL;
