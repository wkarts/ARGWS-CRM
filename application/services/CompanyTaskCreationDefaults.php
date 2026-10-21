<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTaskPreferences.php';

/** Resolves omitted creation fields after receipt lookup, using each existing canonical inserter. */
trait CompanyTaskCreationDefaults
{
    public function creationDefaults($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'create');
        return $this->transaction(function()use($issuer,$actor){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$v=CompanyTaskPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);$this->authorize($issuer,$actor,'create');return ['revision'=>$v['revision'],CompanyTaskPreferences::STATUS=>$v[CompanyTaskPreferences::STATUS],CompanyTaskPreferences::ASSIGN=>$v[CompanyTaskPreferences::ASSIGN]];});
    }
    private function createUsingOwnDefaults($issuer,$actor,array $input,string $key,string $operation):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_invalid_input');
        $statusExplicit=array_key_exists('status',$input);$assigneeExplicit=array_key_exists('assignee_id',$input);
        $preview=$input;if(!$statusExplicit)$preview['status']='todo';if(!$assigneeExplicit)$preview['assignee_id']=$actor;
        $canonical=self::input($preview);
        // Preserve explicit null as distinct input: the existing validator canonicalizes it to todo. It never consumes the new status preference.
        $rawStatus=$statusExplicit?$input['status']:null;$newValid=!$statusExplicit||in_array($rawStatus,[null,'todo','in_progress'],true);
        $payload=['contract'=>'own_task_creation_defaults_v1','status_explicit'=>$statusExplicit,'status_value'=>$rawStatus,'assignee_explicit'=>$assigneeExplicit,'input'=>$canonical];
        $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        // Historical create required an explicit valid assignee and canonical todo only.
        $legacyHash=$assigneeExplicit&&$canonical['status']==='todo'?hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR)):null;
        $this->authorize($issuer,$actor,'create');
        return $this->transaction(function()use($issuer,$actor,$canonical,$statusExplicit,$assigneeExplicit,$newValid,$key,$operation,$hash,$legacyHash):int{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_project_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){
                $newMatch=$newValid&&(hash_equals((string)$receipt['input_hash'],$hash)||$this->sealedCreationReplay($receipt,$hash));$oldMatch=$legacyHash!==null&&hash_equals((string)$receipt['input_hash'],$legacyHash);
                if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||$receipt['operation']!==$operation||(!$newMatch&&!$oldMatch))throw new DomainException('company_project_request_conflict');
                $this->row($issuer,(int)$receipt['result_id']);$this->authorize($issuer,$actor,'create');return (int)$receipt['result_id'];
            }
            if(!$newValid)throw new DomainException('company_project_invalid_input');
            $data=$canonical;$preferences=null;
            if(!$statusExplicit||!$assigneeExplicit){
                $preferences=CompanyTaskPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);
                if(!$statusExplicit)$data['status']=$preferences[CompanyTaskPreferences::STATUS];
                if(!$assigneeExplicit){if($preferences[CompanyTaskPreferences::ASSIGN]!=='1')throw new DomainException('company_project_invalid_input');$data['assignee_id']=$actor;}
            }
            if(!in_array($data['status'],['todo','in_progress'],true))throw new DomainException('company_project_invalid_input');
            $id=$this->insertCreatedInOwned($issuer,$actor,$data);$expected=$this->row($issuer,$id);
            $request=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'input_hash'=>$hash,'operation'=>$operation,'result_id'=>$id,'created_at'=>date('Y-m-d H:i:s')];
            $priorityMetadata=$this->sealCreationRequest($request);
            // One audit write, with exact native bytes known before any AFTER trigger.
            $auditFields=['actor_id'=>$actor,'event'=>'company_'.$operation,'issuer_id'=>$issuer,'subject_id'=>$id,'created_at'=>date('Y-m-d H:i:s')];if($priorityMetadata!==null)$auditFields['metadata_json']=$priorityMetadata;
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(`'.implode('`,`',array_keys($auditFields)).'`) VALUES('.implode(',',array_fill(0,count($auditFields),'?')).')',array_values($auditFields));$auditId=(int)$this->db->insert_id();$auditFrame=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??null;if(!$auditFrame)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($auditFields as$field=>$value)if(!array_key_exists($field,$auditFrame)||(string)$auditFrame[$field]!== (string)$value)throw new RuntimeException('issuer_project_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',array_values($request));
            $stored=$this->rows('SELECT * FROM '.$this->p.'company_project_requests WHERE request_key=?',[$key])[0]??null;
            if(!$stored)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($request as$field=>$value)if(!array_key_exists($field,$stored)||(string)$stored[$field]!== (string)$value)throw new RuntimeException('issuer_project_write_unconfirmed');
            if(($this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??null)!==$auditFrame)throw new RuntimeException('issuer_project_write_unconfirmed');
            if($this->row($issuer,$id)!==$expected)throw new RuntimeException('issuer_project_write_unconfirmed');
            $this->references($issuer,$data);if($preferences!==null)CompanyTaskPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$preferences);$this->authorize($issuer,$actor,'create');return $id;
        });
    }
}
