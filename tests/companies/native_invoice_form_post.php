<?php
require __DIR__.'/native_invoice_form_data.php';
require_once APPPATH.'services/NativeCompanyInvoiceFormInput.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommands.php';
NativeCompanyInvoiceCommandSchema::ensure($db,$p);
$selection=(new NativeCompanyInvoiceSequencePreferences($db,$p))->readForInvoiceCreation($a,12);
$wire=['_native_invoice_id'=>'','_native_invoice_revision'=>'','_native_invoice_request_key'=>hash('sha256','original-form-create'),'_native_invoice_number_revision'=>$selection['revision'],'number'=>str_pad($selection['next_number'],6,'0',STR_PAD_LEFT),'clientid'=>(string)$draftInput['client_id'],'currency'=>(string)$draftInput['currency'],'date'=>$draftInput['date'],'duedate'=>'','project_id'=>'','newitems'=>['7'=>['order'=>'1','description'=>'Actual native POST','long_description'=>'Preserved detail','qty'=>'1','rate'=>'100','unit'=>'hour','taxname'=>[]]],'discount_percent'=>'0','discount_total'=>'0.00','discount_type'=>'','adjustment'=>'','subtotal'=>'100.00','total'=>'100.00','tags'=>'Actual native form','clientnote'=>'Native client note','terms'=>'Native terms','adminnote'=>'Native admin note','show_quantity_as'=>'2','sale_agent'=>'','recurring'=>'0','cancel_overdue_reminders'=>'on','allowed_payment_modes'=>[]];
$form=new NativeCompanyInvoiceForm($db,$p);$receipt=$form->create($a,12,$wire);$id=$receipt['result']['invoice_id'];$record=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$id])->row_array();
essentialCheck($receipt['action']==='save'&&(int)$record['issuer_id']===$a&&$record['duedate']===null,'actual original wire creates issuer invoice and preserves empty due date');
essentialCheck($record['terms']==='Native terms'&&$record['adminnote']==='Native admin note'&&$record['clientnote']==='Native client note'&&(int)$record['show_quantity_as']===2,'original form fields persist instead of being ignored');
essentialCheck((int)$record['cancel_overdue_reminders']===1&&unserialize($record['allowed_payment_modes'])===[],'original checkbox and explicit empty payment permission persist');
$before=[];foreach(['invoices','issuers_sequences','issuers_audit','itemable']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();essentialCheck($form->create($a,12,$wire)===$receipt,'same original POST replays complete command');foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'original POST replay leaves '.$table.' byte-equivalent');
$bad=$wire;$bad['total']='99.99';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_amount_mismatch','browser supplied total is recomputed and mismatch denied');
$bad=$wire;$bad['issuer_id']=$b;essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_invalid','wire cannot select another issuer');
$bad=$wire;$bad['newitems']['7']['order']='2';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_invalid','missing or ambiguous native order is denied');
$bad=$wire;$bad['cancel_overdue_reminders']='0';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_invalid','unchecked checkbox cannot be forged as contradictory scalar');
foreach(['save_and_send','save_and_send_later']as$action){$bad=$wire;$bad[$action]='true';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_mail_required','unavailable mail composition is never silently downgraded to save');}
$bad=$wire;$bad['recurring']='1';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_recurring_required','recurrence is never silently discarded');
$bad=$wire;$bad['task_id']='123';essentialThrows(fn()=>$form->create($a,12,$bad),'native_invoice_form_source_required','task billing is not silently made manual');
$draftWire=$wire;$draftWire['_native_invoice_request_key']=hash('sha256','original-form-draft');$draftWire['save_as_draft']='true';$draft=$form->create($a,12,$draftWire);essentialCheck($draft['action']==='save_as_draft'&&isset($draft['result']['canonical_id']),'actual original save-as-draft reaches durable draft command');
foreach(['invoices','issuers_sequences']as$table)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$before[$table],'native draft does not issue or reserve '.$table);
echo 'Original invoice form POST actual writer: '.$GLOBALS['essentialChecks']." cumulative PASS; HTTP boundary/browser pending.\n";
