<?php
/** Actual controller with synthetic dependencies; never provider/DB/network. */
require __DIR__.'/pige_bridge_controller.php';
$event=['schema_version'=>1,'event_id'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','event_type'=>'connection.test','scope'=>$scope,'data'=>['nonce'=>'cccccccc-cccc-4ccc-8ccc-cccccccccccc']];
$body=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$c->uri->path='pige_bridge/api/v1/diagnostics';$c->input->raw_input_stream=$body;$c->input->verb='POST';
$timestamp=(string)time();$c->input->headers=['X-Pige-Timestamp'=>$timestamp,'X-Pige-Event-Id'=>$event['event_id'],'X-Pige-Signature'=>PigeBridgeProtocol::signature($secret,$timestamp,$event['event_id'],'POST',PigeBridgeProtocol::DIAGNOSTIC_PATH,$body)];
$before=$c->pige_bridge_model->calls;$loads=$c->load->calls;$c->diagnostics();
check($c->output->status===200&&$c->output->body['nonce']===$event['data']['nonce'],'authenticated diagnostic');
check($c->pige_bridge_model->calls===$before&&$c->load->calls===$loads,'diagnostic cannot persist or dispatch');
check($c->output->body['capabilities']===['create_invoice'=>false,'issue_charge'=>false],'unimplemented financial capabilities stay closed');
$c->input->headers['X-Pige-Signature']=PigeBridgeProtocol::signature($secret,$timestamp,$event['event_id'],'POST',PigeBridgeProtocol::REQUEST_PATH,$body);$c->diagnostics();check($c->output->status===401,'request signature cannot authorize diagnostic');
$c->uri->path='pige_bridge/bridge/diagnostics';$c->diagnostics();check($c->output->status===404,'diagnostic alias rejected');
$c->uri->path='pige_bridge/api/v1/diagnostics';$c->input->verb='GET';$c->diagnostics();check($c->output->status===405,'diagnostic never GET');
$event['data']['issuer_id']='8';$c->input->verb='POST';$c->input->raw_input_stream=json_encode($event);$c->diagnostics();check($c->output->status===400,'scope override rejected');
echo "PIGE diagnostic actual controller: synthetic checks passed; no financial effects.\n";
