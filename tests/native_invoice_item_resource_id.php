<?php
require_once __DIR__.'/../application/services/NativeCompanyResourceId.php';
$n=0;$check=function($condition,$label)use(&$n){if(!$condition)throw new RuntimeException($label);++$n;};
foreach([1,4294967295]as$id){$transport=NativeCompanyResourceId::encode('company_invoice_draft_line',$id);$ref=NativeCompanyResourceId::decode('invoice_item',$transport);$check($ref['source_kind']==='company_invoice_draft_line'&&$ref['source_id']===$id,'draft storage identity roundtrip');$check((int)$transport===49*4294967296+$id&&(int)$transport<9007199254740991,'reserved namespace is exact and JS-safe');}
$check(NativeCompanyResourceId::decode('invoice_item','1')['source_kind']==='invoice_item','real itemable ID keeps native plain lineage');
$draft=NativeCompanyResourceId::encode('company_invoice_draft_line',1);
foreach(['item','invoice','task']as$type){try{NativeCompanyResourceId::decode($type,$draft);throw new RuntimeException('accepted wrong type');}catch(DomainException$e){$check($e->getMessage()==='native_resource_namespace_mismatch','cross-domain namespace rejected');}}
echo "Invoice item transport: $n PASS; codec is not ownership authorization.\n";
