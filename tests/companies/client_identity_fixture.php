<?php
define('BASEPATH',__DIR__);require __DIR__.'/../../application/services/CompanyClientProfile.php';
class CompanyRequestScope{static function token(){return 'synthetic-context';}static function adminUrl($ci,$path){return 'https://fixture.test/admin/'.$path;}}
function base_url($path){return 'https://fixture.test/'.$path;}
$mode=$argv[1]??'owned';
$view=new class{function render($mode){
    $clientMode=$mode;$clientPrefix=$mode==='legacy'?'brazil':'edit';$clientData=['userid'=>42,'company'=>'Nome existente','vat'=>'11222333000181'];$clientProfile=[];$company_clients_csrf=str_repeat('a',64);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="https://fixture.test/assets/plugins/bootstrap/css/bootstrap.min.css"></head><body><main style="padding:24px;max-width:1000px;margin:auto"><form><input type="hidden" name="native_csrf" value="synthetic-token">';
    if($mode==='legacy')foreach(['company'=>'Nome existente','vat'=>'11222333000181','zip'=>'','address'=>'','city'=>'','state'=>'','phonenumber'=>''] as $key=>$value)echo '<label for="'.$key.'">'.$key.'</label><input class="form-control" id="'.$key.'" name="'.$key.'" value="'.$value.'">';
    require __DIR__.'/../../application/views/admin/companies/client_identity_fields.php';
    echo '<button type="submit">Salvar</button></form></main></body></html>';
}};$view->render($mode);
