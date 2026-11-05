<?php defined('BASEPATH') or exit('No direct script access allowed');
$paymentContext=CompanyRequestScope::context();
$paymentAcl=new IssuerResourceAccess($this->db,db_prefix());
$paymentEntries=[
    ['name'=>'Efí','module'=>'gerencianet_gateway','route'=>'settings/efi','feature'=>'settings','description'=>'Contas e credenciais de cobrança da empresa.'],
    ['name'=>'Banco do Brasil — API e CNAB','module'=>'banco_brasil','route'=>'banco_brasil','feature'=>'banco_brasil','description'=>'API de cobrança, remessa, retorno e conciliação CNAB 240/400.'],
];
$paymentModules=[];
foreach(IssuerStorage::query($this->db,'SELECT module_name FROM '.db_prefix().'modules WHERE active=1')->result_array() as $paymentModule)$paymentModules[$paymentModule['module_name']]=true;
?>
<p>Configure os meios de pagamento da empresa selecionada.</p>
<div class="row">
<?php foreach($paymentEntries as $paymentEntry){
    if(!isset($paymentModules[$paymentEntry['module']])||!$paymentAcl->can($paymentContext->issuerId(),$paymentContext->staffId(),$paymentEntry['feature'],'view'))continue;
?>
<div class="col-sm-6 col-lg-4"><div class="panel_s"><div class="panel-body">
    <h4><?= e($paymentEntry['name']); ?></h4><p><?= e($paymentEntry['description']); ?></p>
    <a class="btn btn-default" href="<?= e(admin_url($paymentEntry['route'])); ?>">Abrir configurações</a>
</div></div></div>
<?php } ?>
</div>
<?php if($paymentAcl->can($paymentContext->issuerId(),$paymentContext->staffId(),'payment-modes','view')){ ?>
<p><a class="btn btn-default" href="<?= e(admin_url('paymentmodes')); ?>">Meios de pagamento manuais</a></p>
<?php } unset($paymentEntries,$paymentModules,$paymentContext,$paymentAcl); ?>
