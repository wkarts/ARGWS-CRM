<?php
// Render the actual shared production partial with synthetic data; never connect to a DB.
define('BASEPATH',__DIR__);class CompanyRequestScope{static function token(){return 'synthetic-context';}}
function base_url($path){return 'https://fixture.test/'.$path;}
$identity_mode=$argv[1]??'registry';$selected=['id'=>42];
$identity_values=['legal_name'=>'Nome existente','tax_id'=>'11222333000181','profile'=>[]];
$lookup_url='https://fixture.test/admin/issuers/lookup';$lookup_token=str_repeat('a',64);$identity_profile_disabled=false;
echo '<!doctype html><html lang="pt-BR"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="https://fixture.test/assets/plugins/bootstrap/css/bootstrap.min.css"></head><body><main style="padding:24px;max-width:1000px;margin:auto"><form><input type="hidden" name="native_csrf" value="synthetic-token">';
require __DIR__.'/../application/views/admin/companies/identity_fields.php';
echo '<button type="submit">Salvar</button></form></main></body></html>';
