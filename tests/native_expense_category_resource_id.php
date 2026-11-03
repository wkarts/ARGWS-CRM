<?php
require_once __DIR__.'/../application/services/NativeCompanyResourceId.php';
$n=0;$check=function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException($label);++$n;};
foreach([1,4294967295]as$id){$wire=NativeCompanyResourceId::encode('company_expense_category',$id);$decoded=NativeCompanyResourceId::decode('expense_category',$wire);$check($decoded===['source_kind'=>'company_expense_category','source_id'=>$id,'canonical_id'=>$wire],'expense category source roundtrip');$check((int)$wire===53*4294967296+$id&&(int)$wire<9007199254740991,'namespace53 reserved and JS-safe');}
$check(NativeCompanyResourceId::decode('expense_category','1')['source_kind']==='expense_category','native category identity remains unchanged');
$wire=NativeCompanyResourceId::encode('company_expense_category',1);foreach(['expense','item_group','tax','invoice_item']as$type){try{NativeCompanyResourceId::decode($type,$wire);throw new RuntimeException('cross-kind accepted');}catch(DomainException$e){$check($e->getMessage()==='native_resource_namespace_mismatch','cross-kind expense category denied');}}
echo "Expense category codec: $n PASS; ownership remains a separate live check.\n";
