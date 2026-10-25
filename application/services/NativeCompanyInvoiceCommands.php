<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceCommandSchema.php';
require_once __DIR__.'/CompanyInvoiceDrafts.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
/** Full native form composition around existing financial writers, not a second monetary domain. */
final class NativeCompanyInvoiceCommands
{
    public function __construct(private $db,private string $p){NativeCompanyInvoiceCommandSchema::definitions($p);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tables():array{return array_values(array_unique(array_merge([NativeCompanyInvoiceCommandSchema::TABLE],(new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),NativeCompanyResourceTags::tableNames())));}
    private static function canonical(array $value):array{foreach($value as&$item)if(is_array($item))$item=self::canonical($item);unset($item);if(!array_is_list($value))ksort($value);return$value;}
    private function command(int $issuer,int $actor,string $key,string $operation,string $hash):?array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $rows=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=? FOR UPDATE',[$key]);if(!$rows)return null;if(count($rows)!==1)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$row=$rows[0];
        if((int)$row['issuer_id']!==$issuer||(int)$row['actor_id']!==$actor||$row['operation']!==$operation||!hash_equals($row['input_hash'],$hash))throw new DomainException('native_invoice_command_conflict');
        if($row['result_json']===null){if($row['result_hash']!==null||$row['audit_id']!==null)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$row;}
        if(!is_string($row['result_hash'])||!hash_equals(hash('sha256',$row['result_json']),$row['result_hash']))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
        CompanyInvoiceDraftsInput::decode($row['result_json']);$metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$key,'operation'=>$operation,'input_hash'=>$hash,'result_hash'=>$row['result_hash']]);
        $audit=$this->rows('SELECT issuer_id,actor_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$row['audit_id']])[0]??[];
        if((int)($audit['issuer_id']??0)!==$issuer||(int)($audit['actor_id']??0)!==$actor||($audit['event']??null)!=='native_invoice_command_completed'||($audit['metadata_json']??null)!==$metadata)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$row;
    }
    private function begin(int $issuer,int $actor,string $key,string $operation,string $hash):void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.'(request_key,issuer_id,actor_id,operation,input_hash,result_json,result_hash,audit_id) VALUES(?,?,?,?,?,NULL,NULL,NULL)',[$key,$issuer,$actor,$operation,$hash]);
        $row=$this->command($issuer,$actor,$key,$operation,$hash);if(!$row||$row['result_json']!==null)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
    }
    private function complete(int $issuer,int $actor,string $key,string $operation,string $hash,array $result):array
    {
        $json=CompanyInvoiceDraftsInput::json($result);$resultHash=hash('sha256',$json);$metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$key,'operation'=>$operation,'input_hash'=>$hash,'result_hash'=>$resultHash]);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,event,metadata_json,created_at) VALUES(?,?,?,?,?)',[$issuer,$actor,'native_invoice_command_completed',$metadata,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();if($audit<1)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
        IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyInvoiceCommandSchema::TABLE.' SET result_json=?,result_hash=?,audit_id=? WHERE request_key=? AND issuer_id=? AND actor_id=? AND result_json IS NULL',[$json,$resultHash,$audit,$key,$issuer,$actor]);
        $row=$this->command($issuer,$actor,$key,$operation,$hash);if(!$row||$row['result_json']!==$json)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$result;
    }
    /** Direct issue uses the canonical allocator/writer once; no intermediate committed draft. */
    public function createIssued(int $issuer,int $actor,array $invoice,array $metadata,string $key):array
    {
        $key=CompanyInvoiceDraftsInput::key($key);$payload=CompanyInvoiceDraftsInput::normalize($invoice);
        if(array_diff(array_keys($metadata),['invoice_fields','line_fields','tags','number_selection'])||array_diff(['invoice_fields','line_fields','tags'],array_keys($metadata))||!is_array($metadata['invoice_fields'])||!is_array($metadata['line_fields'])||!array_is_list($metadata['line_fields'])||count($metadata['line_fields'])!==count($payload['items'])||!is_array($metadata['tags'])||!array_is_list($metadata['tags']))throw new DomainException('native_invoice_metadata_invalid');
        if(array_key_exists('number_selection',$metadata)){
            $selection=$metadata['number_selection'];
            if(!is_array($selection)||array_keys($selection)!==['next_number','revision']||!is_string($selection['next_number'])||!preg_match('/^[1-9][0-9]{0,19}$/D',$selection['next_number'])||!is_string($selection['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$selection['revision']))throw new DomainException('native_invoice_metadata_invalid');
        }
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$fields->validateValues($issuer,$actor,'invoice',$metadata['invoice_fields'],'create');foreach($metadata['line_fields']as$line){if(!is_array($line))throw new DomainException('native_invoice_metadata_invalid');$fields->validateInvoiceItemValuesForCreation($issuer,$actor,$line);}
        NativeCompanyInvoiceCommandSchema::assertValid($this->db,$this->p);
        $hash=CompanyInvoiceDraftsInput::hash(self::canonical(['payload'=>$payload,'metadata'=>$metadata]));$operation='invoice_create';$writer=new CompanyInvoices($this->db,$this->p);$result=null;$started=false;
        $guard=function()use($issuer,$actor,$metadata,$key,$hash,$operation,$writer,$fields,&$result,&$started):void{
            $record=$this->command($issuer,$actor,$key,$operation,$hash);
            if(!$record){
                if(isset($metadata['number_selection'])){require_once __DIR__.'/NativeCompanyInvoiceSequencePreferences.php';$current=(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readForInvoiceCreationInOwned($issuer,$actor);if($current!==$metadata['number_selection'])throw new DomainException('native_invoice_sequence_stale');}
                $this->begin($issuer,$actor,$key,$operation,$hash);$started=true;return;
            }
            if($record['result_json']!==null){$result=CompanyInvoiceDraftsInput::decode($record['result_json']);return;}
            if(!$started)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            $row=$this->rows('SELECT invoice_id FROM '.$this->p.'company_invoice_requests WHERE issuer_id=? AND request_key=?',[$issuer,$key])[0]??null;if(!$row)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$id=(int)$row['invoice_id'];
            $writer->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'invoice',$id);
            $fields->saveCreatedInOwnedTransaction($issuer,$actor,'invoice',$id,$metadata['invoice_fields'],$writer);
            $lines=$writer->createdLineIdsInOwned($issuer,$actor,$id,$key);if(count($lines)!==count($metadata['line_fields']))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            foreach($lines as$position=>$line)$fields->saveCreatedInOwnedTransaction($issuer,$actor,'items',$line['itemable_id'],$metadata['line_fields'][$position],$writer);
            $tags=(new NativeCompanyResourceTags($this->db,$this->p))->createInvoiceTagsInOwnedTransaction($issuer,$actor,$id,$metadata['tags'],$writer);
            $result=$this->complete($issuer,$actor,$key,$operation,$hash,['invoice_id'=>$id,'canonical_id'=>NativeCompanyResourceId::encode('invoice',$id),'line_ids'=>array_column($lines,'itemable_id'),'tags_revision'=>$tags['revision']]);
        };
        $id=$writer->create($issuer,$actor,$payload,$key,$guard,$this->tables());
        if(!is_array($result)||($result['invoice_id']??null)!==$id)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$result;
    }
    public function createDraft(int $issuer,int $actor,array $invoice,array $metadata,string $key):array
    {
        $key=CompanyInvoiceDraftsInput::key($key);$payload=CompanyInvoiceDraftsInput::normalize($invoice);
        if(array_diff(array_keys($metadata),['invoice_fields','line_fields','tags'])||array_diff(['invoice_fields','line_fields','tags'],array_keys($metadata))||!is_array($metadata['invoice_fields'])||!is_array($metadata['line_fields'])||!array_is_list($metadata['line_fields'])||count($metadata['line_fields'])!==count($payload['items'])||!is_array($metadata['tags'])||!array_is_list($metadata['tags']))throw new DomainException('native_invoice_metadata_invalid');
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$fields->validateValues($issuer,$actor,'invoice',$metadata['invoice_fields'],'create');foreach($metadata['line_fields']as$line){if(!is_array($line))throw new DomainException('native_invoice_metadata_invalid');$fields->validateInvoiceItemValuesForCreation($issuer,$actor,$line);}
        NativeCompanyInvoiceCommandSchema::assertValid($this->db,$this->p);NativeCompanyInvoiceDraftLinesSchema::assertValid($this->db,$this->p);
        $hash=CompanyInvoiceDraftsInput::hash(self::canonical(['payload'=>$payload,'metadata'=>$metadata]));$operation='draft_create';$drafts=new CompanyInvoiceDrafts($this->db,$this->p);$result=null;$started=false;
        $guard=function()use($issuer,$actor,$payload,$metadata,$key,$hash,$operation,$drafts,$fields,&$result,&$started):void{
            $record=$this->command($issuer,$actor,$key,$operation,$hash);
            if(!$record){$this->begin($issuer,$actor,$key,$operation,$hash);$started=true;return;}
            if($record['result_json']!==null){$result=CompanyInvoiceDraftsInput::decode($record['result_json']);return;}
            if(!$started)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            $row=$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=? AND origin_key=?',[$issuer,$key])[0]??null;if(!$row)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$id=(int)$row['id'];
            $drafts->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'company_invoice_draft',$id);
            $fields->saveCreatedInOwnedTransaction($issuer,$actor,'invoice',$id,$metadata['invoice_fields'],$drafts);
            $lines=array_values(array_filter((new NativeCompanyInvoiceDraftLines($this->db,$this->p))->assertInOwned($id),static fn($r)=>$r['draft_revision']===1));
            if(count($lines)!==count($metadata['line_fields']))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            foreach($lines as$position=>$line){if($line['line_json']!==json_encode($payload['items'][$position],JSON_THROW_ON_ERROR))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$fields->saveCreatedInOwnedTransaction($issuer,$actor,'items',$line['id'],$metadata['line_fields'][$position],$drafts);}
            (new NativeCompanyResourceTags($this->db,$this->p))->createDraftTagsInOwnedTransaction($issuer,$actor,$id,$metadata['tags'],$drafts);
            $result=$this->complete($issuer,$actor,$key,$operation,$hash,['draft_id'=>$id,'canonical_id'=>NativeCompanyResourceId::encode('company_invoice_draft',$id),'revision'=>1,'line_ids'=>array_map(static fn($line)=>NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']),$lines)]);
        };
        $id=$drafts->create($issuer,$actor,$payload,$key,$guard,$this->tables());
        if(!is_array($result)||($result['draft_id']??null)!==$id)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$result;
    }
    public function updateDraft(int $issuer,int $actor,int $draft,int $revision,array $invoice,array $metadata,string $key):array
    {
        $key=CompanyInvoiceDraftsInput::key($key);$revision=CompanyInvoiceDraftsInput::revision($revision);$payload=CompanyInvoiceDraftsInput::normalize($invoice);$canonical=NativeCompanyResourceId::encode('company_invoice_draft',$draft);
        $keys=['invoice_fields','line_fields','tags','tags_revision','line_sources','removed_line_ids'];
        if(array_diff(array_keys($metadata),$keys)||array_diff($keys,array_keys($metadata))||!is_array($metadata['invoice_fields'])||!is_array($metadata['line_fields'])||!array_is_list($metadata['line_fields'])||!is_array($metadata['tags'])||!array_is_list($metadata['tags'])||!is_array($metadata['line_sources'])||!array_is_list($metadata['line_sources'])||!is_array($metadata['removed_line_ids'])||!array_is_list($metadata['removed_line_ids'])||count($metadata['line_fields'])!==count($payload['items'])||count($metadata['line_sources'])!==count($payload['items'])||!is_string($metadata['tags_revision'])||!preg_match('/^[a-f0-9]{64}$/D',$metadata['tags_revision']))throw new DomainException('native_invoice_metadata_invalid');
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$fields->validateValuesForResource($issuer,$actor,'invoice',$canonical,$metadata['invoice_fields'],'edit');
        foreach($metadata['line_fields']as$line){if(!is_array($line))throw new DomainException('native_invoice_metadata_invalid');$fields->validateInvoiceDraftItemValues($issuer,$actor,$draft,$line);}
        foreach(array_merge(array_values(array_filter($metadata['line_sources'],static fn($v)=>$v!==null)),$metadata['removed_line_ids'])as$id)if(NativeCompanyResourceId::decode('invoice_item',$id)['source_kind']!=='company_invoice_draft_line')throw new DomainException('native_invoice_metadata_invalid');
        NativeCompanyInvoiceCommandSchema::assertValid($this->db,$this->p);NativeCompanyInvoiceDraftLinesSchema::assertValid($this->db,$this->p);
        $hash=CompanyInvoiceDraftsInput::hash(self::canonical(['draft_id'=>$draft,'revision'=>$revision,'payload'=>$payload,'metadata'=>$metadata]));$operation='draft_update';$drafts=new CompanyInvoiceDrafts($this->db,$this->p);$tags=new NativeCompanyResourceTags($this->db,$this->p);$result=null;$started=false;
        $guard=function()use($issuer,$actor,$draft,$revision,$canonical,$payload,$metadata,$key,$hash,$operation,$drafts,$fields,$tags,&$result,&$started):void{
            $record=$this->command($issuer,$actor,$key,$operation,$hash);
            if($record&&$record['result_json']!==null){$result=CompanyInvoiceDraftsInput::decode($record['result_json']);return;}
            if(!$record){
                $this->begin($issuer,$actor,$key,$operation,$hash);$old=$drafts->documentInOwned($issuer,$actor,$draft);if($old['state']!=='open'||$old['revision']!==$revision)throw new DomainException('company_invoice_draft_stale');
                $rows=array_values(array_filter((new NativeCompanyInvoiceDraftLines($this->db,$this->p))->assertInOwned($draft),static fn($r)=>$r['draft_revision']===$revision));$actual=array_map(static fn($r)=>NativeCompanyResourceId::encode('company_invoice_draft_line',$r['id']),$rows);
                $submitted=array_map(static fn($id)=>NativeCompanyResourceId::decode('invoice_item',$id)['canonical_id'],array_merge(array_values(array_filter($metadata['line_sources'],static fn($v)=>$v!==null)),$metadata['removed_line_ids']));
                if(count($submitted)!==count(array_unique($submitted)))throw new DomainException('native_invoice_line_mapping_invalid');sort($actual);sort($submitted);if($actual!==$submitted)throw new DomainException('native_invoice_line_mapping_invalid');
                if(!hash_equals($tags->read($issuer,$actor,'invoice',$canonical)['revision'],$metadata['tags_revision']))throw new DomainException('native_tags_stale');$started=true;return;
            }
            if(!$started)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$updated=$drafts->documentInOwned($issuer,$actor,$draft);
            if($updated['state']!=='open'||$updated['revision']!==$revision+1||$updated['payload']!==$payload)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            $lines=array_values(array_filter((new NativeCompanyInvoiceDraftLines($this->db,$this->p))->assertInOwned($draft),static fn($r)=>$r['draft_revision']===$revision+1));if(count($lines)!==count($payload['items']))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');
            $fields->saveInOwnedTransaction($issuer,$actor,'invoice',$canonical,$metadata['invoice_fields']);
            foreach($lines as$position=>$line){if($line['line_json']!==json_encode($payload['items'][$position],JSON_THROW_ON_ERROR))throw new RuntimeException('issuer_native_invoice_command_unconfirmed');$target=NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']);if($metadata['line_sources'][$position]!==null)$fields->inheritDraftLineValuesInOwned($issuer,$actor,(string)$metadata['line_sources'][$position],$target);$fields->saveInOwnedTransaction($issuer,$actor,'items',$target,$metadata['line_fields'][$position]);}
            $tagReceipt=$tags->replaceInOwnedTransaction($issuer,$actor,'invoice',$canonical,$metadata['tags_revision'],$metadata['tags']);
            $result=$this->complete($issuer,$actor,$key,$operation,$hash,['draft_id'=>$draft,'canonical_id'=>$canonical,'revision'=>$revision+1,'line_ids'=>array_map(static fn($line)=>NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']),$lines),'tags_revision'=>$tagReceipt['revision']]);
        };
        $drafts->change($issuer,$actor,$draft,$revision,$payload,$key,$guard,$this->tables());
        if(!is_array($result)||($result['draft_id']??null)!==$draft)throw new RuntimeException('issuer_native_invoice_command_unconfirmed');return$result;
    }

}
