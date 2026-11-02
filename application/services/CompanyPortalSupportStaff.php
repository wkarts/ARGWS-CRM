<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySupportAuthorship.php';
require_once __DIR__.'/CompanyTickets.php';
require_once __DIR__.'/NativeCompanyDepartmentSchema.php';

/** Continue proven contact-origin tickets without inventing native staff/department metadata. */
final class CompanyPortalSupportStaff extends CompanyPortalOperationsStorage
{
    protected function extraTables():array
    {
        CompanyPortalSupportWriteSchema::assertValid($this->db,$this->p);
        return array_merge(array_keys(CompanyContractsSchema::definitions($this->p)),array_keys(CompanyPortalSupportWriteSchema::definitions($this->p)),['options','issuers_client_links']);
    }
    private function origin(int $issuer,int $actor,int $id):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'tickets','view')||!$acl->can($issuer,$actor,'tickets','edit'))throw new DomainException('company_operation_access_denied');
        $parent=$this->parent($issuer,'tickets',$id);
        if($this->db->table_exists($this->p.NativeCompanyDepartmentSchema::TICKETS)&&$this->rows('SELECT ticket_id FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=? FOR UPDATE',[$issuer,$id]))throw new DomainException('company_operation_access_denied');
        $origin=$this->rows('SELECT contact_id,author_name,created_at FROM '.$this->p.'company_portal_support_origins WHERE issuer_id=? AND ticket_id=? AND client_id=? FOR UPDATE',[$issuer,$id,$parent['client_id']])[0]??null;
        if(!$origin)throw new DomainException('company_operation_access_denied');
        return $origin;
    }
    public function apply(int $issuer,int $actor,int $id,string $action,string $revision,string $key,array $input):void
    {
        if(!in_array($action,['portal_support_reply','portal_support_state'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_operation_invalid_input');
        $allowed=$action==='portal_support_reply'?['reply']:['assignee_id','status','priority'];
        if(array_diff(array_keys($input),$allowed))throw new DomainException('company_operation_invalid_input');
        $expected=IssuerPolicy::id($revision);if($expected>=2147483647)throw new DomainException('company_operation_stale');
        if($action==='portal_support_reply'){
            $body=$input['reply']??null;
            if(!is_string($body)||trim($body)===''||strlen($body)>12000||preg_match('//u',$body)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$body))throw new DomainException('company_operation_invalid_input');
            $input=['reply'=>trim($body)];
        }else{
            $assignee=IssuerPolicy::id($input['assignee_id']??null);
            if(!in_array($input['status']??null,['open','resolved','closed'],true)||!in_array($input['priority']??null,['low','normal','high'],true))throw new DomainException('company_operation_invalid_input');
            $input=['assignee_id'=>$assignee,'status'=>$input['status'],'priority'=>$input['priority']];
        }
        $hash=hash('sha256',json_encode([$id,$expected,$input],JSON_THROW_ON_ERROR));
        $this->tx(function()use($issuer,$actor,$id,$action,$expected,$key,$input,$hash):void{
            $origin=$this->origin($issuer,$actor,$id);$before=$this->parent($issuer,'tickets',$id);
            $receipt=$this->rows('SELECT issuer_id,actor_id,operation,input_hash,result_id FROM '.$this->p.'company_ticket_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||$receipt['operation']!==$action||!hash_equals($receipt['input_hash'],$hash)||(int)$receipt['result_id']!==$id)throw new DomainException('company_operation_request_conflict');return;}
            if((int)$before['revision']!==$expected)throw new DomainException('company_operation_stale');
            NativeCompanyTicketVisibility::assertMutable($this->db,$this->p,$issuer,$id);$now=gmdate('Y-m-d H:i:s');
            if($action==='portal_support_reply'){
                if($before['status']!=='open')throw new DomainException('company_ticket_closed');
                if(count($this->rows('SELECT id FROM '.$this->p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_operation_limit');
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_replies(issuer_id,ticket_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$issuer,$id,$actor,$input['reply'],$now]);$reply=(int)$this->db->insert_id();
                if(!$this->rows('SELECT id FROM '.$this->p.'company_ticket_replies WHERE id=? AND issuer_id=? AND ticket_id=? AND actor_id=? AND body=?',[$reply,$issuer,$id,$actor,$input['reply']]))throw new RuntimeException('issuer_operation_write_unconfirmed');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$id,$expected]);
            }else{
                if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$input['assignee_id'],'tickets','view'))throw new DomainException('company_operation_reference_denied');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET assignee_id=?,status=?,priority=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$input['assignee_id'],$input['status'],$input['priority'],$now,$issuer,$id,$expected]);
            }
            $after=$this->parent($issuer,'tickets',$id);
            if((int)$after['revision']!==$expected+1||$this->origin($issuer,$actor,$id)!==$origin)throw new RuntimeException('issuer_operation_write_unconfirmed');
            foreach($action==='portal_support_reply'?[]:['assignee_id','status','priority'] as $field)if((string)$after[$field]!==(string)$input[$field])throw new RuntimeException('issuer_operation_write_unconfirmed');
            $this->audit($issuer,$actor,$id,$action,['request_key'=>$key,'contact_id'=>(int)$origin['contact_id']]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$actor,$hash,$action,$id,$now]);
            if(!$this->rows('SELECT result_id FROM '.$this->p.'company_ticket_requests WHERE request_key=? AND issuer_id=? AND actor_id=? AND input_hash=? AND operation=? AND result_id=?',[$key,$issuer,$actor,$hash,$action,$id]))throw new RuntimeException('issuer_operation_write_unconfirmed');
        });
    }
}
