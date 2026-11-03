<?php
require __DIR__.'/native_task_edit_form.php';
require __DIR__.'/native_task_ticket_support.php';
$ticketCanonical=NativeCompanyResourceId::encode('company_ticket',1);$query=['rel_type'=>'ticket','rel_id'=>$ticketCanonical];
$dto=$form->newForm($a,7,$query,'2026-10-25');essentialCheck($dto['native_task_form']['selected_relation']['id']===$ticketCanonical,'Original task modal has exact ticket source selection');
$controller->input->verb='GET';$controller->input->query=$query;$controller->task();$html=$controller->output->body;essentialCheck(str_contains($html,'id="_task_modal"')&&str_contains($html,$ticketCanonical),'Original controller and modal render the ticket-linked form, not a separate UI');
$ticketWire=$wire;unset($ticketWire['native_milestone_revision']);$ticketWire['rel_type']='ticket';$ticketWire['rel_id']=$ticketCanonical;$ticketWire['native_request_key']=$key('ticket-form-create');
$new=$form->save($a,7,null,$ticketWire);$identity=NativeCompanyResourceId::decode('task',$new);essentialCheck($identity['source_kind']==='company_standalone_task','Related task uses existing canonical storage, not third task domain');
$edited=$form->editForm($a,7,$new,'2026-10-25');essentialCheck($edited['task']->rel_type==='ticket'&&$edited['task']->rel_id===$ticketCanonical,'Existing original editor retains ticket relation identity');
$ticketWire['native_revision']=$edited['native_task_form']['fields']['native_revision'];$ticketWire['native_fields_revision']=$edited['native_task_form']['fields']['native_fields_revision'];$ticketWire['native_tags_revision']=$edited['native_task_form']['fields']['native_tags_revision'];$ticketWire['native_request_key']=$key('ticket-form-edit');unset($ticketWire['assignees']);$ticketWire['name']='Updated ticket-linked task';
essentialCheck($form->save($a,7,$new,$ticketWire)===$new,'Original edit commits same ticket-linked canonical task');
echo 'Original ticket-related task GET/modal/create/edit: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
// Child fixtures only need the committed state; discard diagnostic SQL history to keep the bounded PHP worker healthy.
$db->queries=[];
