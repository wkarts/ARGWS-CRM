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
    if (function_exists('_l') && function_exists('render_input')) {
        echo render_input('settings['.$name.']',$label,$companyIdentityOption($name));
        continue;
    }
    // The isolated native Settings route intentionally does not load the
    // legacy language helper. Keep this shared component usable there without
    // reintroducing global option/model hydration just for presentation.
    $fallbackLabels=[
        'settings_sales_company_name'=>'Nome da empresa','company_vat_number'=>'CPF/CNPJ',
        'settings_sales_address'=>'Endereço','settings_sales_city'=>'Cidade',
        'billing_state'=>'Estado','settings_sales_country_code'=>'País',
        'settings_sales_postal_code'=>'CEP','settings_sales_phonenumber'=>'Telefone',
    ];
    $inputName='settings['.$name.']';
    $inputId='company-settings-'.preg_replace('/[^a-z0-9_-]+/i','-',(string)$name);
    $inputValue=function_exists('set_value')?set_value($inputName,$companyIdentityOption($name),false):$companyIdentityOption($name);
    $safe=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    echo '<div class="form-group" app-field-wrapper="'.$safe($inputName).'">';
    echo '<label for="'.$safe($inputId).'" class="control-label">'.$safe($fallbackLabels[$label]??$label).'</label>';
    echo '<input type="text" id="'.$safe($inputId).'" name="'.$safe($inputName).'" class="form-control" value="'.$safe($inputValue).'">';
    echo '</div>';
}
unset($companyIdentityLabels);
?>
