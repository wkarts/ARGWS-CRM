<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';
require_once __DIR__.'/CompanySalesMutations.php';

/** Private quotes/proposals with audited open revisions and resumable canonical invoice conversion. */
final class CompanySales extends CompanySalesBase
{
    use CompanySalesMutations;
    private ?array $nativeCreatedEstimate=null;
    private static function kind(string $kind):string{if(!in_array($kind,['estimates','proposals'],true))throw new DomainException('company_sales_invalid');return $kind;}
    public function create($issuer,$actor,string $kind,array $input,string $key,?array $numberSelection=null,?callable $originGuard=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$payload=self::input($input);self::key($key);$hash=self::hash([$kind,$payload]);$this->auth($issuer,$actor,$kind,'create');
        if($numberSelection!==null&&(array_keys($numberSelection)!==['next_number','revision']||!is_string($numberSelection['next_number'])||!preg_match('/^[1-9][0-9]{0,9}$/D',$numberSelection['next_number'])||(int)$numberSelection['next_number']>2147483647||!is_string($numberSelection['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$numberSelection['revision'])))throw new DomainException('native_sales_sequence_invalid');
        return $this->tx($issuer,$actor,fn()=>$this->createInOwned($issuer,$actor,$kind,$payload,$key,$hash,$numberSelection,$originGuard),array_values(array_unique(array_merge($this->tagTablesForPayload($payload),$this->customTablesForPayload($payload)))));
    }
    /** Existing canonical sales creation body, reused by atomic reviewed-copy receipts. */
    private function createInOwned(int $issuer,int $actor,string $kind,array $payload,string $key,string $hash,?array $numberSelection=null,?callable $originGuard=null):int
    {
            $this->auth($issuer,$actor,$kind,'view');$this->auth($issuer,$actor,$kind,'create');
            $old=$this->rows('SELECT id,issuer_id,actor_id,input_hash FROM '.$this->prefix.'company_sales_documents WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor)throw new DomainException('company_sales_conflict');$existing=$this->owned($issuer,$actor,(int)$old['id'],true);$original=$this->revisionHistory($existing);if(!hash_equals($original['creation_hash'],$hash))throw new DomainException('company_sales_conflict');return (int)$old['id'];}
            if($originGuard!==null)$originGuard(); // A veto can never grant authority.
            $this->assertNativeFields($issuer,$actor,$kind,$payload);
            if($numberSelection!==null){require_once __DIR__.'/NativeCompanySalesSequencePreferences.php';$selection=(new NativeCompanySalesSequencePreferences($this->db,$this->prefix))->readForCreationInOwnedTransaction($issuer,$actor,$kind);if($selection['next_number']!==$numberSelection['next_number']||!hash_equals($selection['revision'],$numberSelection['revision']))throw new DomainException('native_sales_sequence_stale');}
            $context=$this->context($issuer,$actor,$payload['client_id'],$payload['currency'],$kind,false,array_key_exists('native_fields',$payload));$series=$kind==='estimates'?'EST':'PROP';$references=$this->boundaryReferences($issuer,$actor,[$payload]);$sequences=$this->boundarySequences($issuer);
            $seq=$this->rows('SELECT next_number FROM '.$this->prefix.'issuers_sequences WHERE issuer_id=? AND series=? FOR UPDATE',[$issuer,$series])[0]??null;$number=$seq?(int)$seq['next_number']:1;if($number<1||$number>2147483647)throw new DomainException('company_sales_sequence_exhausted');
            if($seq)IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuers_sequences SET next_number=? WHERE issuer_id=? AND series=?',[$number+1,$issuer,$series]);else$this->insert('issuers_sequences',['issuer_id'=>$issuer,'series'=>$series,'next_number'=>2]);
            $amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));$fields=['issuer_id'=>$issuer,'actor_id'=>$actor,'conversion_actor_id'=>0,'client_id'=>$payload['client_id'],'currency'=>$payload['currency'],'kind'=>$kind,'number'=>$number,'state'=>'open','payload_json'=>self::encode($payload),'context_json'=>self::encode($context),'origin_hash'=>self::hash($context),'total'=>$amounts['total'],'request_key'=>$key,'input_hash'=>$hash,'invoice_id'=>0,'created_at'=>date('Y-m-d H:i:s')];$this->insert('company_sales_documents',$fields);$id=(int)$this->db->insert_id();
            $written=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_documents WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??[];if(!$id)throw new RuntimeException('issuer_sales_write_unconfirmed');foreach($fields as$field=>$value){if($field==='total'){if(CompanyInvoiceAmounts::minor((string)($written[$field]??''))!==CompanyInvoiceAmounts::minor((string)$value))throw new RuntimeException('issuer_sales_write_unconfirmed');}elseif((string)($written[$field]??'')!==(string)$value)throw new RuntimeException('issuer_sales_write_unconfirmed');}$this->audit($issuer,$actor,$id,'company_sales_created');if($this->mutationsReady())$this->recordCreation($written);
            if(($payload['native_tags']??[])!==[]||array_key_exists('native_custom_fields',$payload)){
                $this->nativeCreatedEstimate=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'key'=>$key,'input_hash'=>$hash];
                try{if(array_key_exists('native_custom_fields',$payload))(new NativeCompanyCustomFields($this->db,$this->prefix))->saveCreatedInOwnedTransaction($issuer,$actor,'estimate',$id,$payload['native_custom_fields'],$this);if(($payload['native_tags']??[])!==[])(new NativeCompanyResourceTags($this->db,$this->prefix))->createEstimateTagsInOwnedTransaction($issuer,$actor,$id,$payload['native_tags'],$this);}
                finally{$this->nativeCreatedEstimate=null;}
            }
$this->confirmBoundary($issuer,$actor,$references,[$written],$this->reservedSequences($sequences,$issuer,$series,$number));if(!hash_equals(self::hash($context),self::hash($this->context($issuer,$actor,$payload['client_id'],$payload['currency'],$kind,false,array_key_exists('native_fields',$payload)))))throw new RuntimeException('issuer_sales_write_unconfirmed');$this->assertNativeFields($issuer,$actor,$kind,$payload);$this->auth($issuer,$actor,$kind,'view');$this->auth($issuer,$actor,$kind,'create');if($originGuard!==null)$originGuard();return $id;
    }

    /** Only this live transaction can attest a newly created estimate to metadata writers. */
    public function assertCreatedInOwned($db,string $prefix,int $issuer,int $actor,string $source,int $id):void
    {
        IssuerTransaction::assertOwned($db,$prefix,['company_sales_documents']);$frame=$this->nativeCreatedEstimate;
        if($db!==$this->db||$prefix!==$this->prefix||$source!=='company_estimate'||!$frame||$frame['issuer']!==$issuer||$frame['actor']!==$actor||$frame['id']!==$id)throw new DomainException('native_estimate_creation_proof_required');
        $this->auth($issuer,$actor,'estimates','view');$this->auth($issuer,$actor,'estimates','create');
        $row=$this->rows('SELECT issuer_id,actor_id,kind,request_key,input_hash FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if(!$row||(int)$row['issuer_id']!==$issuer||(int)$row['actor_id']!==$actor||$row['kind']!=='estimates'||!hash_equals($frame['key'],$row['request_key'])||!hash_equals($frame['input_hash'],$row['input_hash']))throw new DomainException('native_estimate_creation_proof_required');
    }

    private function owned(int $issuer,int $actor,int $id,bool $lock=false):array
    {
        // Kind and owner only are inspected before resource-specific authorization and hydration.
        $ref=$this->rows('SELECT kind FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND id=?'.($lock?' FOR UPDATE':''),[$issuer,$id])[0]??null;if(!$ref)throw new DomainException('company_sales_denied');$this->auth($issuer,$actor,self::kind($ref['kind']));
        $row=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND id=?'.($lock?' FOR UPDATE':''),[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_sales_denied');$payload=$this->decode($row['payload_json']);$context=$this->decode($row['context_json']);if(!hash_equals($row['origin_hash'],self::hash($context))||!hash_equals($row['input_hash'],self::hash([$row['kind'],$payload]))||(int)($context['issuer_id']??0)!==$issuer||(int)($payload['client_id']??0)!==(int)$row['client_id']||(int)($payload['currency']??0)!==(int)$row['currency'])throw new RuntimeException('issuer_sales_write_unconfirmed');$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));if(CompanyInvoiceAmounts::minor((string)$row['total'])!==CompanyInvoiceAmounts::minor($amounts['total'])||!in_array($row['state'],['open','accepted','declined','cancelled','converting','converted'],true)||($row['state']==='converted'?((int)$row['invoice_id']<1||(int)$row['conversion_actor_id']<1):((int)$row['invoice_id']!==0||($row['state']==='converting'?(int)$row['conversion_actor_id']<1:(int)$row['conversion_actor_id']!==0))))throw new RuntimeException('issuer_sales_write_unconfirmed');$this->revisionHistory($row);return $row;
    }
    public function document($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->tx($issuer,$actor,function()use($issuer,$actor,$id){$r=$this->owned($issuer,$actor,$id,true);$history=$this->revisionHistory($r);$r['revision_token']=self::revisionToken($r,$history['number']);$r['revision_number']=$history['number'];$r['payload']=$this->decode($r['payload_json']);$r['context']=$this->decode($r['context_json']);$r+=CompanySalesPreferences::presentation($r['kind'],$r['number'],$r['context'],$r['kind']==='estimates'?'ORC-':'PROP-', $r['payload']['date']??null);if(array_key_exists('terms',$r['payload']['native_fields']??[]))$r['terms']=$r['payload']['native_fields']['terms'];if(array_key_exists('native_reference',$r['payload']))$r['reference_no']=$r['payload']['native_reference'];unset($r['payload']['native_custom_fields']);unset($r['payload_json'],$r['context_json'],$r['origin_hash'],$r['request_key'],$r['input_hash']);$this->auth($issuer,$actor,$r['kind']);return $r;});
    }

