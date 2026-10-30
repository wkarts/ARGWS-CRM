<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyRelationDirectory.php';
/** Original Misc POST contract. A read never falls back to the global models. */
final class NativeCompanyRelationEndpoints
{
    public static function read($ci):array
    {
        CompanyRequestScope::assertNativeMutation($ci);$context=CompanyRequestScope::context();
        $type=$ci->input->post('type');$q=$ci->input->post('q')??'';$selected=$ci->input->post('rel_id');$extra=$ci->input->post('extra')??[];
        if(!is_string($type)||!is_string($q)||!is_array($extra)||array_diff(array_keys($extra),['client_id']))throw new DomainException('native_relation_invalid');
        if(strlen($q)>200||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new DomainException('native_relation_invalid');
        if($selected!==null&&!is_string($selected)&&!is_int($selected))throw new DomainException('native_relation_invalid');
        $type=['customers'=>'customer','contacts'=>'contact','tasks'=>'task','contracts'=>'contract','expenses'=>'expense','leads'=>'lead'][$type]??$type;
        $i=$context->issuerId();$a=$context->staffId();$p=db_prefix();
        if(in_array($type,['customer','contact','staff'],true)){
            $client=$extra['client_id']??null;$explicit=$ci->input->post('contact_userid');
            if($explicit!==null&&$explicit!==''){
                $explicit=IssuerPolicy::id($explicit);if($client!==null&&$client!==''&&IssuerPolicy::id($client)!==$explicit)throw new DomainException('native_relation_invalid');$client=$explicit;
            }
            $tickets=$ci->input->post('tickets_contacts');if(!in_array($tickets,[null,'',false,0,'0',true,1,'1'],true))throw new DomainException('native_relation_invalid');
            if(in_array($tickets,[true,1,'1'],true)){if($type!=='contact')throw new DomainException('native_relation_invalid');require_once __DIR__.'/NativeCompanyTicketContacts.php';return(new NativeCompanyTicketContacts($ci->db,$p))->options($i,$a,$q,$selected,$client);}
            return(new NativeCompanyRelationDirectory($ci->db,$p))->options($i,$a,$type,$q,$selected,$client);
        }
        if($extra)throw new DomainException('native_relation_invalid');
        if(in_array($type,['contract','expense','lead'],true)){require_once __DIR__.'/NativeCompanyRelationOperations.php';return(new NativeCompanyRelationOperations($ci->db,$p))->options($i,$a,$type,$q,$selected);}
        if(in_array($type,['credit_note','estimate','proposal'],true)){require_once __DIR__.'/NativeCompanyRelationSales.php';return(new NativeCompanyRelationSales($ci->db,$p))->options($i,$a,$type,$q,$selected);}
        if($type==='task'){
            if($selected===null||$selected==='')throw new DomainException('native_relation_selection_required');
            require_once __DIR__.'/NativeCompanyTasksRead.php';$row=(new NativeCompanyTasksRead($ci->db,$p))->relationOption($i,$a,$selected);
            return[['id'=>(string)$row['id'],'name'=>$row['name'],'subtext'=>'']];
        }
        if($type==='ticket'){require_once __DIR__.'/NativeCompanyTicketRead.php';return(new NativeCompanyTicketRead($ci->db,$p))->relationOptions($i,$a,$q,$selected);}
        if($type==='invoice'){
            require_once __DIR__.'/NativeCompanyInvoiceRead.php';return(new NativeCompanyInvoiceRead($ci->db,$p))->options($i,$a,$q,$selected);
        }
        if($type==='project'){
            require_once __DIR__.'/NativeCompanyProjectRead.php';require_once __DIR__.'/NativeCompanyResourceReferences.php';$customer=$ci->input->post('customer_id');
            if($selected!==null&&$selected!==''){
                $ref=NativeCompanyResourceReferences::resolve($ci->db,$p,$i,$a,'project',$selected,'view');
                if($customer!==null&&$customer!==''){
                    $customer=IssuerPolicy::id($customer);$clientRef=NativeCompanyResourceReferences::resolve($ci->db,$p,$i,$a,'client',$customer,'view');
                    $owned=$ref['source_kind']==='company_project';$table=$owned?'company_projects':'projects';$field=$owned?'client_id':'clientid';
                    $r=IssuerStorage::query($ci->db,'SELECT '.$field.' client FROM '.$p.$table.' WHERE id=?',[$ref['source_id']])->row_array();if(!$r||(int)$r['client']!==$customer||$r!==IssuerStorage::query($ci->db,'SELECT '.$field.' client FROM '.$p.$table.' WHERE id=?',[$ref['source_id']])->row_array()||$clientRef!==NativeCompanyResourceReferences::resolve($ci->db,$p,$i,$a,'client',$customer,'view'))throw new DomainException('native_relation_denied');
                }
                if($ref!==NativeCompanyResourceReferences::resolve($ci->db,$p,$i,$a,'project',$selected,'view'))throw new DomainException('native_relation_denied');
                return[['id'=>$ref['canonical_id'],'name'=>$ref['name'],'subtext'=>'']];
            }
            return array_map(static fn($r)=>['id'=>(string)$r['id'],'name'=>$r['name'],'subtext'=>''],(new NativeCompanyProjectRead($ci->db,$p))->relationOptions($i,$a,$q,$customer,50));
        }
        // These domains need their concrete native reader before this endpoint is admitted for them.
        throw new DomainException('native_relation_provider_required');
    }
}
