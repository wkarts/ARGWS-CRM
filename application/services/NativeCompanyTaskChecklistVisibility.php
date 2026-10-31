<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Per-actor preference scoped to the active company and exact canonical task. */
final class NativeCompanyTaskChecklistVisibility
{
    public function __construct(private$db,private string$p){}
    private function key(int$i,int$a,$id):array
    {
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$id,'view');$acl=new IssuerResourceAccess($this->db,$this->p);$key=($acl->primaryId()===$i?'':'issuer:'.$i.':').'task-hide-completed-items-'.$ref['canonical_id'];return[$key,$ref];
    }
    public function read(int$i,int$a,$id):int
    {
        [$key,$ref]=$this->key($i,$a,$id);$rows=IssuerStorage::query($this->db,'SELECT meta_value FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$a,$key])->result_array();if(count($rows)>1)throw new DomainException('native_checklist_visibility_ambiguous');$value=$rows?$rows[0]['meta_value']:'0';if(!in_array((string)$value,['0','1'],true)||$this->key($i,$a,$id)!==[$key,$ref])throw new DomainException('native_checklist_visibility_invalid');return(int)$value;
    }
    public function save(int$i,int$a,$id,$value):int
    {
        if(!in_array($value,[0,1,'0','1'],true))throw new DomainException('native_checklist_visibility_invalid');$value=(string)$value;[$key,$ref]=$this->key($i,$a,$id);
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','user_meta','company_tasks','company_standalone_tasks','company_projects'];if($this->db->table_exists($this->p.'company_ticket_catalog'))$tables=array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,'ticket'));if($this->db->table_exists($this->p.'invoices'))$tables=array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,'invoice'));
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique($tables)),function()use($i,$a,$id,$value,$key,$ref){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);if($this->key($i,$a,$id)!==[$key,$ref])throw new DomainException('native_checklist_visibility_denied');$rows=IssuerStorage::query($this->db,'SELECT umeta_id FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=? FOR UPDATE',[$a,$key])->result_array();if(count($rows)>1)throw new DomainException('native_checklist_visibility_ambiguous');if($rows)IssuerStorage::query($this->db,'UPDATE '.$this->p.'user_meta SET meta_value=? WHERE umeta_id=? AND staff_id=? AND meta_key=?',[$value,$rows[0]['umeta_id'],$a,$key]);else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[$a,$key,$value]);if($this->read($i,$a,$id)!==(int)$value)throw new RuntimeException('native_checklist_visibility_unconfirmed');return(int)$value;});
    }
}