    public function page($issuer,$actor,string $kind,$after=0,string $search=''):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$this->auth($issuer,$actor,$kind);if($after!==0)$after=IssuerPolicy::id($after);
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$kind,$after,$search){
        if(strlen($search)>191||preg_match('//u',$search)!==1)throw new DomainException('company_sales_invalid');$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$searchable=self::searchablePayloadExpression();$r=$this->rows("SELECT id,kind,number,state,total,invoice_id,created_at,context_json,origin_hash FROM ".$this->prefix."company_sales_documents WHERE issuer_id=? AND kind=? AND id>? AND (".$searchable." LIKE ? ESCAPE '!' OR context_json LIKE ? ESCAPE '!') ORDER BY id LIMIT 51",[$issuer,$kind,$after,$like,$like]);foreach($r as &$row){$owned=$this->owned($issuer,$actor,(int)$row['id'],true);$payload=$this->decode($owned['payload_json']);$context=CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer);$row['formatted_number']=CompanySalesPreferences::presentation($row['kind'],$row['number'],$context,'',$payload['date']??null)['formatted_number'];unset($row['context_json'],$row['origin_hash']);}unset($row);$this->auth($issuer,$actor,$kind);return ['items'=>array_slice($r,0,50),'next'=>count($r)>50?(int)$r[49]['id']:null];});
    }

    /** Search only visible document content; remove private fields before SQL matching. */
    private static function searchablePayloadExpression():string
    {return "(CASE WHEN json_valid(payload_json) THEN json_remove(payload_json,'$.native_custom_fields','$.native_fields.adminnote') ELSE '' END)";}

    /** SQL projection narrows the search before owned-document hydration. Values are never trusted until owned() verifies both snapshots. */
    private function relationNumberExpression(string $kind):string
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;
        $driver=$connection instanceof PDO?$connection->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($this->db->dbdriver??'');
        if(!in_array($driver,['sqlite','mysql','mysqli'],true))throw new RuntimeException('issuer_storage_unsupported');
        $sqlite=$driver==='sqlite';
        $json=static function(string $column,string $path)use($sqlite):string{
            $extract="json_extract($column, '$path')";
            if(!$sqlite)$extract="JSON_UNQUOTE($extract)";
            return "(CASE WHEN json_valid($column) THEN $extract ELSE NULL END)";
        };
        $concat=static fn(array$parts):string=>$sqlite?'('.implode(' || ',$parts).')':'CONCAT('.implode(',',$parts).')';
        $legacy=$kind==='estimates'?"'ORC-'":"'PROP-'";
        $prefix='COALESCE('.$json('context_json','$.sales_preferences.prefix').','.$legacy.')';
        $version='CAST('.$json('context_json','$.sales_preferences.format').' AS CHAR)';
        $format=$json('context_json','$.sales_preferences.number_format');
        $integerType=$sqlite?'INTEGER':'SIGNED';
        $padding='(CASE WHEN '.$version." IN ('4','5') THEN CAST(COALESCE(".$json('context_json','$.sales_preferences.padding').",'6') AS $integerType) ELSE 6 END)";
        $number=$sqlite?'CAST(number AS TEXT)':'CAST(number AS CHAR)';
        $padded=$sqlite?"printf('%0*d',$padding,number)":"(CASE WHEN LENGTH($number)>=$padding THEN $number ELSE LPAD($number,$padding,'0') END)";
        $date=$json('payload_json','$.date');
        $year='substr('.$date.',1,4)';$shortYear='substr('.$date.',3,2)';$month='substr('.$date.',6,2)';
        $plain=$concat([$prefix,$padded]);
        $yearBased=$concat([$prefix,$year,"'/'",$padded]);
        $short=$concat([$prefix,$padded,"'-'",$shortYear]);
        $monthBased=$concat([$prefix,$padded,"'/'",$month,"'/'",$year]);
        return '(CASE WHEN '.$version." IN ('4','5') THEN CASE ".$format." WHEN '2' THEN $yearBased WHEN '3' THEN $short WHEN '4' THEN $monthBased ELSE $plain END ELSE $plain END)";
    }
    /** Bounded native relation search with the same snapshot validation and number presentation. */
    public function relationOptions(int $issuer,int $actor,string $kind,string $search=''):array
    {
        $kind=self::kind($kind);if(strlen($search)>191||preg_match('//u',$search)!==1||preg_match('/[\x00-\x1f\x7f]/',$search))throw new DomainException('company_sales_invalid');
        return$this->tx($issuer,$actor,function()use($issuer,$actor,$kind,$search){
            $this->auth($issuer,$actor,$kind);$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
            $numberExpression=$this->relationNumberExpression($kind);
            $rows=$this->rows("SELECT id FROM ".$this->prefix."company_sales_documents WHERE issuer_id=? AND kind=? AND (".self::searchablePayloadExpression()." LIKE ? ESCAPE '!' OR context_json LIKE ? ESCAPE '!' OR ".$numberExpression." LIKE ? ESCAPE '!') ORDER BY id LIMIT 50",[$issuer,$kind,$needle,$needle,$needle]);
            $out=[];foreach($rows as$r){$row=$this->owned($issuer,$actor,(int)$r['id'],true);$context=CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer);$label=CompanySalesPreferences::presentation($kind,$row['number'],$context,$kind==='estimates'?'ORC-':'PROP-', $this->decode($row['payload_json'])['date']??null)['formatted_number'];$out[]=['id'=>(string)$row['id'],'name'=>$label,'subtext'=>''];}$this->auth($issuer,$actor,$kind);return$out;
        });
    }
    public function decide($issuer,$actor,$id,string $decision,?string $revision=null):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);if(!in_array($decision,['accepted','declined','cancelled'],true))throw new DomainException('company_sales_invalid');
        $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$decision,$revision){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],$decision==='cancelled'?'delete':'edit');if($r['state']===$decision)return;if($revision!==null&&!hash_equals(self::revisionToken($r,$this->revisionHistory($r)['number']),$revision))throw new DomainException('company_sales_stale');if($r['state']!=='open')throw new DomainException('company_sales_state_conflict');$references=$this->boundaryReferences($issuer,$actor,[$r]);$sequences=$this->boundarySequences($issuer);$expected=array_replace($r,['state'=>$decision]);IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_sales_documents SET state=? WHERE id=? AND issuer_id=?',[$decision,$id,$issuer]);if(($this->owned($issuer,$actor,$id,true)['state']??null)!==$decision)throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_'.$decision);$this->confirmBoundary($issuer,$actor,$references,[$expected],$sequences);$this->auth($issuer,$actor,$r['kind'],$decision==='cancelled'?'delete':'edit');});
    }
    public function convert($issuer,$actor,$id,?string $revision=null,?callable $originGuard=null,array $originTables=[],?NativeCompanyEstimateAutoConvert $automation=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        $transfer=null;$willTransfer=false;if($this->db->table_exists($this->prefix.'company_custom_fields')&&$this->db->table_exists($this->prefix.'company_custom_field_values')){require_once __DIR__.'/NativeCompanyEstimateSmartTransfer.php';$transfer=new NativeCompanyEstimateSmartTransfer($this->db,$this->prefix);}
        $claim=$this->tx($issuer,$actor,function()use($issuer,$actor,$id,$revision,$transfer,$originGuard,$automation,&$willTransfer){if($originGuard!==null)$originGuard();if($automation!==null)$automation->assertConversionInOwned($this->db,$this->prefix,$issuer,$actor,$id);$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');$this->auth($issuer,$actor,'invoices','create');if($r['state']==='converted')return $r;if($revision!==null){$number=$this->revisionHistory($r)['number'];$valid=hash_equals(self::revisionToken($r,$number),$revision);if(!$valid&&$r['state']==='converting'&&(int)$r['conversion_actor_id']===$actor){$accepted=$r;$accepted['state']='accepted';$accepted['conversion_actor_id']=0;$accepted['invoice_id']=0;$valid=hash_equals(self::revisionToken($accepted,$number),$revision);}if(!$valid)throw new DomainException('company_sales_stale');}if(!in_array($r['state'],['accepted','converting'],true))throw new DomainException('company_sales_state_conflict');if((int)$r['conversion_actor_id']!==$actor&&$r['state']==='converting')throw new DomainException('company_sales_conflict');$payload=$this->decode($r['payload_json']);if($r['kind']==='estimates'&&$transfer!==null){$existing=$this->rows("SELECT id FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_estimate_transfer_planned' LIMIT 1",[$issuer,$id]);$valueCount=$this->rows("SELECT COUNT(*) n FROM {$this->prefix}company_custom_field_values WHERE issuer_id=? AND source_kind='company_estimate' AND source_id=?",[$issuer,$id]);$willTransfer=array_key_exists('native_custom_fields',$payload)||(int)($valueCount[0]['n']??0)>0||$existing!==[];if($willTransfer&&$r['state']==='accepted')$transfer->capture($issuer,$actor,$id);}elseif(array_key_exists('native_custom_fields',$payload))throw new DomainException('native_estimate_form_fields_unavailable');$this->assertOriginOrReceipt($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']),hash('sha256','company-sales-invoice:'.$issuer.':'.$id),$automation!==null);$references=$this->boundaryReferences($issuer,$actor,[$r]);$sequences=$this->boundarySequences($issuer);$expected=array_replace($r,['state'=>'converting','conversion_actor_id'=>$actor]);IssuerStorage::query($this->db,"UPDATE ".$this->prefix."company_sales_documents SET state='converting',conversion_actor_id=? WHERE issuer_id=? AND id=?",[$actor,$issuer,$id]);if($this->owned($issuer,$actor,$id,true)['state']!=='converting')throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_conversion_claimed');$this->confirmBoundary($issuer,$actor,$references,[$expected],$sequences);$this->assertOriginOrReceipt($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']),hash('sha256','company-sales-invoice:'.$issuer.':'.$id),$automation!==null);$this->auth($issuer,$actor,$r['kind'],'edit');$this->auth($issuer,$actor,'invoices','create');if($originGuard!==null)$originGuard();return $r;},array_values(array_unique(array_merge($transfer?$transfer->tables():[],$originTables))));
        if($claim['state']==='converted'){(new CompanyInvoices($this->db,$this->prefix))->document($issuer,$actor,$claim['invoice_id']);return (int)$claim['invoice_id'];}
        $payload=$this->decode($claim['payload_json']);$guardTables=array_values(array_unique(array_merge(['company_sales_documents'],$originTables)));if($this->mutationsReady())$guardTables=array_merge($guardTables,array_keys(CompanySalesMutationsSchema::definitions()));if($willTransfer){if($transfer===null)throw new DomainException('native_estimate_form_fields_unavailable');$guardTables=array_values(array_unique(array_merge($guardTables,$transfer->tables())));}$invoiceWriter=new CompanyInvoices($this->db,$this->prefix);$invoiceKey=hash('sha256','company-sales-invoice:'.$issuer.':'.$id);$guard=function()use($issuer,$actor,$id,$payload,$claim,$guardTables,$transfer,$invoiceWriter,$invoiceKey,$willTransfer,$originGuard,$automation){IssuerTransaction::assertOwned($this->db,$this->prefix,$guardTables);if($automation!==null)$automation->assertConversionInOwned($this->db,$this->prefix,$issuer,$actor,$id);(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');$expected=array_replace($claim,['state'=>$r['state'],'conversion_actor_id'=>$actor,'invoice_id'=>$r['state']==='converted'?$r['invoice_id']:0]);self::boundaryRow($r,$expected);if($r['state']==='converted')$this->canonicalBoundary($issuer,$actor,$id,(int)$r['invoice_id'],$r);if(!in_array($r['state'],['converting','converted'],true)||(int)$r['conversion_actor_id']!==$actor||!hash_equals($claim['origin_hash'],$r['origin_hash']))throw new DomainException('company_sales_conflict');$this->assertOriginOrReceipt($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']),hash('sha256','company-sales-invoice:'.$issuer.':'.$id),$automation!==null);if($willTransfer)$transfer->verifyOrTransfer($issuer,$actor,$id,$invoiceKey,$invoiceWriter);if($originGuard!==null)$originGuard();};
        $invoiceInput=$payload;unset($invoiceInput['native_reference'],$invoiceInput['native_tags'],$invoiceInput['native_custom_fields']);
        if(isset($invoiceInput['native_fields'])){
            // The original estimate conversion transfers address/agent/display, but
            // starts a new invoice with its own default terms, client note and no admin note.
            $origin=$this->decode($claim['context_json']);$invoiceInput['native_fields']['terms']=$origin['identity']['terms'];
            $invoiceInput['native_fields']['adminnote']='';
            if(($origin['native_invoice_defaults']['format']??null)!==1||!is_string($origin['native_invoice_defaults']['clientnote']??null))throw new DomainException('company_sales_invalid');
            $invoiceInput['note']=$origin['native_invoice_defaults']['clientnote'];
        }
        $invoice=$invoiceWriter->create($issuer,$actor,$invoiceInput,$invoiceKey,$guard,$guardTables);
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$invoice,$originGuard,$automation){if($originGuard!==null)$originGuard();if($automation!==null)$automation->assertConversionInOwned($this->db,$this->prefix,$issuer,$actor,$id);$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');if($r['state']==='converted'&&(int)$r['invoice_id']===$invoice)return $invoice;if($r['state']!=='converting'||(int)$r['conversion_actor_id']!==$actor)throw new DomainException('company_sales_conflict');$references=$this->boundaryReferences($issuer,$actor,[$r]);$sequences=$this->boundarySequences($issuer);$canonical=$this->canonicalBoundary($issuer,$actor,$id,$invoice,$r);$expected=array_replace($r,['state'=>'converted','invoice_id'=>$invoice]);IssuerStorage::query($this->db,"UPDATE ".$this->prefix."company_sales_documents SET state='converted',invoice_id=? WHERE issuer_id=? AND id=?",[$invoice,$issuer,$id]);$saved=$this->owned($issuer,$actor,$id,true);if($saved['state']!=='converted'||(int)$saved['invoice_id']!==$invoice)throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_converted');$this->confirmBoundary($issuer,$actor,$references,[$expected],$sequences,$canonical);$this->canonicalBoundary($issuer,$actor,$id,$invoice,$expected);$this->auth($issuer,$actor,$r['kind'],'edit');if($originGuard!==null)$originGuard();return $invoice;},$originTables);
    }
    public function confirmPdf($issuer,$actor,$id,?string $revision=null):void{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$this->tx($issuer,$actor,function()use($issuer,$actor,$id,$revision){$r=$this->owned($issuer,$actor,$id,true);if($revision!==null&&!hash_equals(self::revisionToken($r,$this->revisionHistory($r)['number']),$revision))throw new DomainException('company_sales_stale');$references=$this->boundaryReferences($issuer,$actor,[$r]);$sequences=$this->boundarySequences($issuer);$this->audit($issuer,$actor,$id,'company_sales_pdf_downloaded');$this->confirmBoundary($issuer,$actor,$references,[$r],$sequences);});}
}
