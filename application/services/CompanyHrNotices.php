<?php

declare(strict_types=1);

/** Opt-in local inbox. No SMTP, webhook or legacy notification writer is invoked. */
trait CompanyHrNotices
{
    private function notice(int $i,int $id):array
    {
        $n=$this->row($i,'notice',$id);$assignment=$this->assignment($i,(int)$n['assignment_id']);
        if((int)$assignment['actor_id']!==(int)$n['actor_id']||(int)$assignment['employee_id']!==(int)$n['employee_id']||(int)$assignment['notify_opt_in']!==1||!in_array($n['event'],['assigned','due'],true)||$n['created_at']!==$assignment['created_at']||$n['scheduled_at']!==($n['event']==='assigned'?$assignment['created_at']:$assignment['due_at']))throw new RuntimeException('issuer_hr_integrity_failed');
        if($n['state']!=='pending'){
            if(!in_array($n['state'],['published','cancelled'],true)||$n['scheduled_at']>self::now())throw new RuntimeException('issuer_hr_integrity_failed');
            $audit=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND event=? AND subject_id=? ORDER BY id DESC LIMIT 1',[$i,$n['actor_id'],'company_hr_notice_'.$n['state'],$id])[0]??null;
            if(!$audit||$audit['metadata_json']!==self::json(['assignment_id'=>(int)$n['assignment_id'],'event'=>$n['event']]))throw new RuntimeException('issuer_hr_integrity_failed');$this->remember('issuers_audit',['id'=>$audit['id']],$audit);
        }
        if($n['read_at']!==null){if($n['state']!=='published'||$n['read_at']<$n['scheduled_at']||$n['read_at']>self::now())throw new RuntimeException('issuer_hr_integrity_failed');$this->boundProof($i,'notice',$id,$n);}
        return [$n,$assignment];
    }
    public function processNotices(int $limit=25):array
    {
        if($limit<1||$limit>25)throw new DomainException('issuer_hr_invalid');CompanyHrSchema::assertValid($this->db,$this->p);
        $ids=IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($limit):array{
            $cursor=$this->rows('SELECT * FROM '.$this->p.'company_hr_cursor WHERE id=1 FOR UPDATE')[0]??null;if(!$cursor||(int)$cursor['revision']<1||(int)$cursor['last_id']>(int)$cursor['upper_id'])throw new RuntimeException('issuer_hr_integrity_failed');
            $last=(int)$cursor['last_id'];$upper=(int)$cursor['upper_id'];if($last===$upper){$last=0;$upper=(int)($this->rows('SELECT MAX(id) n FROM '.$this->p.'company_hr_notices')[0]['n']??0);}
            $rows=$this->rows('SELECT id,issuer_id,actor_id FROM '.$this->p.'company_hr_notices WHERE id>? AND id<=? ORDER BY id LIMIT '.$limit,[$last,$upper]);$next=$rows?(int)end($rows)['id']:$upper;$revision=self::integer($cursor['revision'],1,2147483646)+1;
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_hr_cursor SET last_id=?,upper_id=?,revision=? WHERE id=1',[$next,$upper,$revision]);$read=$this->rows('SELECT * FROM '.$this->p.'company_hr_cursor WHERE id=1');if(self::canonical($read)!==self::canonical([['id'=>1,'last_id'=>$next,'upper_id'=>$upper,'revision'=>$revision]]))throw new RuntimeException('issuer_hr_write_unconfirmed');return $rows;
        });
        $counts=['examined'=>count($ids),'published'=>0,'waiting'=>0,'blocked'=>0];foreach($ids as $found){try{
            $published=$this->transaction((int)$found['issuer_id'],(int)$found['actor_id'],self::permissions('hr_training','assign'),'notice_publish',null,function(int $i,int $a)use($found):bool{
                [$n,$assignment]=$this->notice($i,(int)$found['id']);if((int)$n['actor_id']!==$a)throw new RuntimeException('issuer_hr_integrity_failed');
                if($n['state']!=='pending'||$n['scheduled_at']>self::now())return false;
                $state=$assignment['state']==='assigned'?'published':'cancelled';$this->updateRow('company_hr_notices',$n,['state'=>$state]);$this->audit($i,$a,'notice_'.$state,(int)$n['id'],['assignment_id'=>(int)$n['assignment_id'],'event'=>$n['event']]);$this->validators[]=function()use($i,$assignment):void{$this->assignment($i,(int)$assignment['id']);};return $state==='published';
            });$counts[$published?'published':'waiting']++;
        }catch(DomainException|RuntimeException $e){$counts['blocked']++;}}
        return $counts;
    }
    public function myNotices($issuer,$actor):array
    {
        return $this->transaction($issuer,$actor,[],'notices',null,function(int $i,int $a):array{
            $employee=$this->ownEmployee($i,$a);$ids=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_notices WHERE issuer_id=? AND employee_id=? AND state=? ORDER BY id DESC LIMIT 101',[$i,$employee['id'],'published']);if(count($ids)>100)throw new DomainException('issuer_hr_limit');$out=[];
            foreach($ids as $v){[$n,$assignment]=$this->notice($i,(int)$v['id']);if((int)$assignment['employee_id']!==(int)$employee['id']||$assignment['state']==='cancelled')throw new DomainException('issuer_hr_access_denied');$out[]=['id'=>(int)$n['id'],'event'=>$n['event'],'assignment_id'=>(int)$n['assignment_id'],'title'=>self::data($assignment,'contract_json','contract_hash')['course']['title'],'scheduled_at'=>$n['scheduled_at'],'read_at'=>$n['read_at']];}return $out;
        });
    }
    public function readNotice($issuer,$actor,$id,string $key):int
    {
        $id=IssuerPolicy::id($id);return $this->request($issuer,$actor,[],'notice_read',$key,[$id],function(int $i,int $a)use($id):array{
            $employee=$this->ownEmployee($i,$a);[$n]=$this->notice($i,$id);if((int)$n['employee_id']!==(int)$employee['id']||$n['state']!=='published')throw new DomainException('issuer_hr_access_denied');$this->updateRow('company_hr_notices',$n,['read_at'=>$n['read_at']??self::now()]);return ['notice',$id,$id];
        },function(int $i,int $a)use($id):void{$employee=$this->ownEmployee($i,$a);[$n]=$this->notice($i,$id);if((int)$n['employee_id']!==(int)$employee['id']||$n['state']!=='published')throw new DomainException('issuer_hr_access_denied');});
    }
}
