<?php defined('BASEPATH') or exit('No direct script access allowed');
$paymentContext=CompanyRequestScope::context();
$paymentAcl=new IssuerResourceAccess($this->db,db_prefix());
$paymentEntries=[
    ['name'=>'Asaas','module'=>'asaas','route'=>'settings?group=payment_gateways&gateway=asaas','feature'=>'settings','description'=>'Configuração, webhook, operações, transações e conciliação.'],
    ['name'=>'Efí','module'=>'gerencianet_gateway','route'=>'settings/efi','feature'=>'settings','description'=>'Contas e credenciais de cobrança da empresa.'],
    ['name'=>'Banco do Brasil — API e CNAB','module'=>'banco_brasil','route'=>'settings?group=payment_gateways&gateway=banco_brasil','feature'=>'banco_brasil','description'=>'Configuração de contas, convênios e perfis API/CNAB 240/400.'],
];
$paymentModules=[];
foreach(IssuerStorage::query($this->db,'SELECT module_name FROM '.db_prefix().'modules WHERE active=1')->result_array() as $paymentModule)$paymentModules[$paymentModule['module_name']]=true;
?>
<p>Configure os meios de pagamento da empresa selecionada.</p>
<ul class="nav nav-tabs" aria-label="Meios de pagamento">
<?php foreach($paymentEntries as $paymentEntry){
    if(!isset($paymentModules[$paymentEntry['module']])||!$paymentAcl->can($paymentContext->issuerId(),$paymentContext->staffId(),$paymentEntry['feature'],'view'))continue;
?>
<li><a href="<?= e(admin_url($paymentEntry['route'])); ?>" title="<?= e($paymentEntry['description']); ?>"><?= e($paymentEntry['name']); ?></a></li>
<?php } ?>
</ul>
<?php if($paymentAcl->can($paymentContext->issuerId(),$paymentContext->staffId(),'payment-modes','view')){ ?>
<p><a class="btn btn-default" href="<?= e(admin_url('paymentmodes')); ?>">Meios de pagamento manuais</a></p>
<?php } unset($paymentEntries,$paymentModules,$paymentContext,$paymentAcl); ?>
