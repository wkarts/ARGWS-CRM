<?php

declare(strict_types=1);

trait CompanyHrRecords
{
    private function recordInput(string $kind,array $data):array
    {
        $fields=match($kind){'dependent'=>['name','relationship','birth_date','tax_identifier'],'education'=>['qualification','institution','completed_on'],'award','discipline'=>['title','occurred_on','notes'],'onboarding','offboarding'=>['title','items'],'equipment'=>['asset_code','description','assigned_on','returned_on'],'compensation'=>['previous_minor','current_minor','currency','effective_on','notes'],default=>throw new DomainException('issuer_hr_invalid')};self::fields($data,$fields);$r=[];
        foreach($fields as $f){$r[$f]=match($f){'birth_date','completed_on','occurred_on','assigned_on','effective_on'=>self::date($data[$f]??null),'returned_on'=>self::date($data[$f]??null,true),'previous_minor','current_minor'=>self::integer($data[$f]??null,0,2000000000),'items'=>$data[$f]??null,'notes','description'=>self::text($data[$f]??'',4000,false,true),default=>self::text($data[$f]??null,$f==='currency'?3:191,$f!=='tax_identifier')};}
        if($kind==='equipment'&&$r['returned_on']!==null&&$r['returned_on']<$r['assigned_on'])throw new DomainException('issuer_hr_invalid');
        if($kind==='compensation'&&!in_array($r['currency'],['BRL','USD','EUR'],true))throw new DomainException('issuer_hr_invalid');
        if(array_key_exists('items',$r)){if(!is_array($r['items'])||!array_is_list($r['items'])||!$r['items']||count($r['items'])>50)throw new DomainException('issuer_hr_invalid');foreach($r['items'] as &$v){if(!is_array($v))throw new DomainException('issuer_hr_invalid');self::fields($v,['text','done']);$v=['text'=>self::text($v['text']??null,250,true),'done'=>self::integer($v['done']??0,0,1)];}unset($v);}
        return $r;
    }
    private function record(int $i,int $id):array
    {
        $r=$this->row($i,'record',$id);$this->boundProof($i,'record',$id,$r);$data=self::data($r,'data_json','data_hash');if($this->recordInput($r['kind'],$data)!==$data)throw new RuntimeException('issuer_hr_integrity_failed');return $r;
    }
    public function saveRecord($issuer,$actor,$employee,string $kind,?int $id,$revision,array $data,string $key):int
    {
        $employee=IssuerPolicy::id($employee);$r=$this->recordInput($kind,$data);$perms=array_merge(self::permissions('hr_sensitive','view'),[['hr_sensitive','edit']]);
        return $this->request($issuer,$actor,$perms,'record_saved',$key,[$employee,$kind,$id,$revision,$r],function(int $i,int $a)use($employee,$kind,$id,$revision,$r):array{
            $this->employee($i,$employee,true);$json=self::json($r);$changes=['data_json'=>$json,'data_hash'=>hash('sha256',$json),'updated_at'=>self::now()];
            if($id===null){$this->limit($i,'record',200,$employee);$id=$this->insert('company_hr_records',['issuer_id'=>$i,'employee_id'=>$employee,'kind'=>$kind,'state'=>$kind==='dependent'?'pending':'active','actor_id'=>$a,'revision'=>1,'created_at'=>self::now()]+$changes);}
            else{$old=$this->record($i,$id);if((int)$old['employee_id']!==$employee||$old['kind']!==$kind||!in_array($old['state'],['active','pending'],true))throw new DomainException('issuer_hr_state');$this->updateRow('company_hr_records',$old,$changes+['revision'=>$this->revision($old,$revision)]);}
            return ['record',$id,$id];
        });
    }
    public function recordState($issuer,$actor,$id,$revision,string $state,string $key):int
    {
        $id=IssuerPolicy::id($id);if(!in_array($state,['approved','rejected','void'],true))throw new DomainException('issuer_hr_invalid');$perms=array_merge(self::permissions('hr_sensitive','view'),[['hr_sensitive',$state==='void'?'edit':'approve']]);
        return $this->request($issuer,$actor,$perms,'record_state',$key,[$id,$revision,$state],function(int $i)use($id,$revision,$state):array{
            $r=$this->record($i,$id);$this->employee($i,(int)$r['employee_id'],true);if($r['state']==='void'||($state!=='void'&&($r['kind']!=='dependent'||$r['state']!=='pending')))throw new DomainException('issuer_hr_state');
            $this->updateRow('company_hr_records',$r,['state'=>$state,'revision'=>$this->revision($r,$revision),'updated_at'=>self::now()]);return ['record',$id,$id];
        });
    }
    public function records($issuer,$actor,$employee):array
    {
        $employee=IssuerPolicy::id($employee);return $this->transaction($issuer,$actor,self::permissions('hr_sensitive','view'),'records',null,function(int $i)use($employee):array{
            $this->employee($i,$employee);$rows=$this->observedRows('SELECT id FROM '.$this->p.'company_hr_records WHERE issuer_id=? AND employee_id=? ORDER BY id LIMIT 201',[$i,$employee]);if(count($rows)>200)throw new DomainException('issuer_hr_limit');$out=[];
            foreach($rows as $v){$r=$this->record($i,(int)$v['id']);$data=self::data($r,'data_json','data_hash');unset($r['data_json'],$r['data_hash']);$out[]=$r+['data'=>$data];}return $out;
        });
    }
}
