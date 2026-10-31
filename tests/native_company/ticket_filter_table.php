<?php
declare(strict_types=1);
require __DIR__.'/ticket_create.php';
if(!defined('BASEPATH'))define('BASEPATH',__DIR__);
function _l($key,...$args){return$key;}
require_once __DIR__.'/../../application/services/NativeCompanyTicketTable.php';
$options=['department'=>[],'status'=>[],'priority'=>[],'service'=>[],'assigned'=>[['value'=>17,'label'=>'Authorized synthetic staff']]];
$catalog=new NativeCompanyTicketCatalog($db,'tbl');foreach(['status','priority','service']as$kind)$options[$kind]=array_map(fn($r)=>['value'=>$r['native_id'],'label'=>$r['name']],$catalog->list(2,17,$kind));
$options['department']=[['value'=>NativeCompanyResourceId::encode('company_ticket_department',1),'label'=>'Owned department']];
$table=new NativeCompanyTicketTable($db,'tbl',2,17,'tickets',$options,[]);$rules=$table->rules();kc(array_column($rules,'id')===array_keys(NativeCompanyTicketRules::TYPES),'same original eight rule identities and order');$serialized=array_map(fn($r)=>$r->jsonSerialize(),$rules);kc($serialized[1]['visible_to_all']&&$serialized[7]['visible_to_all'],'administrative controls preserve original visibility');kc(in_array('is_empty',$serialized[7]['operators'],true)&&in_array('is_not_empty',$serialized[7]['operators'],true),'assigned original empty operators retained');kc($table->contextIdentity()===['issuer_id'=>2,'actor_id'=>17]&&$table->id()==='tickets'&&$table->viewName()==='tickets','exact existing table/filter identity');
$builder=['match_type'=>'and','rules'=>[['id'=>'subject','type'=>'TextRule','operator'=>'contains','value'=>'email']]];kc($table->normalizeBuilder($builder)['rules'][0]['value']==='email','real table normalizes saved native grammar');
$foreign=[['issuer_id'=>3,'staff_id'=>17,'identifier'=>'tickets','is_shared'=>1,'builder'=>$builder]];$deny(fn()=>new NativeCompanyTicketTable($db,'tbl',2,17,'tickets',$options,$foreign));$deny(fn()=>new NativeCompanyTicketTable($db,'tbl',2,17,'invoices',$options,[]));
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=NULL');$db->query('UPDATE tblstaff SET admin=0 WHERE staffid=17');$visible=array_map(fn($r)=>$r->jsonSerialize(),$table->rules());kc(!$visible[1]['visible_to_all']&&!$visible[7]['visible_to_all'],'same object revalidates administrative revocation');$deny(fn()=>$table->normalizeBuilder(['match_type'=>'and','rules'=>[['id'=>'assigned','type'=>'SelectRule','operator'=>'equal','value'=>'17']]]));
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$table->rules());$deny(fn()=>$table->contextIdentity());echo "Ticket original filter presentation + real SQLite authority passed: eight actual App_table_filter objects, exact identity, visibility, A/B saved-filter and revoked ACL negatives. Full index/endpoint remains pending.\n";
