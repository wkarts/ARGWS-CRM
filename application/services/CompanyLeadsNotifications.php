<?php

declare(strict_types=1);

/** Opt-in internal inbox only. No SMTP, IMAP, Connect, mobile push or native legacy notification. */
trait CompanyLeadsNotifications
{
    private function scheduleNotices(int $issuer,int $actor,array $lead,string $cap):void
    {
        if((int)$lead['assignee_id']===0||(int)$lead['archived']!==0||$lead['converted_client_id']!==null)return;
        $stage=$this->related($issuer,'stage',(int)$lead['stage_id']);if($stage['category']!=='open')return;
        $jobs=[];if((int)$lead['notify_assignee']===1)$jobs['assignment']=self::now();if($lead['reminder_at']!==null)$jobs['reminder']=$lead['reminder_at'];
        foreach($jobs as $kind=>$due)$this->insert('company_lead_notices',['issuer_id'=>$issuer,'lead_id'=>$lead['id'],'actor_id'=>$actor,'recipient_id'=>$lead['assignee_id'],'kind'=>$kind,'required_capability'=>$cap,'lead_revision'=>$lead['revision'],'due_at'=>$due,'state'=>'pending','origin_token_id'=>$this->api['token_id']??null,'origin_scope'=>$this->api['scope']??null,'created_at'=>self::now(),'processed_at'=>null,'acknowledged_at'=>null]);
    }
    private function cancelNotices(int $issuer,int $id):void
    {
        foreach($this->rows("SELECT * FROM {$this->p}company_lead_notices WHERE issuer_id=? AND lead_id=? AND state='pending' FOR UPDATE",[$issuer,$id]) as $r)$this->updateRow('company_lead_notices',$r,['state'=>'cancelled','processed_at'=>self::now()]);
    }
    private function bumpNoticeRevision(int $issuer,int $id,int $revision):void
    {
        foreach($this->rows("SELECT * FROM {$this->p}company_lead_notices WHERE issuer_id=? AND lead_id=? AND state='pending' FOR UPDATE",[$issuer,$id]) as $r)$this->updateRow('company_lead_notices',$r,['lead_revision'=>$revision]);
    }
    private function guardNotice(array $r):array
    {
        if(!in_array($r['kind'],['assignment','reminder'],true)||!in_array($r['required_capability'],['create','edit'],true)||($r['origin_token_id']===null)!==($r['origin_scope']===null))throw new RuntimeException('issuer_lead_notice_invalid');
        $i=IssuerPolicy::id($r['issuer_id']);$a=IssuerPolicy::id($r['actor_id']);$recipient=IssuerPolicy::id($r['recipient_id']);$this->acl->lockFinancialIdentity($i,$a);$this->acl->lockFinancialIdentity($i,$recipient);
        if(!$this->acl->can($i,$a,'leads','view')||!$this->acl->can($i,$a,'leads',$r['required_capability'])||!$this->acl->can($i,$recipient,'leads','view'))throw new DomainException('company_lead_notice_revoked');
        if($r['origin_token_id']!==null)(new CompanyApiTokens($this->db,$this->p))->guardOriginInOwnedTransaction($r['origin_token_id'],$r['origin_scope'],$i,$a);
        $lead=$this->lead($i,(int)$r['lead_id']);$stage=$this->related($i,'stage',(int)$lead['stage_id'],false);
        if((int)$lead['archived']!==0||$lead['converted_client_id']!==null||(int)$lead['assignee_id']!==$recipient||$stage['category']!=='open'||(int)$stage['active']!==1)throw new DomainException('company_lead_notice_revoked');
        if($r['state']==='pending'&&(int)$lead['revision']!==(int)$r['lead_revision'])throw new DomainException('company_lead_notice_revoked');
        if($r['kind']==='assignment'&&(int)$lead['notify_assignee']!==1)throw new DomainException('company_lead_notice_revoked');
        if($r['kind']==='reminder'&&$lead['reminder_at']!==$r['due_at'])throw new DomainException('company_lead_notice_revoked');return $lead;
    }
    public function processNotice($id):string
    {
        $id=IssuerPolicy::id($id);if($this->api!==null)throw new DomainException('company_lead_access_denied');CompanyLeadsSchema::assertValid($this->db,$this->p);CompanyApiSchema::assertValid($this->db,$this->p);$this->frames=[];$this->validators=[];
        try{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($id):string{$r=$this->rows('SELECT * FROM '.$this->p.'company_lead_notices WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$r||$r['state']!=='pending'||$r['due_at']>self::now())return 'unchanged';
            $state='delivered';try{$lead=$this->guardNotice($r);$this->remember('company_leads',['id'=>$lead['id']],$lead);}catch(DomainException $e){$state='suppressed';}
            $this->updateRow('company_lead_notices',$r,['state'=>$state,'processed_at'=>self::now()]);$this->audit((int)$r['issuer_id'],(int)$r['actor_id'],'notice_'.$state,'notice',(string)$id);
            if($state==='delivered')$this->guardNotice($r);$this->assertFrames();return $state;
        });}finally{$this->frames=[];$this->validators=[];}
    }
    /** Durable rotating discovery cursor. A crash leaves work pending; failures cannot pin later passes. */
    public function processAllNotices(int $limit=100):array
    {
        self::integer($limit,1,500);CompanyLeadsSchema::assertValid($this->db,$this->p);$now=self::now();$out=['scanned'=>0,'delivered'=>0,'suppressed'=>0,'unchanged'=>0,'failed'=>0];
        $ids=IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($limit,$now):array{$cursor=$this->rows('SELECT * FROM '.$this->p.'company_lead_notice_cursor WHERE id=1 FOR UPDATE')[0]??null;if(!$cursor)throw new RuntimeException('issuer_lead_cursor_invalid');$max=(int)($this->rows("SELECT MAX(id) n FROM {$this->p}company_lead_notices WHERE state='pending' AND due_at<=?",[$now])[0]['n']??0);$rows=$this->rows("SELECT id FROM {$this->p}company_lead_notices WHERE state='pending' AND due_at<=? AND id>? AND id<=? ORDER BY id LIMIT ".$limit,[$now,$cursor['last_id'],$max]);if(count($rows)<$limit)$rows=array_merge($rows,$this->rows("SELECT id FROM {$this->p}company_lead_notices WHERE state='pending' AND due_at<=? AND id<=? AND id<=? ORDER BY id LIMIT ".($limit-count($rows)),[$now,$cursor['last_id'],$max]));if($rows){$last=(int)$rows[count($rows)-1]['id'];$updated=self::now();IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_lead_notice_cursor SET last_id=?,updated_at=? WHERE id=1',[$last,$updated]);$this->confirm('company_lead_notice_cursor',['id'=>1],['id'=>1,'last_id'=>$last,'updated_at'=>$updated]);}return $rows;});
        foreach($ids as $r){++$out['scanned'];try{$result=$this->processNotice((int)$r['id']);++$out[$result];}catch(Throwable $e){++$out['failed'];}}return $out;
    }
    public function inbox($issuer,$actor,$after=0):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=self::integer($after);
        return $this->transaction($issuer,$actor,'view','inbox',null,function()use($issuer,$actor,$after):array{$rows=$this->rows("SELECT * FROM {$this->p}company_lead_notices WHERE issuer_id=? AND recipient_id=? AND state='delivered' AND id>? ORDER BY id LIMIT 51",[$issuer,$actor,$after]);$more=count($rows)>50;if($more)array_pop($rows);$items=[];foreach($rows as $r){try{$lead=$this->guardNotice($r);}catch(DomainException $e){continue;}$this->remember('company_lead_notices',['id'=>$r['id']],$r);$this->remember('company_leads',['id'=>$lead['id']],$lead);$this->validators[]=fn()=>$this->guardNotice($r);$items[]=['id'=>(int)$r['id'],'lead_id'=>(int)$r['lead_id'],'name'=>$lead['name'],'kind'=>$r['kind'],'due_at'=>$r['due_at'],'processed_at'=>$r['processed_at'],'acknowledged_at'=>$r['acknowledged_at']];}return ['items'=>$items,'next'=>$more?(int)$rows[count($rows)-1]['id']:null];});
    }
    public function acknowledge($issuer,$actor,$notice,string $key):int
    {
        $notice=IssuerPolicy::id($notice);return $this->request($issuer,$actor,'view','notice_read',$key,[$notice],function(int $i,int $a)use($notice):array{$r=$this->rows("SELECT * FROM {$this->p}company_lead_notices WHERE id=? AND issuer_id=? AND recipient_id=? AND state='delivered' FOR UPDATE",[$notice,$i,$a])[0]??null;if(!$r)throw new DomainException('company_lead_access_denied');$this->guardNotice($r);$this->validators[]=fn()=>$this->guardNotice($r);$this->updateRow('company_lead_notices',$r,['acknowledged_at'=>$r['acknowledged_at']??self::now()]);return ['notice',$notice,$notice];});
    }
}
