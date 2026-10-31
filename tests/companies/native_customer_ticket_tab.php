<?php
/** Original customer tab ACL and official ticket table shape; reader tested separately. */
require __DIR__.'/native_customer_views.php';
require APPPATH.'helpers/tickets_helper.php';
CompanyRequestScope::$issuer=41;$controller->input->method='GET';$controller->input->get=['group'=>'tickets'];
try{$controller->client((string)$nativeA);throw new RuntimeException('ticket group accepted without permission');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'ticket tab direct access requires ticket authority');}
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'tickets','view')");$controller->input->get=[];$controller->client((string)$nativeA);$view=end($controller->load->views)[1];
essentialCheck(($view['customer_tabs']['tickets']['view']??null)==='admin/clients/groups/tickets','ticket view grant exposes original customer group');
$oldCi=$GLOBALS['CI'];$GLOBALS['CI']=$controller;
try{$html=$renderer->render('admin/clients/groups/tickets',['client'=>$view['client'],'native_customers'=>true,'native_ticket_structure'=>['can_edit'=>false,'services'=>'0','order'=>'','hidden_columns'=>'','custom_fields'=>[]]]);}finally{$GLOBALS['CI']=$oldCi;}
essentialCheck(str_contains($html,'table-tickets-single')&&str_contains($html,'ticket_created_column')&&!str_contains($html,'tickets/add'),'native customer tab renders the original ticket table without a non-admitted create action');
CompanyRequestScope::$issuer=82;$controller->input->get=['group'=>'tickets'];
try{$controller->client((string)$nativeB);throw new RuntimeException('foreign ticket permission accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'ticket authority does not transfer between issuers');}
echo "Original customer ticket tab scoped permission and native table passed.\n";
