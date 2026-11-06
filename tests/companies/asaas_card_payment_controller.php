<?php
/** Actual controller negative boundaries. No provider transport is configured or called. */
require __DIR__.'/asaas_card_settings.php';
$controller->input->verb='POST';
$post=['action'=>'card_pay','origin_id'=>'1','card_operation'=>'payWithCreditCard','card_token'=>'synthetic-controller-token','request_key'=>str_repeat('a',64),'confirm'=>'1'];
$controller->input->posted=$post;$controller->output->status=200;$run();
essentialCheck($controller->output->status===409,'direct payment requires preference CSRF');
$post['company_asaas_preferences_csrf']=$controller->session->data['company_asaas_preferences_csrf'];
$_SERVER['HTTPS']='off';$_SERVER['SERVER_PORT']='80';$_SERVER['HTTP_X_FORWARDED_PROTO']='https';
$controller->input->posted=$post;$controller->output->status=200;$run();
essentialCheck($controller->output->status===422,'untrusted forwarded header cannot authorize card over plain HTTP');
essentialCheck(!str_contains(json_encode($controller->load->view),$post['card_token']),'rejected token never returns in page DTO');
$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
foreach(['card_operation','card_token','request_key'] as $field){$bad=$post;$bad[$field]=['invalid'];$controller->input->posted=$bad;$controller->output->status=200;$run();essentialCheck($controller->output->status===422,'array-valued '.$field.' fails without provider access');}
$bad=$post;unset($bad['confirm']);$controller->input->posted=$bad;$controller->output->status=200;$run();essentialCheck($controller->output->status===422,'payment requires explicit immediate-charge confirmation');
$post['action']='card_query';unset($post['company_asaas_preferences_csrf']);$controller->input->posted=$post;$controller->output->status=200;$run();essentialCheck($controller->output->status===409,'query also requires scoped CSRF');
echo 'Direct card controller guards: '.$GLOBALS['essentialChecks'].' cumulative checks; no provider calls.'.PHP_EOL;
