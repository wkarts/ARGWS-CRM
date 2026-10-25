<?php
require __DIR__.'/native_invoice_table_controller.php';
$ci->input->verb='POST';CompanyRequestScope::$issuer=$a;$ci->output->status=200;
$cancelInput=$legacyInput;$cancelInput['client_id']=$ca;$fresh=$s->create($a,7,$cancelInput,hash('sha256','native-cancel-controller-issued'));
$ci->input->posts=['revision'=>$s->revision($a,7,$fresh)];$controller->mark_as_cancelled($fresh);
essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)['success']===true&&$s->document($a,7,$fresh)->data()['status']===5,'same native cancel action composes actual guarded writer and journal');
CompanyRequestScope::$issuer=$b;$controller->mark_as_cancelled($fresh);essentialCheck($ci->output->status===403,'cross-company native cancel denied despite same master actor');CompanyRequestScope::$issuer=$a;
$ci->input->verb='GET';$controller->mark_as_cancelled($fresh);essentialCheck($ci->output->status===403,'GET cannot cancel through existing native URL');$ci->input->verb='POST';
$ci->input->posts=['revision'=>$s->revision($a,7,$main)];$controller->mark_as_cancelled($main);essentialCheck($ci->output->status===403&&$s->document($a,7,$main)->data()['status']!==5,'native action cannot bypass canonical paid/provider/receipt vetoes');
$private=(new CompanyInvoiceDrafts($db,$p))->create($a,7,$cancelInput,hash('sha256','native-cancel-controller-draft'));
$encoded=NativeCompanyResourceId::encode('company_invoice_draft',$private);$ci->input->posts=['revision'=>1,'request_key'=>hash('sha256','native-cancel-controller-private-action')];$ci->output->status=200;$controller->mark_as_cancelled($encoded);
$draft=(new CompanyInvoiceDrafts($db,$p))->document($a,7,$private);essentialCheck($ci->output->status===200&&$draft['state']==='cancelled'&&$draft['invoice_id']===null,'private cancel retains history and never emits or aliases raw invoice ID');
echo 'Native cancellation actual writer/controller boundary: '.$GLOBALS['essentialChecks']." cumulative PASS; historical NULL adapter remains pending.\n";
