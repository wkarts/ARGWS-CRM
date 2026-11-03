<?php
require __DIR__.'/native_invoice_fields.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
$agentInput=$nativeInput;$agentInput['native_fields']['sale_agent']=7;$agentInvoice=$s->create($a,7,$agentInput,hash('sha256','native-agent-issued'));
essentialCheck((int)$db->query('SELECT sale_agent FROM '.$p.'invoices WHERE id=?',[$agentInvoice])->row_array()['sale_agent']===7,'canonical issue persists actual eligible sales agent');
$invalid=$agentInput;$invalid['native_fields']['sale_agent']=999999;
essentialThrows(fn()=>$s->create($a,7,$invalid,hash('sha256','native-agent-unknown')),'native_invoice_agent_denied','unknown agent cannot enter issuer invoice');
$agentDraft=$drafts->create($a,7,$agentInput,hash('sha256','native-agent-private'));$draft=$drafts->document($a,7,$agentDraft);
essentialCheck($draft['payload']['native_fields']['sale_agent']===7,'private draft retains assigned agent inside canonical payload hash');
$projection=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>(new NativeCompanyInvoiceDraftProjection($db,$p))->assertInOwned($agentDraft));essentialCheck($projection['sale_agent']===7,'draft table projection uses real selected agent, not invented zero');
$unassigned=$agentInput;$unassigned['native_fields']['sale_agent']=0;$revision=$s->revision($a,7,$agentInvoice);$s->change($a,7,$agentInvoice,$revision,$unassigned);
essentialCheck((int)$db->query('SELECT sale_agent FROM '.$p.'invoices WHERE id=?',[$agentInvoice])->row_array()['sale_agent']===0,'explicit native unassignment persists zero without arbitrary actor');
$before=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$agentInvoice])->row_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';try{essentialThrows(fn()=>$s->change($a,7,$agentInvoice,$s->revision($a,7,$agentInvoice),$agentInput),'issuer_storage_failed','late audit failure rolls back agent assignment');}finally{$db->failOn=null;}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$agentInvoice])->row_array()===$before,'assignment failure leaves entire invoice unchanged');
echo 'Native agent issued/draft/editor writes: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";

require_once APPPATH.'services/NativeCompanyInvoiceTableFactory.php';if(!function_exists('_l')){function _l($s){return$s;}}
$catalogue=(new NativeCompanyInvoiceTableFactory($db,$p))->data($a,7);essentialCheck(in_array('7',array_column($catalogue['options']['sale_agent'],'value'),true),'filter catalogue includes real assignment in a private draft without exposing unrelated staff');
echo "Private draft sale-agent filter choices PASS.\n";
