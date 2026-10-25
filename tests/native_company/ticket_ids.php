<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyResourceId.php';
function ticketIdCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(['ticket_predefined_reply'=>47,'ticket_reply'=>48]as$type=>$ns){
    foreach([1,4294967295]as$id){$encoded=NativeCompanyResourceId::encode('company_'.$type,$id);ticketIdCheck($encoded===(string)($ns*4294967296+$id),'stable namespace');$r=NativeCompanyResourceId::decode($type,$encoded);ticketIdCheck($r['source_id']===$id&&$r['source_kind']==='company_'.$type,'lossless owned roundtrip');}
    ticketIdCheck(NativeCompanyResourceId::decode($type,'1')['source_kind']===$type,'historical source remains explicit');
    foreach(['ticket','ticket_status','ticket_department', $type==='ticket_reply'?'ticket_predefined_reply':'ticket_reply']as$other){try{NativeCompanyResourceId::decode($other,NativeCompanyResourceId::encode('company_'.$type,1));throw new LogicException('cross-type accepted');}catch(DomainException$e){ticketIdCheck($e->getMessage()==='native_resource_namespace_mismatch','type mismatch fails closed');}}
}
echo "Ticket response IDs: fixed 47/48 namespaces, native lineage, maximum source ID and cross-type denials passed; IDs are not authorization.\n";
