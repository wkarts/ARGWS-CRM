<?php
require __DIR__.'/native_invoice_issue_commands.php';
require_once APPPATH.'services/NativeCompanyInvoiceSequencePreferences.php';
$sequence=new NativeCompanyInvoiceSequencePreferences($db,$p);$selection=$sequence->readForInvoiceCreation($a,12);$form=$metadata+['number_selection'=>$selection];$key=hash('sha256','native-form-number-transaction-cas');
$issued=$commands->createIssued($a,12,$draftInput,$form,$key);$row=$db->query('SELECT number FROM '.$p.'invoices WHERE id=?',[$issued['invoice_id']])->row_array();essentialCheck((string)$row['number']===$selection['next_number'],'full form emits exactly the number displayed and authorized under the same transaction');
$before=[];foreach(['invoices','issuers_sequences','issuers_audit','company_invoice_native_commands']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
essentialCheck($commands->createIssued($a,12,$draftInput,$form,$key)===$issued,'exact form retry replays receipt despite allocator advancement');
essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$form,hash('sha256','stale-form-number-tab')),'native_invoice_sequence_stale','another stale form cannot silently receive a different invoice number');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'stale number/replay leaves '.$table.' unchanged');
$new=$sequence->readForInvoiceCreation($a,12);$changed=$metadata+['number_selection'=>$new];essentialThrows(fn()=>$commands->createIssued($a,12,$draftInput,$changed,$key),'native_invoice_command_conflict','same request cannot silently change its displayed number selection');
essentialThrows(fn()=>$sequence->readForInvoiceCreationInOwned($a,12),'issuer_owned_transaction_required','owned sequence reader requires actual canonical transaction');
echo 'Native full form number CAS: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
