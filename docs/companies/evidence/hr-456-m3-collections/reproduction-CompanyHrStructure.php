<?php

declare(strict_types=1);

trait CompanyHrStructure
{
    private function structureInput(string $kind,array $input):array
    {
        $extra=match($kind){'unit'=>['parent_id'],'position'=>['unit_id','description'],'workplace'=>['address'],'contract_type'=>['description'],default=>throw new DomainException('issuer_hr_invalid')};
        self::fields($input,array_merge(['name','active'],$extra));$r=['name'=>self::text($input['name']??null,120,true),'active'=>self::integer($input['active']??1,0,1)];
        foreach($extra as $field)$r[$field]=match($field){'parent_id'=>empty($input[$field])?null:self::integer($input[$field],1),'unit_id'=>self::integer($input[$field]??null,1),'address'=>self::text($input[$field]??'',500),default=>self::text($input[$field]??'',4000,false,true)};
        return $r;
    }
    private function activeReference(int $i,string $kind,int $id):array
    {
        $r=$this->row($i,$kind,$id);$this->boundProof($i,$kind,$id,$r);if((int)($r['active']??0)!==1)throw new DomainException('issuer_hr_reference_denied');return $r;
    }
    private function structureRefs(int $i,string $kind,?int $id,array $r):void
    {
        if($kind==='unit'){
            $parent=$r['parent_id'];$seen=[];while($parent!==null){if($parent===$id||isset($seen[$parent])||count($seen)>=64)throw new DomainException('issuer_hr_cycle');$seen[$parent]=true;$v=$this->activeReference($i,'unit',$parent);$parent=$v['parent_id']===null?null:(int)$v['parent_id'];}
        }elseif($kind==='position')$this->activeReference($i,'unit',$r['unit_id']);
        if($id!==null&&(int)$r['active']===0){
            $checks=match($kind){'unit'=>[['company_hr_units','parent_id','active=1'],['company_hr_positions','unit_id','active=1'],['company_hr_employees','unit_id',"state<>'terminated'"]],'position'=>[['company_hr_employees','position_id',"state<>'terminated'"]],'workplace'=>[['company_hr_employees','workplace_id',"state<>'terminated'"]],'contract_type'=>[['company_hr_contracts','type_id',"state='active'"]]};
            foreach($checks as [$t,$field,$filter])if($this->rows('SELECT id FROM '.$this->p.$t.' WHERE issuer_id=? AND '.$field.'=? AND '.$filter.' LIMIT 1',[$i,$id]))throw new DomainException('issuer_hr_reference_in_use');
        }
    }
    public function saveStructure($issuer,$actor,string $kind,?int $id,$revision,array $input,string $key):int
    {
        $r=$this->structureInput($kind,$input);
        return $this->request($issuer,$actor,self::permissions('hr','configure'),'structure_'.$kind,$key,[$id,$revision,$r],function(int $i,int $a)use($kind,$id,$revision,$r):array{
            $this->structureRefs($i,$kind,$id,$r);$now=self::now();
            if($id===null){$this->limit($i,$kind,500);$id=$this->insert(self::OBJECTS[$kind],['issuer_id'=>$i]+$r+['revision'=>1,'created_at'=>$now,'updated_at'=>$now]);}
            else{$old=$this->row($i,$kind,$id);$this->boundProof($i,$kind,$id,$old);$next=$this->revision($old,$revision);$this->updateRow(self::OBJECTS[$kind],$old,$r+['revision'=>$next,'updated_at'=>$now]);}
            $this->validators[]=function()use($i,$kind,$id,$r):void{$this->structureRefs($i,$kind,$id,$r);};return [$kind,$id,$id];
        });
    }
    public function structure($issuer,$actor):array
    {
        return $this->transaction($issuer,$actor,self::permissions(),'structure_read',null,function(int $i):array{
            $out=[];foreach(['unit','position','workplace','contract_type'] as $kind){$rows=$this->rows('SELECT * FROM '.$this->p.self::OBJECTS[$kind].' WHERE issuer_id=? ORDER BY id LIMIT 501',[$i]);if(count($rows)>500)throw new DomainException('issuer_hr_limit');foreach($rows as $r){$this->boundProof($i,$kind,(int)$r['id'],$r);$this->remember(self::OBJECTS[$kind],['id'=>$r['id'],'issuer_id'=>$i],$r);}$out[$kind]=$rows;}return $out;
        });
    }
    public function orgChart($issuer,$actor):array
    {
        return $this->transaction($issuer,$actor,self::permissions(),'org_chart',null,function(int $i):array{
            $rows=$this->rows('SELECT id FROM '.$this->p.'company_hr_employees WHERE issuer_id=? AND state<>? ORDER BY id LIMIT 1001',[$i,'terminated']);if(count($rows)>1000)throw new DomainException('issuer_hr_limit');$out=[];
            foreach($rows as $r){$employee=$this->employee($i,(int)$r['id'],true);$out[]=$this->publicEmployee($employee);}
            return ['employees'=>$out,'count'=>count($out)];
        });
    }
    public function saveArticle($issuer,$actor,?int $id,$revision,array $input,string $key):int
    {
        self::fields($input,['title','body','published']);$r=['title'=>self::text($input['title']??null,191,true),'body'=>self::text($input['body']??null,10000,true,true),'published'=>self::integer($input['published']??0,0,1)];
        return $this->request($issuer,$actor,self::permissions('hr','configure'),'article_saved',$key,[$id,$revision,$r],function(int $i)use($id,$revision,$r):array{
            if($id===null){$this->limit($i,'article',500);$id=$this->insert('company_hr_articles',['issuer_id'=>$i]+$r+['revision'=>1,'created_at'=>self::now(),'updated_at'=>self::now()]);}
            else{$old=$this->row($i,'article',$id);$this->boundProof($i,'article',$id,$old);$this->updateRow('company_hr_articles',$old,$r+['revision'=>$this->revision($old,$revision),'updated_at'=>self::now()]);}return ['article',$id,$id];
        });
    }
    public function articles($issuer,$actor,bool $publishedOnly=false):array
    {
        return $this->transaction($issuer,$actor,$publishedOnly?[]:self::permissions(),'articles',null,function(int $i,int $a)use($publishedOnly):array{
            if($publishedOnly)$this->ownEmployee($i,$a);$rows=$this->rows('SELECT * FROM '.$this->p.'company_hr_articles WHERE issuer_id=?'.($publishedOnly?' AND published=1':'').' ORDER BY id LIMIT 501',[$i]);if(count($rows)>500)throw new DomainException('issuer_hr_limit');foreach($rows as $r){$this->boundProof($i,'article',(int)$r['id'],$r);$this->remember('company_hr_articles',['id'=>$r['id'],'issuer_id'=>$i],$r);}return $rows;
        });
    }
}
