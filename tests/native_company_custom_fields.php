<?php
require dirname(__DIR__).'/application/services/NativeCompanyCustomFields.php';
$n=0;$check=static function(bool $ok)use(&$n){if(!$ok)throw new RuntimeException('Custom-field pure contract failed');++$n;};
$reject=static function(callable $f)use($check){try{$f();}catch(DomainException $e){$check(true);return;}throw new RuntimeException('Invalid custom-field input accepted');};
$d=NativeCompanyCustomFields::definition(['fieldto'=>'customers','name'=>'Reference','type'=>'input']);
$check($d['active']===1&&$d['bs_column']===12&&$d['only_admin']===0);
$check(NativeCompanyCustomFields::definition($d)===$d);
$check(NativeCompanyCustomFields::value($d,'<synthetic label>')==='<synthetic label>'); // renderer, not storage, escapes text
foreach([['fieldto'=>'customers','name'=>'X','type'=>'unknown'],['fieldto'=>'customers','type'=>'input'],['fieldto'=>'customers','name'=>'X','type'=>'input','issuer_id'=>2],['fieldto'=>'customers','name'=>'X','type'=>'input','bs_column'=>'13']]as$bad)$reject(static fn()=>NativeCompanyCustomFields::definition($bad));
$required=$d;$required['required']=1;$reject(static fn()=>NativeCompanyCustomFields::value($required,''));
$select=$d;$select['type']='select';$select['options']='A, B';$check(NativeCompanyCustomFields::value($select,'A')==='A');$reject(static fn()=>NativeCompanyCustomFields::value($select,'C'));
$multi=$select;$multi['type']='multiselect';$check(NativeCompanyCustomFields::value($multi,['A','cfk_hidden','B'])==='A, B');$reject(static fn()=>NativeCompanyCustomFields::value($multi,['A','A']));
$date=$d;$date['type']='date_picker';$check(NativeCompanyCustomFields::value($date,'2024-02-29')==='2024-02-29');$reject(static fn()=>NativeCompanyCustomFields::value($date,'2023-02-29'));
$number=$d;$number['type']='number';$check(NativeCompanyCustomFields::value($number,'-12.25')==='-12.25');$reject(static fn()=>NativeCompanyCustomFields::value($number,'NaN'));$reject(static fn()=>NativeCompanyCustomFields::value($number,'1e100'));
echo "Native custom fields: {$n} pure checks passed.\n";
