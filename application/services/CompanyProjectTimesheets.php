<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectTimesheetsStorage.php';
/** Staff opt-in only; timesheets never create financial quantity or change timers. */
final class CompanyProjectTimesheets extends CompanyProjectTimesheetsStorage
{
    private function authorize(int $issuer,int $actor,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'projects',$cap))throw new DomainException('company_project_portal_access_denied');}
    public function get($issuer,$actor,$project):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);return $this->tx(function()use($issuer,$actor,$project):array{$this->authorize($issuer,$actor,'view');$parent=$this->parent($issuer,$project);$rules=$this->timesheetRules($issuer,$project);$this->authorize($issuer,$actor,'view');return ['project'=>$parent,'rules'=>$rules,'can_edit'=>(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'projects','edit')];});
    }
    public function save($issuer,$actor,$project,$revision,$rulesRevision,array $flags,array $features,string $key):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);$rulesRevision=self::rulesRevision($rulesRevision);if(array_keys($flags)!==['view_timesheets']||array_keys($features)!==['project_timesheets']||!in_array($flags['view_timesheets'],[0,1,'0','1'],true)||!in_array($features['project_timesheets'],[0,1,'0','1'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_portal_invalid');$data=['view_timesheets'=>(int)$flags['view_timesheets'],'project_timesheets'=>(int)$features['project_timesheets']];$hash=self::hash([$revision,$rulesRevision,$data]);
        $this->tx(function()use($issuer,$actor,$project,$revision,$rulesRevision,$key,$hash,$data):void{
            $this->authorize($issuer,$actor,'edit');$parent=$this->parent($issuer,$project);$old=$this->rows('SELECT * FROM '.$this->p.'company_project_timesheet_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||(int)$old['project_id']!==$project||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_project_portal_request_conflict');return;}if((int)$parent['revision']!==$revision||$this->timesheetRules($issuer,$project)['revision']!==$rulesRevision)throw new DomainException('company_project_portal_stale');
            $fields=$data+['revision'=>$rulesRevision+1,'actor_id'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')];if($rulesRevision===0){$all=['issuer_id'=>$issuer,'project_id'=>$project]+$fields;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_timesheet_rules (`'.implode('`,`',array_keys($all)).'`) VALUES ('.implode(',',array_fill(0,count($all),'?')).')',array_values($all));}else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_project_timesheet_rules SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($fields))).' WHERE issuer_id=? AND project_id=?',array_merge(array_values($fields),[$issuer,$project]));
            if($this->timesheetRules($issuer,$project)!==($data+['revision'=>$rulesRevision+1]))throw new RuntimeException('issuer_project_timesheets_unconfirmed');$this->audit($issuer,$actor,$project,'company_project_timesheet_rules',['request_key'=>$key,'input_hash'=>$hash]);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_timesheet_requests(request_key,issuer_id,project_id,actor_id,input_hash,created_at) VALUES(?,?,?,?,?,?)',[$key,$issuer,$project,$actor,$hash,gmdate('Y-m-d H:i:s')]);if(!$this->rows('SELECT request_key FROM '.$this->p.'company_project_timesheet_requests WHERE request_key=? AND issuer_id=? AND project_id=? AND actor_id=? AND input_hash=?',[$key,$issuer,$project,$actor,$hash]))throw new RuntimeException('issuer_project_timesheets_unconfirmed');$this->authorize($issuer,$actor,'edit');if($this->parent($issuer,$project)!==$parent||$this->timesheetRules($issuer,$project)!==($data+['revision'=>$rulesRevision+1]))throw new DomainException('company_project_portal_stale');
        });
    }
}
