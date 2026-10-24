<?php defined('BASEPATH') or exit('No direct script access allowed');
// One native field component for primary and scoped Settings. The caller must
// supply its admitted value adapter; there is intentionally no global fallback.
if(!isset($companyIdentityOption)||!is_callable($companyIdentityOption)){throw new LogicException('Company identity value adapter required');}
$companyIdentityLabels=[
 'invoice_company_name'=>'settings_sales_company_name',
 'company_trade_name'=>'Nome fantasia',
 'company_state_registration'=>'Inscrição estadual (ou ISENTO, quando aplicável)',
 'company_address_number'=>'Número','company_address_complement'=>'Complemento','company_address_district'=>'Bairro',
 'invoice_company_address'=>'settings_sales_address','invoice_company_city'=>'settings_sales_city',
 'company_state'=>'billing_state','invoice_company_country_code'=>'settings_sales_country_code',
 'invoice_company_postal_code'=>'settings_sales_postal_code','invoice_company_phonenumber'=>'settings_sales_phonenumber',
 'company_vat'=>'company_vat_number',
];
foreach($companyIdentityLabels as $name=>$label){
    echo render_input('settings['.$name.']',$label,$companyIdentityOption($name));
}
unset($companyIdentityLabels);
?>
