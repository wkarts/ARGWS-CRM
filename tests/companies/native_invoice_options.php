<?php
require __DIR__.'/native_invoice_draft_table.php';
$reader=new NativeCompanyInvoiceRead($db,$p);$aOptions=$reader->options($a,7);$bOptions=$reader->options($b,7);
essentialCheck(count($aOptions)<=50&&count($bOptions)<=50,'invoice dropdown uses bounded real canonical listing');
$overlap=array_intersect(array_column($aOptions,'id'),array_column($bOptions,'id'));essentialCheck($overlap===[],'same actor A/B options do not share source identities');
foreach($aOptions as$option){essentialCheck(array_keys($option)===['id','name','subtext'],'native relation wire contains no invented actor or global profile');$one=$reader->options($a,7,'',$option['id']);essentialCheck($one===[$option],'selected canonical identity resolves exact same native label');}
$foreign=$bOptions[0]['id'];try{$reader->options($a,7,'',$foreign);throw new RuntimeException('foreign option accepted');}catch(DomainException$e){essentialCheck(true,'selected foreign invoice denied before dropdown hydration');}
essentialThrows(fn()=>$reader->options($a,7,str_repeat('x',301)),'native_company_invoice_query_invalid','oversized relation search rejected');
echo 'Native invoice relation dropdown: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$primary=(new IssuerResourceAccess($db,$p))->primaryId();(new CompanySettingsStorage($db,$p))->mutate($primary,7,'update','number_padding_prefixes','6');
$beforeQueries=count($db->queries);$legacyOption=$reader->options($primary,7,'',42);$queried=array_slice($db->queries,$beforeQueries);
$labels=array_filter($queried,static fn($q)=>str_contains($q[0],'SELECT userid AS source_id,company AS label')&&$q[1]===[9999]);
essentialCheck($legacyOption[0]['subtext']==='Synthetic untouched legacy'&&count($labels)===2,'selected principal legacy label revalidates actual customer ownership before returning');
echo 'Legacy selected relation recheck PASS.\n';
