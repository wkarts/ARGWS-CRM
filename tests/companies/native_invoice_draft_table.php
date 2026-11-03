<?php
require __DIR__.'/native_invoice_draft_projection.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
if(!function_exists('_l')){function _l($s){return$s;}}
$reader=new NativeCompanyInvoiceRead($db,$p);$all=$reader->page($a,7,['length'=>-1]);
$encoded=NativeCompanyResourceId::encode('company_invoice_draft',$id);
essentialCheck(in_array($encoded,array_column($all['documents'],'invoice_id'),true),'global native table union includes private draft canonical source');
$draftsOnly=$reader->page($a,7,['length'=>-1,'status'=>6]);
essentialCheck(count($draftsOnly['documents'])>0&&count(array_filter($draftsOnly['documents'],fn($d)=>$d['lineage']==='private_draft'))===count($draftsOnly['documents']),'native draft status filters real third lineage without issuing it');
$seen=[];for($offset=0;$offset<$all['recordsFiltered'];$offset+=2){$page=$reader->page($a,7,['length'=>2,'start'=>$offset]);foreach($page['documents']as$d)$seen[]=$d['invoice_id'];}
essentialCheck($seen===array_column($all['documents'],'invoice_id'),'one global order/pagination spans emitted and private sources without duplication');
$foreign=$reader->page($b,7,['length'=>-1]);essentialCheck(!in_array($encoded,array_column($foreign['documents'],'invoice_id'),true),'B union never includes A private draft');
echo 'Native invoice union private drafts: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
require_once APPPATH.'services/NativeCompanyResourceTags.php';require_once APPPATH.'services/NativeCompanyInvoiceRows.php';
$tags=new NativeCompanyResourceTags($db,$p);$revision=$tags->read($a,7,'invoice',$encoded)['revision'];
IssuerTransaction::run($db,$p,array_unique(array_merge($drafts->tables(),NativeCompanyResourceTags::tableNames())),fn()=>$tags->replaceInOwnedTransaction($a,7,'invoice',$encoded,$revision,['Synthetic private table tag']));
$found=$reader->page($a,7,['search'=>'Synthetic private table tag','length'=>-1]);essentialCheck(array_column($found['documents'],'invoice_id')===[$encoded],'search resolves private tags by source namespace, not emitted raw ID');
$fields=new NativeCompanyCustomFields($db,$p);$definition=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic draft filter','type'=>'input','active'=>1,'show_on_table'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice','invoice_private_filter',json_encode($definition,JSON_THROW_ON_ERROR)]);$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
IssuerTransaction::run($db,$p,array_unique(array_merge($drafts->tables(),$fields->tableNames())),fn()=>$fields->saveInOwnedTransaction($a,7,'invoice',$encoded,[$field=>'Synthetic private metadata']));
$found=$reader->page($a,7,['search'=>'Synthetic private metadata','length'=>-1]);essentialCheck(array_column($found['documents'],'invoice_id')===[$encoded],'global search includes real private custom values');
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}function admin_url($p=''){return '/admin/'.$p;}function site_url($p=''){return '/'.$p;}function app_format_money($v,$c){return$c.' '.$v;}function _d($v){return(string)$v;}function format_invoice_status($s){return'<span>'.(int)$s.'</span>';}
$row=(new NativeCompanyInvoiceRows($db,$p))->render($a,7,$encoded);
essentialCheck(str_contains($row[0],'init_invoice('.$encoded.')')&&str_contains($row[0],'DRAFT')&&!str_contains($row[0],'href="/invoice/'),'same native row links private draft to authenticated preview without inventing bearer hash');
essentialCheck(str_contains(implode('',$row),'Synthetic private metadata')&&str_contains(implode('',$row),'Synthetic private table tag'),'same row renders canonical private tags and custom cells');
$summary=$reader->summary($a,7);essentialCheck($summary['counts'][6]===$reader->page($a,7,['status'=>6,'length'=>-1])['recordsFiltered'],'quick-stat draft count equals the actual globally filtered source');
$db->query('UPDATE '.$p.'company_invoice_draft_projection SET total=total+1 WHERE id=?',[$id]);
try{essentialThrows(fn()=>$reader->page($a,7,['search'=>'Definitely no result']),'issuer_invoice_draft_projection_unconfirmed','off-page corrupt draft fails closed before filtered counts');}finally{$projection->backfill();}
echo 'Private draft shared row/tags/custom/count/off-page guards: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";

$db->query('UPDATE '.$p.'company_invoice_draft_projection SET issuer_id=? WHERE id=?',[$b,$id]);
try{essentialThrows(fn()=>$reader->page($b,7,['search'=>'Definitely no result']),'issuer_invoice_draft_projection_unconfirmed','foreign projection row cannot alter another issuer count even when filtered off-page');}finally{$projection->backfill();}
echo "Projection/header issuer bijection PASS.\n";
