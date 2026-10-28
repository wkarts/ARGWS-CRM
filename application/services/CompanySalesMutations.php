<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesMutationsSchema.php';

/** Reviewed mutations of canonical private sales documents; never writes a native invoice. */
trait CompanySalesMutations
{
    private function mutationsReady():bool{return CompanySalesMutationsSchema::present($this->db,$this->prefix);}
    private function requireMutations():void{if(!$this->mutationsReady())throw new DomainException('company_sales_mutations_unavailable');}
    protected function participantTables():array
    {
        $tables=array_merge(parent::participantTables(),['itemable','item_tax','invoicepaymentrecords','credits','company_financial_events']);if($this->mutationsReady())$tables=array_merge($tables,array_keys(CompanySalesMutationsSchema::definitions()));return $tables;
    }
    /** Read-only final commit frame. Audits/triggers are part of the same transaction. */
    private function boundaryReferences(int $issuer,int $actor,array $documents):array
    {
        $clients=[];$currencies=[];foreach($documents as$r){$clients[(int)$r['client_id']]=true;$currencies[(int)$r['currency']]=true;}ksort($clients);ksort($currencies);
        $queries=[['issuers_registry','id=?',[$issuer]],['staff','staffid=?',[$actor]],['issuers_staff','issuer_id=? AND staff_id=?',[$issuer,$actor]],['issuers_resource_permissions','issuer_id=? AND staff_id=? ORDER BY feature,capability',[$issuer,$actor]],['issuers_installation_identity','id=1',[]]];
        foreach(array_keys($clients)as$id){$queries[]=['clients','userid=?',[$id]];$queries[]=['issuers_clients','client_id=?',[$id]];$queries[]=['issuers_client_links','client_id=? AND issuer_id=?',[$id,$issuer]];}
        foreach(array_keys($currencies)as$id){$queries[]=['currencies','id=?',[$id]];$queries[]=['company_currency_units','currency_id=?',[$id]];}
        $names=array_keys(CompanySettingsIdentity::ALIASES);foreach(['company','email','documents','sales_documents','document_pdf','sales_pdf','pdf_typography']as$group)$names=array_merge($names,CompanySettingsCatalog::fields($group));$names=array_values(array_unique($names));$queries[]=['options','name IN ('.implode(',',array_fill(0,count($names),'?')).') ORDER BY name',$names];
        $snapshot=[];foreach($queries as[$table,$where,$args])$snapshot[]=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE '.$where.' FOR UPDATE',$args);
        return ['queries'=>$queries,'snapshot'=>$snapshot];
    }
    private function boundarySequences(int $issuer):array
    {
        // Only the three canonical sales/conversion series participate; unrelated custom series do not.
        return $this->rows("SELECT issuer_id,series,next_number FROM {$this->prefix}issuers_sequences WHERE issuer_id=? AND series IN ('EST','PROP','INV') ORDER BY series FOR UPDATE",[$issuer]);
    }
    private function reservedSequences(array $before,int $issuer,string $series,int $number):array
    {
        $found=false;foreach($before as&$r)if($r['series']===$series){$r['next_number']=(string)($number+1);$found=true;}unset($r);if(!$found)$before[]=['issuer_id'=>$issuer,'series'=>$series,'next_number'=>$number+1];usort($before,fn($a,$b)=>strcmp($a['series'],$b['series']));return $before;
    }
    private static function boundaryRow(array $actual,array $expected,string $error='issuer_sales_write_unconfirmed'):void
    {
        // SQL drivers differ in numeric wire types; NULL and every persisted column remain exact.
        $normalize=static function(array $row):array{foreach($row as$key=>$value)$row[$key]=$value===null?null:(string)$value;ksort($row);return$row;};
        if($normalize($actual)!==$normalize($expected))throw new RuntimeException($error);
    }
    private function confirmBoundary(int $issuer,int $actor,array $references,array $documents,array $sequences,array $records=[]):void
    {
        foreach($references['queries']as$i=>[$table,$where,$args])if($this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE '.$where.' FOR UPDATE',$args)!==$references['snapshot'][$i])throw new RuntimeException('issuer_sales_write_unconfirmed');
        foreach($documents as$r)self::boundaryRow($this->owned($issuer,$actor,(int)$r['id'],true),$r);
        $actual=$this->boundarySequences($issuer);if(count($actual)!==count($sequences))throw new RuntimeException('issuer_sales_write_unconfirmed');foreach($actual as$i=>$r)self::boundaryRow($r,$sequences[$i]);
        foreach($records as$record){[$table,$where,$args,$expected]=$record;$rows=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE '.$where.' FOR UPDATE',$args);if($record[4]??false){if(count($rows)!==count($expected))throw new RuntimeException('issuer_sales_mutation_unconfirmed');foreach($rows as$i=>$row)self::boundaryRow($row,$expected[$i],'issuer_sales_mutation_unconfirmed');}else self::boundaryRow($rows[0]??[],$expected,'issuer_sales_mutation_unconfirmed');}
    }
    private function canonicalBoundary(int $issuer,int $actor,int $id,int $invoice,array $source):array
    {
        $key=hash('sha256','company-sales-invoice:'.$issuer.':'.$id);$this->auth($issuer,$actor,'invoices','create');$this->assertOriginOrReceipt($issuer,$actor,$this->decode($source['payload_json']),$source['origin_hash'],$this->decode($source['context_json']),$key);
        $receipt=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[$key])[0]??[];
        if((int)($receipt['invoice_id']??0)!==$invoice||(int)($receipt['issuer_id']??0)!==$issuer||(int)($receipt['actor_id']??0)!==$actor)throw new RuntimeException('issuer_sales_write_unconfirmed');
        $records=[['company_invoice_requests','request_key=?',[$key],$receipt]];
        foreach([['invoices','id'],['issuers_invoice_documents','invoice_id']]as[$table,$field]){$row=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE '.$field.'=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??[];if(!$row)throw new RuntimeException('issuer_sales_write_unconfirmed');$records[]=[$table,$field.'=? AND issuer_id=?',[$invoice,$issuer],$row];}
        // These are veto-only reads of the canonical writer's effects, never a second financial writer.
        foreach([['itemable',"rel_id=? AND rel_type='invoice' ORDER BY id",[$invoice],100],['item_tax',"rel_id=? AND rel_type='invoice' ORDER BY id",[$invoice],1000],['invoicepaymentrecords','invoiceid=? ORDER BY id',[$invoice],1000],['credits','invoice_id=? ORDER BY id',[$invoice],1000],['company_financial_events',"issuer_id=? AND source_type='invoice' AND source_id=? ORDER BY evidence_key",[$issuer,(string)$invoice],1000]]as[$table,$where,$args,$limit]){$rows=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE '.$where.' LIMIT '.($limit+1).' FOR UPDATE',$args);if(count($rows)>$limit)throw new RuntimeException('issuer_sales_write_unconfirmed');$records[]=[$table,$where.' LIMIT '.($limit+1),$args,$rows,true];}return$records;
    }
    private static function creationBinding(array $r,string $hash):array
    {
        return ['id'=>(int)$r['id'],'issuer_id'=>(int)$r['issuer_id'],'actor_id'=>(int)$r['actor_id'],'kind'=>$r['kind'],'number'=>(int)$r['number'],'client_id'=>(int)$r['client_id'],'currency'=>(int)$r['currency'],'origin_hash'=>$r['origin_hash'],'creation_key'=>$r['request_key'],'creation_hash'=>$hash,'created_at'=>$r['created_at']];
    }
    private function mutationAudit(int $issuer,int $actor,int $id,string $event,array $data):void
    {
        $json=self::encode($data);$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$id,'event'=>$event,'metadata_json'=>$json,'created_at'=>gmdate('Y-m-d H:i:s')]);$audit=(string)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$json]))throw new RuntimeException('issuer_sales_mutation_unconfirmed');
    }
    private function recordCreation(array $r,?int $recordingActor=null):void
    {
        $this->mutationAudit((int)$r['issuer_id'],$recordingActor??(int)$r['actor_id'],(int)$r['id'],'company_sales_creation_recorded',self::creationBinding($r,$r['input_hash']));
    }
    private static function revisionToken(array $r,int $revision):string
    {
        return self::hash([self::creationBinding($r,$r['input_hash']),$r['state'],(int)$r['conversion_actor_id'],(int)$r['invoice_id'],$revision]);
    }
    private static function revisionRecord(array $r):array
    {
        foreach(['issuer_id','document_id','actor_id','revision_no','creation_actor_id']as$field)$r[$field]=(int)$r[$field];return $r;
    }
    private function revisionHistory(array $r):array
    {
        if(!$this->mutationsReady())return ['number'=>0,'creation_hash'=>$r['input_hash'],'creation_payload'=>$r['payload_json']];
        IssuerTransaction::assertOwned($this->db,$this->prefix,array_keys(CompanySalesMutationsSchema::definitions()));
        $conn=$this->db->pdo??$this->db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$length=fn($field)=>$sqlite?'LENGTH(CAST('.$field.' AS BLOB))':'OCTET_LENGTH('.$field.')';
        $bytes=(int)$this->rows('SELECT COALESCE(SUM('.$length('before_payload_json').'+'.$length('after_payload_json').'),0) n FROM '.$this->prefix.'company_sales_revisions WHERE issuer_id=? AND document_id=?',[$r['issuer_id'],$r['id']])[0]['n'];if($bytes>8388608)throw new RuntimeException('issuer_sales_mutation_corrupt');
        $records=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_revisions WHERE issuer_id=? AND document_id=? ORDER BY revision_no LIMIT 1001',[$r['issuer_id'],$r['id']]);
        $creation=$this->rows("SELECT metadata_json FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_sales_creation_recorded' ORDER BY id LIMIT 2",[$r['issuer_id'],$r['id']]);
        $audits=$this->rows("SELECT actor_id,metadata_json FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_sales_revised' ORDER BY id LIMIT 1001",[$r['issuer_id'],$r['id']]);
        if(count($records)>1000||count($creation)>1||count($audits)!==count($records)||$records&&!$creation)throw new RuntimeException('issuer_sales_mutation_corrupt');
        $hash=$records?$records[0]['before_hash']:$r['input_hash'];$original=$records?$records[0]['before_payload_json']:$r['payload_json'];$payload=$original;
        if($creation&&$this->decode($creation[0]['metadata_json'])!==self::creationBinding($r,$hash))throw new RuntimeException('issuer_sales_mutation_corrupt');
        foreach($records as$index=>$raw){$record=self::revisionRecord($raw);$stored=$record['record_hash'];unset($record['record_hash']);$before=$this->decode($record['before_payload_json']);$after=$this->decode($record['after_payload_json']);$n=$index+1;
            $prior=$r;$prior['state']='open';$prior['invoice_id']=0;$prior['conversion_actor_id']=0;$prior['input_hash']=$hash;
            $review=['source_revision'=>$record['source_revision'],'reason'=>$record['reason'],'edit_confirmed'=>'1'];
            if($record['revision_no']!==$n||$record['creation_key']!==$r['request_key']||$record['creation_actor_id']!==(int)$r['actor_id']||$record['origin_hash']!==$r['origin_hash']||$record['before_payload_json']!==$payload||$record['before_hash']!==$hash||$hash!==self::hash([$r['kind'],$before])||$record['after_hash']!==self::hash([$r['kind'],$after])||$record['source_revision']!==self::revisionToken($prior,$index)||$record['form_hash']!==self::hash([$after,$review])||!hash_equals($stored,self::hash($record)))throw new RuntimeException('issuer_sales_mutation_corrupt');
            $audit=$audits[$index];if((int)$audit['actor_id']!==$record['actor_id']||$this->decode($audit['metadata_json'])!==['request_key'=>$record['request_key'],'record_hash'=>$stored,'revision'=>$n])throw new RuntimeException('issuer_sales_mutation_corrupt');
            $hash=$record['after_hash'];$payload=$record['after_payload_json'];
        }
        if($hash!==$r['input_hash']||$payload!==$r['payload_json'])throw new RuntimeException('issuer_sales_mutation_corrupt');
        return ['number'=>count($records),'creation_hash'=>$records?$records[0]['before_hash']:$r['input_hash'],'creation_payload'=>$original,'bytes'=>$bytes];
    }
    private static function mutationIntent(array $review,string $action):array
    {
        $allowed=$action==='edit'?['source_revision','reason','edit_confirmed']:['source_revision','destination_revision','reason','copy_confirmed'];
        if(array_diff(array_keys($review),$allowed)||!is_string($review['reason']??null)||strlen($review['reason'])>2000||trim($review['reason'])===''||preg_match('//u',$review['reason'])!==1||preg_match('/[\x00-\x1f\x7f]/',$review['reason'])||($review[$action.'_confirmed']??null)!=='1')throw new DomainException('company_sales_invalid');
        if(!is_string($review['source_revision']??null))throw new DomainException('company_sales_invalid');self::key($review['source_revision']);$out=['source_revision'=>$review['source_revision'],'reason'=>trim($review['reason']),$action.'_confirmed'=>'1'];if($action==='copy'){if(!is_string($review['destination_revision']??null))throw new DomainException('company_sales_invalid');self::key($review['destination_revision']);$out['destination_revision']=$review['destination_revision'];}return $out;
    }
    private function copyTagTables():array
    {
        require_once __DIR__.'/NativeCompanyResourceTags.php';
        $tables=NativeCompanyResourceTags::tableNames();foreach($tables as$table)if(!$this->db->table_exists($this->prefix.$table))return [];
        return $tables;
    }
    private function copyTags(int $issuer,int $actor,array $row):?array
    {
        if($row['kind']!=='estimates')return null;
        if($this->copyTagTables()===[]){if(($this->decode($row['payload_json'])['native_tags']??[])!==[])throw new DomainException('native_estimate_form_tags_unavailable');return null;}
        return (new NativeCompanyResourceTags($this->db,$this->prefix))->read($issuer,$actor,'estimate',NativeCompanyResourceId::encode('company_estimate',$row['id']));
    }
    private function copyCustomTables():array
    {
        if(!$this->db->table_exists($this->prefix.'company_custom_fields')||!$this->db->table_exists($this->prefix.'company_custom_field_values'))return [];
        require_once __DIR__.'/NativeCompanyCustomFields.php';return (new NativeCompanyCustomFields($this->db,$this->prefix))->tableNames();
    }
    private function copyCustomFields(int $issuer,int $actor,array $row):?array
    {
        if($row['kind']!=='estimates')return null;
        if($this->copyCustomTables()===[]){if(array_key_exists('native_custom_fields',$this->decode($row['payload_json'])))throw new DomainException('native_estimate_form_fields_unavailable');return null;}
        $fields=new NativeCompanyCustomFields($this->db,$this->prefix);$id=NativeCompanyResourceId::encode('company_estimate',$row['id']);
        $definitions=$fields->definitionsForResource($issuer,$actor,'estimate',$id,['active'=>1],'view');$values=$fields->storedInput($issuer,$actor,'estimate',$id);
        // Hidden or inactive data cannot be silently omitted from a reviewed copy.
        $all=$this->rows("SELECT COUNT(*) n FROM {$this->prefix}company_custom_field_values WHERE issuer_id=? AND source_kind='company_estimate' AND source_id=?",[$issuer,$row['id']]);
        if((int)($all[0]['n']??0)!==count($values))throw new DomainException('native_estimate_custom_copy_denied');
        return ['definitions'=>$definitions,'values'=>$values];
    }
    private static function copySourceRevision(array $row,int $number,?array $tags,?array $fields):string
    {
        $revision=self::revisionToken($row,$number);return $tags===null&&$fields===null?$revision:self::hash([$revision,$tags['revision']??null,$fields]);
    }
    public function review($issuer,$actor,$id,string $action,?array $destination=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);if(!in_array($action,['edit','copy'],true)||$action==='edit'&&$destination!==null)throw new DomainException('company_sales_invalid');$this->requireMutations();
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$action,$destination){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],$action==='edit'?'edit':'create');if($action==='edit'&&$r['state']!=='open'||$action==='copy'&&$r['state']==='converting')throw new DomainException('company_sales_state_conflict');$history=$this->revisionHistory($r);$payload=$destination===null?$this->decode($r['payload_json']):self::input($destination);if($action==='edit'&&array_key_exists('native_custom_fields',$payload))$this->copyCustomFields($issuer,$actor,$r);$tags=$action==='copy'?$this->copyTags($issuer,$actor,$r):null;$fields=$action==='copy'?$this->copyCustomFields($issuer,$actor,$r):null;if($tags!==null&&$destination===null){$labels=array_column($tags['tags'],'name');if($labels===[])unset($payload['native_tags']);else $payload['native_tags']=$labels;}if($fields!==null&&$destination===null&&(array_key_exists('native_custom_fields',$payload)||$fields['values']!==[]))$payload['native_custom_fields']=$fields['values'];$out=['id'=>$id,'kind'=>$r['kind'],'number'=>(int)$r['number'],'payload'=>$payload,'context'=>$this->decode($r['context_json']),'source_revision'=>$action==='copy'?self::copySourceRevision($r,$history['number'],$tags,$fields):self::revisionToken($r,$history['number']),'revision_number'=>$history['number']];
            if($action==='copy'){$this->assertNativeFields($issuer,$actor,$r['kind'],$payload);$current=$this->context($issuer,$actor,$payload['client_id'],$payload['currency'],$r['kind'],false,array_key_exists('native_fields',$payload));$out['destination_context']=$current;$out['destination_revision']=self::hash([$payload,$current]);}return$out;},$action==='copy'?array_values(array_unique(array_merge($this->copyTagTables(),$this->copyCustomTables()))):$this->copyCustomTables());
    }
    public function edit($issuer,$actor,$id,array $input,array $review,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);self::key($key);$payload=self::input($input);$intent=self::mutationIntent($review,'edit');$form=self::hash([$payload,$intent]);$this->requireMutations();
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$payload,$intent,$form,$key){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');$history=$this->revisionHistory($r);$old=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_revisions WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['document_id']!==$id||(int)$old['actor_id']!==$actor||$old['form_hash']!==$form)throw new DomainException('company_sales_conflict');return$id;}
            if($r['state']!=='open')throw new DomainException('company_sales_state_conflict');if(!hash_equals(self::revisionToken($r,$history['number']),$intent['source_revision']))throw new DomainException('company_sales_stale');if($history['number']>=1000)throw new DomainException('company_sales_revision_limit');
            if($payload['client_id']!==(int)$r['client_id']||$payload['currency']!==(int)$r['currency'])throw new DomainException('company_sales_recipient_denied');
            // The estimate tag catalogue is written separately. Until edits update both
            // records atomically, never allow an edit to change or discard the tags.
            $previousPayload=$this->decode($r['payload_json']);
            if(($previousPayload['native_tags']??[])!==($payload['native_tags']??[]))throw new DomainException('native_estimate_tag_edit_unavailable');
            if(($previousPayload['native_custom_fields']??[])!==($payload['native_custom_fields']??[]))throw new DomainException('native_estimate_custom_edit_unavailable');
            $this->assertNativeFields($issuer,$actor,$r['kind'],$payload);
            // An edit preserves the original fiscal snapshot; a reviewed copy can capture current identity.
            $this->assertOrigin($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']));$references=$this->boundaryReferences($issuer,$actor,[$r]);$sequences=$this->boundarySequences($issuer);
            if(!$this->rows("SELECT id FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_sales_creation_recorded'",[$issuer,$id]))$this->recordCreation($r,$actor);
            $after=self::encode($payload);if(($history['bytes']??0)+strlen($r['payload_json'])+strlen($after)>8388608)throw new DomainException('company_sales_revision_limit');$hash=self::hash([$r['kind'],$payload]);$record=['request_key'=>$key,'issuer_id'=>$issuer,'document_id'=>$id,'actor_id'=>$actor,'revision_no'=>$history['number']+1,'source_revision'=>$intent['source_revision'],'before_payload_json'=>$r['payload_json'],'after_payload_json'=>$after,'before_hash'=>$r['input_hash'],'after_hash'=>$hash,'origin_hash'=>$r['origin_hash'],'creation_key'=>$r['request_key'],'creation_actor_id'=>(int)$r['actor_id'],'reason'=>$intent['reason'],'form_hash'=>$form,'created_at'=>gmdate('Y-m-d H:i:s')];$record['record_hash']=self::hash($record);
            $amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_sales_documents SET payload_json=?,input_hash=?,total=? WHERE issuer_id=? AND id=?',[$after,$hash,$amounts['total'],$issuer,$id]);$this->insert('company_sales_revisions',$record);$expected=array_replace($r,['payload_json'=>$after,'input_hash'=>$hash,'total'=>$amounts['total']]);$expected['total']=$this->rows('SELECT total FROM '.$this->prefix.'company_sales_documents WHERE id=?',[$id])[0]['total'];if(CompanyInvoiceAmounts::minor((string)$expected['total'])!==CompanyInvoiceAmounts::minor($amounts['total']))throw new RuntimeException('issuer_sales_write_unconfirmed');$this->mutationAudit($issuer,$actor,$id,'company_sales_revised',['request_key'=>$key,'record_hash'=>$record['record_hash'],'revision'=>$record['revision_no']]);$saved=$this->owned($issuer,$actor,$id,true);if($saved['payload_json']!==$after||$saved['input_hash']!==$hash)throw new RuntimeException('issuer_sales_mutation_unconfirmed');$this->confirmBoundary($issuer,$actor,$references,[$expected],$sequences,[['company_sales_revisions','request_key=?',[$key],$record]]);$this->assertOrigin($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']));$this->assertNativeFields($issuer,$actor,$r['kind'],$payload);$this->auth($issuer,$actor,$r['kind'],'view');$this->auth($issuer,$actor,$r['kind'],'edit');return$id;});
    }
    public function copy($issuer,$actor,$id,array $input,array $review,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);self::key($key);$payload=self::input($input);$intent=self::mutationIntent($review,'copy');$form=self::hash([$payload,$intent]);$this->requireMutations();
        return$this->tx($issuer,$actor,function()use($issuer,$actor,$id,$payload,$intent,$form,$key){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'create');$history=$this->revisionHistory($r);$old=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_copy_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'source_document_id'=>$id]as$field=>$value)if((int)$old[$field]!==$value)throw new DomainException('company_sales_conflict');$record=$old;foreach(['issuer_id','actor_id','source_document_id','result_document_id']as$field)$record[$field]=(int)$record[$field];$stored=$record['record_hash'];unset($record['record_hash']);if($record['input_hash']!==$form||!hash_equals($stored,self::hash($record)))throw new DomainException('company_sales_conflict');$result=$this->owned($issuer,$actor,(int)$record['result_document_id'],true);$rh=$this->revisionHistory($result);if($result['request_key']!==$record['result_creation_key']||!hash_equals($record['result_hash'],self::hash(self::creationBinding($result,$rh['creation_hash']))))throw new RuntimeException('issuer_sales_mutation_corrupt');$audits=$this->rows("SELECT metadata_json FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_sales_copied' LIMIT 2",[$issuer,$result['id']]);if(count($audits)!==1||$this->decode($audits[0]['metadata_json'])!==['source_document_id'=>$id,'request_key'=>$key,'record_hash'=>$stored])throw new RuntimeException('issuer_sales_mutation_corrupt');return(int)$result['id'];}
            if($r['state']==='converting')throw new DomainException('company_sales_state_conflict');$tags=$this->copyTags($issuer,$actor,$r);$fields=$this->copyCustomFields($issuer,$actor,$r);if(!hash_equals(self::copySourceRevision($r,$history['number'],$tags,$fields),$intent['source_revision']))throw new DomainException('company_sales_stale');if($fields!==null&&$fields['values']!==($payload['native_custom_fields']??[]))throw new DomainException('native_estimate_custom_copy_denied');$current=$this->context($issuer,$actor,$payload['client_id'],$payload['currency'],$r['kind'],false,array_key_exists('native_fields',$payload));if(!hash_equals($intent['destination_revision'],self::hash([$payload,$current])))throw new DomainException('company_sales_stale');
            $references=$this->boundaryReferences($issuer,$actor,[$r,['client_id'=>$payload['client_id'],'currency'=>$payload['currency']]]);$sequences=$this->boundarySequences($issuer);$creation=hash('sha256','company-sales-copy:'.$issuer.':'.$id.':'.$key);$result=$this->createInOwned($issuer,$actor,$r['kind'],$payload,$creation,self::hash([$r['kind'],$payload]));$new=$this->owned($issuer,$actor,$result,true);$record=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'source_document_id'=>$id,'result_document_id'=>$result,'source_revision'=>$intent['source_revision'],'source_json'=>self::encode(['binding'=>self::creationBinding($r,$r['input_hash']),'payload'=>$this->decode($r['payload_json']),'context'=>$this->decode($r['context_json']),'state'=>$r['state']]),'review_json'=>self::encode(['payload'=>$payload,'intent'=>$intent,'context'=>$current]),'input_hash'=>$form,'result_creation_key'=>$creation,'result_hash'=>self::hash(self::creationBinding($new,$new['input_hash'])),'created_at'=>gmdate('Y-m-d H:i:s')];$record['record_hash']=self::hash($record);$this->insert('company_sales_copy_requests',$record);$this->mutationAudit($issuer,$actor,$result,'company_sales_copied',['source_document_id'=>$id,'request_key'=>$key,'record_hash'=>$record['record_hash']]);$expectedSequences=$this->reservedSequences($sequences,$issuer,$r['kind']==='estimates'?'EST':'PROP',(int)$new['number']);$this->confirmBoundary($issuer,$actor,$references,[$r,$new],$expectedSequences,[['company_sales_copy_requests','request_key=?',[$key],$record]]);if(!hash_equals(self::hash($current),self::hash($this->context($issuer,$actor,$payload['client_id'],$payload['currency'],$r['kind'],false,array_key_exists('native_fields',$payload)))))throw new RuntimeException('issuer_sales_write_unconfirmed');if($tags!==null&&$this->copyTags($issuer,$actor,$r)!==$tags)throw new DomainException('company_sales_stale');if($fields!==null&&$this->copyCustomFields($issuer,$actor,$r)!==$fields)throw new DomainException('company_sales_stale');$this->auth($issuer,$actor,$r['kind'],'view');$this->auth($issuer,$actor,$r['kind'],'create');return$result;},array_values(array_unique(array_merge($this->tagTablesForPayload($payload),$this->customTablesForPayload($payload),$this->copyTagTables(),$this->copyCustomTables()))));
    }
}
