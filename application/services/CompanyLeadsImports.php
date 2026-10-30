<?php

declare(strict_types=1);

/** Bounded, explicit preview/commit. No background adoption or native global import. */
trait CompanyLeadsImports
{
    private function csvRows(string $filename,string $bytes,int $stage,int $source):array
    {
        if(strlen($bytes)<10||strlen($bytes)>262144||strlen($filename)>191||$filename===''||strtolower(pathinfo($filename,PATHINFO_EXTENSION))!=='csv'||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$filename)||preg_match('//u',$filename)!==1||preg_match('//u',$bytes)!==1||str_contains($bytes,"\0")||!in_array((new finfo(FILEINFO_MIME_TYPE))->buffer($bytes),['text/plain','text/csv','application/csv'],true))throw new DomainException('company_lead_import_invalid');
        if(str_starts_with($bytes,"\xef\xbb\xbf"))$bytes=substr($bytes,3);$stream=fopen('php://temp','w+b');if(!$stream)throw new RuntimeException('issuer_lead_import_unavailable');
        try{if(fwrite($stream,$bytes)!==strlen($bytes))throw new RuntimeException('issuer_lead_import_unavailable');rewind($stream);$header=fgetcsv($stream,0,',','"','');if($header!==self::CSV_FIELDS)throw new DomainException('company_lead_import_invalid');$rows=[];while(($line=fgetcsv($stream,0,',','"',''))!==false){if($line===[null])continue;if(count($line)!==6||count($rows)>=100)throw new DomainException('company_lead_import_invalid');$raw=array_combine(self::CSV_FIELDS,$line);$r=$this->input($raw+['stage_id'=>$stage,'source_id'=>$source]);$row=[];foreach(self::CSV_FIELDS as $field)$row[$field]=$r[$field];$rows[]=$row;}if(!$rows)throw new DomainException('company_lead_import_invalid');return $rows;}finally{fclose($stream);}
    }
    private function importPayload(array $batch):array
    {
        if(!hash_equals($batch['preview_hash'],hash('sha256',$batch['payload_json'])))throw new RuntimeException('issuer_lead_import_invalid');$payload=json_decode($batch['payload_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||array_keys($payload)!==['stage_id','stage_revision','source_id','source_revision','origin','rows']||!is_array($payload['rows'])||!array_is_list($payload['rows'])||count($payload['rows'])!== (int)$batch['row_count']||count($payload['rows'])<1||count($payload['rows'])>100)throw new RuntimeException('issuer_lead_import_invalid');
        $origin=$this->api===null?null:[$this->api['token_id'],$this->api['scope']];if($payload['origin']!==$origin)throw new DomainException('company_lead_import_origin_denied');return $payload;
    }
    public function previewImport($issuer,$actor,string $filename,string $bytes,$stage,$source,string $key):array
    {
        $stage=IssuerPolicy::id($stage);$source=IssuerPolicy::id($source);$rows=$this->csvRows($filename,$bytes,$stage,$source);$contentHash=hash('sha256',$bytes);
        return $this->request($issuer,$actor,'create','import_previewed',$key,[$filename,$contentHash,$stage,$source],function(int $i,int $a)use($key,$contentHash,$stage,$source,$rows):array{$s=$this->related($i,'stage',$stage);$o=$this->related($i,'source',$source);$origin=$this->api===null?null:[$this->api['token_id'],$this->api['scope']];$payload=json_encode(['stage_id'=>$stage,'stage_revision'=>(int)$s['revision'],'source_id'=>$source,'source_revision'=>(int)$o['revision'],'origin'=>$origin,'rows'=>$rows],JSON_THROW_ON_ERROR);$hash=hash('sha256',$payload);$id=substr(hash('sha256','company-lead-import:'.$key),0,32);
            $this->insert('company_lead_imports',['batch_id'=>$id,'issuer_id'=>$i,'actor_id'=>$a,'content_hash'=>$contentHash,'payload_json'=>$payload,'preview_hash'=>$hash,'state'=>'previewed','row_count'=>count($rows),'result_json'=>null,'created_at'=>self::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+900),'committed_at'=>null],'batch_id');return ['import',$id,['batch_id'=>$id,'preview_hash'=>$hash,'row_count'=>count($rows)]];});
    }
    private static function batchId(string $id):void{if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('company_lead_import_invalid');}
    public function importBatch($issuer,$actor,string $id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::batchId($id);
        return $this->transaction($issuer,$actor,'create','import_read',null,function()use($issuer,$actor,$id):array{$r=$this->rows('SELECT * FROM '.$this->p.'company_lead_imports WHERE batch_id=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$id,$issuer,$actor])[0]??null;if(!$r)throw new DomainException('company_lead_access_denied');$payload=$this->importPayload($r);$this->remember('company_lead_imports',['batch_id'=>$id],$r);return ['batch_id'=>$id,'preview_hash'=>$r['preview_hash'],'row_count'=>(int)$r['row_count'],'state'=>$r['state'],'expires_at'=>$r['expires_at'],'rows'=>$payload['rows'],'stage_id'=>$payload['stage_id'],'source_id'=>$payload['source_id'],'result'=>$r['result_json']===null?null:json_decode($r['result_json'],true,32,JSON_THROW_ON_ERROR)];});
    }
    public function commitImport($issuer,$actor,string $id,string $previewHash,string $key):array
    {
        self::batchId($id);self::key($previewHash);
        return $this->request($issuer,$actor,'create','import_committed',$key,[$id,$previewHash],function(int $i,int $a)use($id,$previewHash):array{$r=$this->rows('SELECT * FROM '.$this->p.'company_lead_imports WHERE batch_id=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$id,$i,$a])[0]??null;if(!$r)throw new DomainException('company_lead_access_denied');if($r['state']!=='previewed'||$r['expires_at']<=self::now()||!hash_equals($r['preview_hash'],$previewHash))throw new DomainException('company_lead_import_stale');$payload=$this->importPayload($r);$stage=$this->related($i,'stage',self::integer($payload['stage_id'],1));$source=$this->related($i,'source',self::integer($payload['source_id'],1));if((int)$stage['revision']!==$payload['stage_revision']||(int)$source['revision']!==$payload['source_revision'])throw new DomainException('company_lead_import_stale');
            $ids=[];foreach($payload['rows'] as $row){if(!is_array($row)||array_keys($row)!==self::CSV_FIELDS)throw new RuntimeException('issuer_lead_import_invalid');$input=$this->input($row+['stage_id'=>$payload['stage_id'],'source_id'=>$payload['source_id']]);$lead=$this->createRow($i,$a,$input);$this->audit($i,$a,'import_row_created','lead',(string)$lead);$ids[]=$lead;}$this->updateRow('company_lead_imports',$r,['state'=>'committed','result_json'=>json_encode($ids,JSON_THROW_ON_ERROR),'committed_at'=>self::now()],'batch_id');return ['import',$id,$ids];});
    }
    private static function spreadsheetCell(string $value):string{return preg_match('/^\s*[=+\-@]/u',$value)?"'".$value:$value;}
    /** Export contact fields only, as a documented CSV; quoting alone does not stop formulas. */
    public function exportCsv($issuer,$actor,string $search='',string $state='active'):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$search=self::text($search,191);if(!in_array($state,['active','archived','all'],true))throw new DomainException('company_lead_invalid_input');
        return $this->transaction($issuer,$actor,'view','export',null,function()use($issuer,$search,$state):string{$where='issuer_id=?';$args=[$issuer];if($state!=='all'){$where.=' AND archived=?';$args[]=$state==='archived'?1:0;}$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where.=" AND (name LIKE ? ESCAPE '!' OR company LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";array_push($args,$needle,$needle,$needle);$rows=$this->rows('SELECT * FROM '.$this->p.'company_leads WHERE '.$where.' ORDER BY id LIMIT 1001',$args);if(count($rows)>1000)throw new DomainException('company_lead_export_limit');$stream=fopen('php://temp','w+b');if(!$stream)throw new RuntimeException('issuer_lead_export_unavailable');try{if(fputcsv($stream,self::CSV_FIELDS,',','"','')===false)throw new RuntimeException('issuer_lead_export_unavailable');foreach($rows as $r){$this->remember('company_leads',['id'=>$r['id']],$r);$line=[];foreach(self::CSV_FIELDS as $f)$line[]=self::spreadsheetCell($r[$f]);if(fputcsv($stream,$line,',','"','')===false)throw new RuntimeException('issuer_lead_export_unavailable');}rewind($stream);$csv=stream_get_contents($stream);if($csv===false)throw new RuntimeException('issuer_lead_export_unavailable');return $csv;}finally{fclose($stream);}});
    }
}
