<?php

declare(strict_types=1);

/** Owned boundary and exact readbacks shared by every HR operation. */
trait CompanyHrStorage
{
    private array $frames=[];
    private array $validators=[];
    private ?array $api=null;

    public function tables():array
    {
        return array_merge(array_keys(CompanyHrSchema::definitions($this->p)),['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit','company_api_tokens','company_api_token_requests']);
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function now():string{return gmdate('Y-m-d H:i:s');}
    private static function integer($v,int $min=0,int $max=2147483647):int
    {
        if(!(is_int($v)||(is_string($v)&&preg_match('/^(0|[1-9][0-9]*)$/D',$v)))||(float)$v>$max||(int)$v<$min)throw new DomainException('issuer_hr_invalid');return (int)$v;
    }
    private static function text($v,int $max,bool $required=false,bool $multiline=false):string
    {
        if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/':'/[\x00-\x1f\x7f]/',$v))throw new DomainException('issuer_hr_invalid');
        $v=trim($v);if($required&&$v==='')throw new DomainException('issuer_hr_invalid');return $v;
    }
    private static function fields(array $in,array $fields):void{if(array_diff(array_keys($in),$fields))throw new DomainException('issuer_hr_invalid');}
    private static function date($v,bool $nullable=false):?string
    {
        if($nullable&&($v===null||$v===''))return null;
        if(!is_string($v)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$v))throw new DomainException('issuer_hr_invalid');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d')!==$v||(int)$d->format('Y')<1900||(int)$d->format('Y')>2199)throw new DomainException('issuer_hr_invalid');return $v;
    }
    private static function canonical($v)
    {
        if(!is_array($v))return $v===null?null:(string)$v;
        if(!array_is_list($v))ksort($v);return array_map([self::class,'canonical'],$v);
    }
    private static function json($v):string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    private static function digest($v):string{return hash('sha256',self::json(self::canonical($v)));}
    private static function data(array $row,string $field,string $hash):array
    {
        if(!is_string($row[$field]??null)||!hash_equals(hash('sha256',$row[$field]),(string)($row[$hash]??'')))throw new RuntimeException('issuer_hr_integrity_failed');
        try{$v=json_decode($row[$field],true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('issuer_hr_integrity_failed');}
        if(!is_array($v)||self::json($v)!==$row[$field])throw new RuntimeException('issuer_hr_integrity_failed');return $v;
    }
    private function authorize(int $i,int $a,array $permissions):void
    {
        if(!$this->acl->member($i,$a))throw new DomainException('issuer_hr_access_denied');
        foreach($permissions as [$feature,$cap])if(!$this->acl->can($i,$a,$feature,$cap))throw new DomainException('issuer_hr_access_denied');
        if($this->api!==null){if($this->api['issuer_id']!==$i||$this->api['actor_id']!==$a)throw new DomainException('company_api_scope_denied');(new CompanyApiTokens($this->db,$this->p))->guard($this->api['secret'],$this->api['scope'],$i,$a);}
    }
    private static function permissions(string $feature='hr',string $cap='view'):array{return array_values(array_unique([['hr','view'],[$feature,$cap]],SORT_REGULAR));}
    private function transaction($issuer,$actor,array $permissions,string $operation,?string $key,callable $work)
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$this->authorize($i,$a,$permissions);
        CompanyHrSchema::assertValid($this->db,$this->p);CompanyApiSchema::assertValid($this->db,$this->p);$this->frames=[];$this->validators=[];
        try{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$permissions,$operation,$key,$work){
            $this->acl->lockFinancialIdentity($i,$a);$this->authorize($i,$a,$permissions);$result=$work($i,$a);
            $this->authorize($i,$a,$permissions);
            if($this->api!==null)(new CompanyApiTokens($this->db,$this->p))->usedInOwnedTransaction($this->api['secret'],$this->api['scope'],$i,$a,'hr_'.$operation,$key);
            $this->authorize($i,$a,$permissions);foreach($this->validators as $check)$check();$this->assertFrames();return $result;
        });}finally{$this->frames=[];$this->validators=[];}
    }
    private function confirm(string $table,array $where,array $want):void
    {
        $columns=[];foreach($want as $k=>$v)$columns[]=$k==='content_sha256'?'SHA2(content,256) AS content_sha256':'`'.$k.'`';
        $parts=[];foreach($where as $k=>$v)$parts[]='`'.$k.'`=?';$r=$this->rows('SELECT '.implode(',',$columns).' FROM `'.$this->p.$table.'` WHERE '.implode(' AND ',$parts),array_values($where));
        if(count($r)!==1||self::canonical($r[0])!==self::canonical($want))throw new RuntimeException('issuer_hr_write_unconfirmed');
    }
    private function remember(string $table,array $where,array $want,bool $replace=false):void
    {
        if(array_key_exists('content',$want)){$want['content_sha256']=hash('sha256',$want['content']);unset($want['content']);}
        $key=$table.':'.self::json(self::canonical($where));if(isset($this->frames[$key])&&!$replace){[$t,$w,$v]=$this->frames[$key];$this->confirm($t,$w,$v);$this->frames[$key]=[$table,$where,array_replace($v,$want)];}else $this->frames[$key]=[$table,$where,$want];$this->confirm($table,$where,$want);
    }
    private function assertFrames():void{foreach($this->frames as [$t,$w,$v])$this->confirm($t,$w,$v);}
    private function insert(string $table,array $data,string $pk='id')
    {
        IssuerStorage::query($this->db,'INSERT INTO `'.$this->p.$table.'` (`'.implode('`,`',array_keys($data)).'`) VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
        $id=$data[$pk]??(int)$this->db->insert_id();if($id===0||$id==='')throw new RuntimeException('issuer_hr_write_unconfirmed');$this->remember($table,[$pk=>$id],[$pk=>$id]+$data);return $id;
    }
    private function updateRow(string $table,array $old,array $changes,string $pk='id'):array
    {
        $parts=[];foreach($changes as $field=>$unused)$parts[]='`'.$field.'`=?';
        IssuerStorage::query($this->db,'UPDATE `'.$this->p.$table.'` SET '.implode(',',$parts).' WHERE `'.$pk.'`=? AND issuer_id=?',array_merge(array_values($changes),[$old[$pk],$old['issuer_id']]));
        $want=array_replace($old,$changes);$this->remember($table,[$pk=>$old[$pk],'issuer_id'=>$old['issuer_id']],$want,true);
        // The same row may have been first captured without the owner in the key.
        $other=$table.':'.self::json(self::canonical([$pk=>$old[$pk]]));if(isset($this->frames[$other]))$this->frames[$other]=[$table,[$pk=>$old[$pk]],$want];return $want;
    }
    private function row(int $issuer,string $kind,int $id):array
    {
        $table=self::OBJECTS[$kind]??throw new DomainException('issuer_hr_invalid');$select=$kind==='file'?'id,issuer_id,employee_id,purpose,object_id,name,mime,size,sha256,SHA2(content,256) AS content_sha256,hidden,actor_id,revision,created_at,updated_at':'*';
        $r=$this->rows('SELECT '.$select.' FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if(!$r)throw new DomainException('issuer_hr_access_denied');$this->remember($table,['id'=>$id,'issuer_id'=>$issuer],$r);return $r;
    }
    private function revision(array $r,$revision):int
    {
        $n=self::integer($revision,1);if($n!==(int)$r['revision'])throw new DomainException('issuer_hr_stale');if($n>=2147483647)throw new DomainException('issuer_hr_limit');return $n+1;
    }
    private function origin():?array{return $this->api===null?null:['token_id'=>$this->api['token_id'],'scope'=>$this->api['scope']];}
    private function validateOrigin(?array $origin,int $issuer,int $actor):void
    {
        if($origin===null)return;
        if(array_keys($origin)!==['token_id','scope']||!is_string($origin['token_id'])||!is_string($origin['scope']))throw new RuntimeException('issuer_hr_integrity_failed');
        $guard=function()use($origin,$issuer,$actor):void{(new CompanyApiTokens($this->db,$this->p))->guardOriginInOwnedTransaction($origin['token_id'],$origin['scope'],$issuer,$actor);};$guard();$this->validators[]=$guard;
    }
    private function audit(int $issuer,int $actor,string $operation,int $subject,array $metadata,?string $createdAt=null):int
    {
        return $this->insert('issuers_audit',['actor_id'=>$actor,'event'=>'company_hr_'.$operation,'issuer_id'=>$issuer,'subject_id'=>$subject,'created_at'=>$createdAt??self::now(),'metadata_json'=>self::json($metadata)]);
    }
    private static function objectProof(string $kind,array $r):string
    {
        unset($r['revision'],$r['updated_at']);
        if($kind==='assignment'){$r['cancelled']=$r['state']==='cancelled';unset($r['state'],$r['attempt_count']);}
        return self::digest([$kind,$r]);
    }
    private function proofRow(int $i,string $kind,array $r):array
    {
        if($kind==='employee'){
            $p=$this->rows('SELECT data_hash,SHA2(data_json,256) actual_hash FROM '.$this->p.'company_hr_profiles WHERE issuer_id=? AND employee_id=?',[$i,$r['id']])[0]??null;
            if(!$p||!hash_equals($p['data_hash'],$p['actual_hash']))throw new RuntimeException('issuer_hr_integrity_failed');$r['profile_hash']=$p['data_hash'];
        }
        return $r;
    }
    /** Immutable contracts are anchored to an audited request, not a self-hash alone. */
    private function boundProof(int $i,string $kind,int $id,array $r):void
    {
        $receipt=$this->rows('SELECT * FROM '.$this->p.'company_hr_requests WHERE issuer_id=? AND object_kind=? AND object_id=? ORDER BY audit_id DESC LIMIT 1',[$i,$kind,$id])[0]??null;
        if(!$receipt)throw new RuntimeException('issuer_hr_integrity_failed');$result=self::data($receipt,'result_json','result_hash');
        if(!is_string($result['proof']??null)||!hash_equals($result['proof'],self::objectProof($kind,$this->proofRow($i,$kind,$r))))throw new RuntimeException('issuer_hr_integrity_failed');
        $meta=['key'=>$receipt['request_key'],'input_hash'=>$receipt['input_hash'],'object_kind'=>$kind,'object_id'=>$id,'result_hash'=>$receipt['result_hash']];
        $audit=['actor_id'=>$receipt['actor_id'],'event'=>'company_hr_'.$receipt['operation'],'issuer_id'=>$i,'subject_id'=>$id,'created_at'=>$receipt['created_at'],'metadata_json'=>self::json($meta)];
        $this->remember('issuers_audit',['id'=>$receipt['audit_id']],$audit);$this->remember('company_hr_requests',['request_key'=>$receipt['request_key']],$receipt);
    }
    private function writeReceipt(int $i,int $a,string $operation,string $key,string $hash,string $kind,int $id,$value):void
    {
        $proof=$this->proofRow($i,$kind,$this->row($i,$kind,$id));$json=self::json(['value'=>$value,'proof'=>self::objectProof($kind,$proof)]);$resultHash=hash('sha256',$json);$now=self::now();
        $meta=['key'=>$key,'input_hash'=>$hash,'object_kind'=>$kind,'object_id'=>$id,'result_hash'=>$resultHash];$audit=$this->audit($i,$a,$operation,$id,$meta,$now);
        $this->insert('company_hr_requests',['request_key'=>$key,'issuer_id'=>$i,'actor_id'=>$a,'operation'=>$operation,'input_hash'=>$hash,'object_kind'=>$kind,'object_id'=>$id,'result_json'=>$json,'result_hash'=>$resultHash,'audit_id'=>$audit,'created_at'=>$now],'request_key');
    }
    private function request($issuer,$actor,array $permissions,string $operation,string $key,array $input,callable $work,?callable $gate=null)
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||strlen($operation)>48)throw new DomainException('issuer_hr_invalid');$hash=self::digest([$operation,$input,$this->origin()]);
        return $this->transaction($issuer,$actor,$permissions,$operation,$key,function(int $i,int $a)use($operation,$key,$hash,$work,$gate){
            if($gate!==null)$gate($i,$a);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_hr_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){
                if((int)$receipt['issuer_id']!==$i||(int)$receipt['actor_id']!==$a||$receipt['operation']!==$operation||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('issuer_hr_request_conflict');
                $result=self::data($receipt,'result_json','result_hash');$r=$this->row($i,$receipt['object_kind'],(int)$receipt['object_id']);$this->boundProof($i,$receipt['object_kind'],(int)$receipt['object_id'],$r);
                if($operation==='employees_imported'){$value=$result['value'];if(!is_array($value)||!is_array($value['ids']??null)||!array_is_list($value['ids'])||($value['count']??null)!==count($value['ids'])||count($value['ids'])<1||count($value['ids'])>100)throw new RuntimeException('issuer_hr_integrity_failed');foreach($value['ids'] as $employeeId){$employee=$this->employee($i,IssuerPolicy::id($employeeId));$this->staffReference($i,(int)$employee['staff_id']);}}
                $meta=['key'=>$key,'input_hash'=>$hash,'object_kind'=>$receipt['object_kind'],'object_id'=>(int)$receipt['object_id'],'result_hash'=>$receipt['result_hash']];
                $audit=['actor_id'=>$a,'event'=>'company_hr_'.$operation,'issuer_id'=>$i,'subject_id'=>(int)$receipt['object_id'],'created_at'=>$receipt['created_at'],'metadata_json'=>self::json($meta)];
                $this->remember('issuers_audit',['id'=>$receipt['audit_id']],$audit);$this->remember('company_hr_requests',['request_key'=>$key],$receipt);return $result['value'];
            }
            [$kind,$id,$value]=$work($i,$a);$this->writeReceipt($i,$a,$operation,$key,$hash,$kind,$id,$value);return $value;
        });
    }
    private function limit(int $issuer,string $kind,int $maximum,?int $employee=null):void
    {
        $where=$employee===null?'':' AND employee_id=?';$args=[$issuer];if($employee!==null)$args[]=$employee;
        if((int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.self::OBJECTS[$kind].' WHERE issuer_id=?'.$where,$args)[0]['n']>=$maximum)throw new DomainException('issuer_hr_limit');
    }
}
