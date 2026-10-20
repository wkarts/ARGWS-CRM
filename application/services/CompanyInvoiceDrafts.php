<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';
require_once __DIR__.'/CompanyInvoiceCopiesStorage.php';
require_once __DIR__.'/CompanyInvoiceDraftsSchema.php';
require_once __DIR__.'/CompanyInvoiceDraftsInput.php';

/** Private documents. Only explicit issue enters CompanyInvoices and its numbering/journal transaction. */
final class CompanyInvoiceDrafts extends CompanySalesBase
{
    public function tables():array{return array_values(array_unique(array_merge((new CompanyInvoiceCopiesStorage($this->db,$this->prefix))->tables(),['company_invoice_drafts','company_invoice_draft_events'])));}
    private function transaction(callable $work,array $extra=[]){CompanyInvoiceDraftsSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,array_values(array_unique(array_merge($this->tables(),$extra))),$work);}
    private function access(int $i,int $a,string $cap='view'):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$acl=new IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($i,$a);
        foreach(array_unique(['view',$cap]) as $c)if(!$acl->can($i,$a,'invoices',$c))throw new DomainException('company_invoice_draft_denied');
        if(!$acl->can($i,$a,'customers','view'))throw new DomainException('company_invoice_draft_denied');
    }
    private static function rowBytes(array $r):array{foreach(['id','issuer_id','created_by','revision','client_id','currency_id','invoice_id','issue_actor','issue_revision'] as $k)$r[$k]=$r[$k]===null?null:(int)$r[$k];return$r;}
    private function row(int $i,int $id):array{$r=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_drafts WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])[0]??null;if(!$r)throw new DomainException('company_invoice_draft_unavailable');return self::rowBytes($r);}
    private function recipient(int $i,int $a,array $r):void{(new CompanyInvoiceCopiesStorage($this->db,$this->prefix))->recipientOwned($i,(int)$r['client_id']);(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($r['currency_id']);$this->access($i,$a);}
    private function event(string $key,int $i,int $a,string $operation,string $inputHash):?array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? FOR UPDATE',[$key])[0]??null;if(!$r)return null;
        if((int)$r['issuer_id']!==$i||(int)$r['actor_id']!==$a||$r['operation']!==$operation||!hash_equals($r['input_hash'],$inputHash))throw new DomainException('company_invoice_draft_conflict');$this->verifyEvent($r);return$r;
    }
    private function verifyEvent(array $r):array
    {
        if(!hash_equals(hash('sha256',$r['data_json']),$r['data_hash']))throw new RuntimeException('issuer_invoice_draft_unconfirmed');$data=CompanyInvoiceDraftsInput::decode($r['data_json']);
        $metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$r['request_key'],'operation'=>$r['operation'],'input_hash'=>$r['input_hash'],'data_hash'=>$r['data_hash']]);
        $audit=$this->rows('SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->prefix.'issuers_audit WHERE id=? FOR UPDATE',[$r['audit_id']])[0]??[];
        if((int)($audit['issuer_id']??0)!==(int)$r['issuer_id']||(int)($audit['actor_id']??0)!==(int)$r['actor_id']||(int)($audit['subject_id']??0)!==(int)$r['draft_id']||($audit['event']??null)!=='company_invoice_draft_'.$r['operation']||($audit['metadata_json']??null)!==$metadata)throw new RuntimeException('issuer_invoice_draft_unconfirmed');return$data;
    }
    private function append(int $i,int $a,int $id,string $key,string $operation,string $inputHash,array $data):void
    {
        $json=CompanyInvoiceDraftsInput::json($data);$dataHash=hash('sha256',$json);$metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$key,'operation'=>$operation,'input_hash'=>$inputHash,'data_hash'=>$dataHash]);$stamp=gmdate('Y-m-d H:i:s');
        $this->insert('issuers_audit',['issuer_id'=>$i,'actor_id'=>$a,'subject_id'=>$id,'event'=>'company_invoice_draft_'.$operation,'metadata_json'=>$metadata,'created_at'=>$stamp]);$audit=(int)$this->db->insert_id();if($audit<1)throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        $this->insert('company_invoice_draft_events',['request_key'=>$key,'issuer_id'=>$i,'actor_id'=>$a,'draft_id'=>$id,'operation'=>$operation,'input_hash'=>$inputHash,'data_json'=>$json,'data_hash'=>$dataHash,'audit_id'=>$audit,'created_at'=>$stamp]);
        $r=$this->event($key,$i,$a,$operation,$inputHash);if(!$r||$this->verifyEvent($r)!==$data)throw new RuntimeException('issuer_invoice_draft_unconfirmed');
    }
    private function validated(int $i,int $a,int $id):array
    {
        $this->access($i,$a);$r=$this->row($i,$id);$this->recipient($i,$a,$r);$payload=CompanyInvoiceDraftsInput::decode($r['payload_json']);$context=CompanyInvoiceDraftsInput::decode($r['context_json']);
        if(!hash_equals(hash('sha256',$r['payload_json']),$r['payload_hash'])||!hash_equals(hash('sha256',$r['context_json']),$r['context_hash'])||CompanyInvoiceDraftsInput::normalize($payload)!==$payload||$payload['client_id']!==$r['client_id']||$payload['currency']!==$r['currency_id']||($context['issuer_id']??null)!==$i||($context['recipient']['client_id']??null)!==$r['client_id']||!in_array($r['state'],['open','cancelled','issued'],true)||$r['revision']<1)throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        if(($r['source_json']===null)!==($r['source_hash']===null)||($r['source_json']!==null&&!hash_equals(hash('sha256',$r['source_json']),$r['source_hash'])))throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        $e=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? AND issuer_id=? AND draft_id=? FOR UPDATE',[$r['last_event_key'],$i,$id])[0]??null;if(!$e||$this->verifyEvent($e)!==['header'=>$r])throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        if($r['state']==='issued')$this->confirmIssued($i,$a,$r);elseif($r['invoice_id']!==null||$r['issue_actor']!==null||$r['issue_revision']!==null||$r['issue_key']!==null||$r['issue_json']!==null||$r['issue_hash']!==null)throw new RuntimeException('issuer_invoice_draft_unconfirmed');return$r;
    }
    private function update(int $i,int $id,array $fields):void{$sql=implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($fields)));IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_invoice_drafts SET '.$sql.' WHERE issuer_id=? AND id=?',array_merge(array_values($fields),[$i,$id]));$r=$this->row($i,$id);foreach($fields as $k=>$v)if($r[$k]!==$v)throw new RuntimeException('issuer_invoice_draft_unconfirmed');}
    public function create($issuer,$actor,array $invoice,string $originKey,?callable $guard=null,array $guardTables=[]):int{return$this->createInternal($issuer,$actor,$invoice,$originKey,null,$guard,$guardTables);}
    private function createInternal($issuer,$actor,array $invoice,string $originKey,?array $source,?callable $guard,array $guardTables):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$key=CompanyInvoiceDraftsInput::key($originKey);$payload=CompanyInvoiceDraftsInput::normalize($invoice);$hash=CompanyInvoiceDraftsInput::hash(['payload'=>$payload,'source'=>$source]);
        return$this->transaction(function()use($i,$a,$key,$payload,$source,$hash,$guard):int{
            $this->access($i,$a,'create');if($guard)$guard();$existing=$this->event($key,$i,$a,'created',$hash);if($existing){$id=(int)$existing['draft_id'];$r=$this->validated($i,$a,$id);if($r['origin_key']!==$key||$r['creation_hash']!==$hash)throw new RuntimeException('issuer_invoice_draft_unconfirmed');if($guard)$guard();$this->validated($i,$a,$id);$this->access($i,$a,'create');return$id;}
            if($this->rows('SELECT id FROM '.$this->prefix.'company_invoice_drafts WHERE origin_key=? FOR UPDATE',[$key]))throw new DomainException('company_invoice_draft_conflict');$context=$this->context($i,$a,$payload['client_id'],$payload['currency']);$stamp=gmdate('Y-m-d H:i:s');$pj=CompanyInvoiceDraftsInput::json($payload);$cj=CompanyInvoiceDraftsInput::json($context);$sj=$source===null?null:CompanyInvoiceDraftsInput::json($source);
            $fields=['issuer_id'=>$i,'created_by'=>$a,'origin_key'=>$key,'creation_hash'=>$hash,'last_event_key'=>$key,'state'=>'open','revision'=>1,'client_id'=>$payload['client_id'],'currency_id'=>$payload['currency'],'payload_json'=>$pj,'payload_hash'=>hash('sha256',$pj),'context_json'=>$cj,'context_hash'=>hash('sha256',$cj),'source_json'=>$sj,'source_hash'=>$sj===null?null:hash('sha256',$sj),'invoice_id'=>null,'issue_actor'=>null,'issue_revision'=>null,'issue_key'=>null,'issue_json'=>null,'issue_hash'=>null,'created_at'=>$stamp,'updated_at'=>$stamp];$this->insert('company_invoice_drafts',$fields);$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$expected=['id'=>$id]+$fields;if($this->row($i,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$this->append($i,$a,$id,$key,'created',$hash,['header'=>$expected]);
            $r=$this->validated($i,$a,$id);$this->assertOrigin($i,$a,$payload,$r['context_hash'],$context);if($r!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');if($guard)$guard();if($this->validated($i,$a,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$this->assertOrigin($i,$a,$payload,$expected['context_hash'],$context);$this->access($i,$a,'create');return$id;
        },$guardTables);
    }
    /** Copy only reviewed values. The source remains immutable and never transfers provider/payment authority. */
    public function copyInvoice($issuer,$actor,$source,array $review,string $key):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($source);$key=CompanyInvoiceDraftsInput::key($key);$review=CompanyInvoiceCopiesInput::review($review);$store=new CompanyInvoiceCopiesStorage($this->db,$this->prefix);
        $prepared=$this->transaction(function()use($i,$a,$id,$review,$key,$store):array{$this->access($i,$a,'create');$event=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$key,$i,$a])[0]??null;if($event){$data=$this->verifyEvent($event);$r=$data['header'];$source=CompanyInvoiceDraftsInput::decode($r['source_json']??'[]');if(($source['review']??null)!==$review||($source['invoice']['id']??null)!==$id)throw new DomainException('company_invoice_draft_conflict');return ['payload'=>CompanyInvoiceDraftsInput::decode($r['payload_json']),'source'=>$source,'replay'=>true];}$invoice=$store->sourceOwned($i,$a,$id);if($invoice['revision']!==$review['source_revision'])throw new DomainException('company_invoice_draft_stale');return ['payload'=>['client_id'=>$invoice['client_id'],'date'=>$review['date'],'due_date'=>$review['due_date'],'currency'=>$invoice['currency_id'],'items'=>$invoice['items'],'note'=>$review['note']]+$invoice['options'],'source'=>['invoice'=>$invoice,'review'=>$review],'replay'=>false];});
        $guard=function()use($prepared,$store,$i,$a,$id):void{if(!$prepared['replay']&&$store->sourceOwned($i,$a,$id)!==$prepared['source']['invoice'])throw new DomainException('company_invoice_draft_stale');};return$this->createInternal($i,$a,$prepared['payload'],$key,$prepared['source'],$guard,$store->tables());
    }
    public function change($issuer,$actor,$draft,$revision,?array $invoice,string $key,?callable $guard=null,array $guardTables=[]):void
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($draft);$rev=CompanyInvoiceDraftsInput::revision($revision);$key=CompanyInvoiceDraftsInput::key($key);$payload=$invoice===null?null:CompanyInvoiceDraftsInput::normalize($invoice);$op=$payload===null?'cancelled':'changed';$hash=CompanyInvoiceDraftsInput::hash(['draft_id'=>$id,'revision'=>$rev,'payload'=>$payload]);
        $this->transaction(function()use($i,$a,$id,$rev,$key,$payload,$op,$hash,$guard):void{if($guard)$guard();$this->access($i,$a,$payload===null?'delete':'edit');$r=$this->validated($i,$a,$id);if($this->event($key,$i,$a,$op,$hash)){if($guard)$guard();$this->validated($i,$a,$id);return;}if($r['state']!=='open'||$r['revision']!==$rev)throw new DomainException('company_invoice_draft_stale');$fields=['state'=>$payload===null?'cancelled':'open','revision'=>$rev+1,'last_event_key'=>$key,'updated_at'=>gmdate('Y-m-d H:i:s')];if($payload!==null){$context=$this->context($i,$a,$payload['client_id'],$payload['currency']);$pj=CompanyInvoiceDraftsInput::json($payload);$cj=CompanyInvoiceDraftsInput::json($context);$fields+=['client_id'=>$payload['client_id'],'currency_id'=>$payload['currency'],'payload_json'=>$pj,'payload_hash'=>hash('sha256',$pj),'context_json'=>$cj,'context_hash'=>hash('sha256',$cj)];}$expected=array_replace($r,$fields);$this->update($i,$id,$fields);$this->append($i,$a,$id,$key,$op,$hash,['header'=>$expected]);if($this->validated($i,$a,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');if($payload!==null)$this->assertOrigin($i,$a,$payload,$expected['context_hash'],$context);$this->access($i,$a,$payload===null?'delete':'edit');if($guard)$guard();if($this->validated($i,$a,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');},$guardTables);
    }
    public static function canonicalKey(int $issuer,int $id):string{return hash('sha256','company-invoice-draft-issued:'.$issuer.':'.$id);}
    private function confirmIssued(int $i,int $a,array $r):array
    {
        if($r['invoice_id']===null||$r['issue_actor']===null||$r['issue_revision']===null||$r['issue_key']===null||$r['issue_json']===null||$r['issue_hash']===null||!hash_equals(hash('sha256',$r['issue_json']),$r['issue_hash']))throw new RuntimeException('issuer_invoice_draft_unconfirmed');$result=CompanyInvoiceDraftsInput::decode($r['issue_json']);$payload=CompanyInvoiceDraftsInput::decode($r['payload_json']);$q=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[self::canonicalKey($i,$r['id'])])[0]??null;
        if(!$q||(int)$q['issuer_id']!==$i||(int)$q['actor_id']!==$r['issue_actor']||(int)$q['invoice_id']!==$r['invoice_id']||$q['input_hash']!==CompanyInvoiceCopiesInput::hash(CompanyInvoiceCopiesInput::canonical($payload))||($result['invoice_id']??null)!==$r['invoice_id']||($result['draft_id']??null)!==$r['id'])throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        $dest=$this->rows('SELECT d.formatted_number FROM '.$this->prefix.'invoices n JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=n.id AND d.issuer_id=n.issuer_id WHERE n.id=? AND n.issuer_id=? FOR UPDATE',[$r['invoice_id'],$i])[0]??null;if(!$dest||$dest['formatted_number']!==$result['number'])throw new RuntimeException('issuer_invoice_draft_unconfirmed');return$result;
    }
    public function issue($issuer,$actor,$draft,$revision,string $key,?callable $externalGuard=null,array $externalTables=[],?callable $prepareGuard=null):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($draft);$rev=CompanyInvoiceDraftsInput::revision($revision);$key=CompanyInvoiceDraftsInput::key($key);$prepared=$this->transaction(function()use($i,$a,$id,$prepareGuard):array{if($prepareGuard)$prepareGuard();$this->access($i,$a,'create');$r=$this->validated($i,$a,$id);if($prepareGuard)$prepareGuard();return$r;},$externalTables);$payload=CompanyInvoiceDraftsInput::decode($prepared['payload_json']);$hash=CompanyInvoiceDraftsInput::hash(['draft_id'=>$id,'revision'=>$rev,'payload_hash'=>$prepared['payload_hash']]);$entered=false;
        $guard=function()use($i,$a,$id,$rev,$key,$prepared,$payload,$hash,&$entered,$externalGuard):void{
            if($externalGuard)$externalGuard();$this->access($i,$a,'create');$r=$this->validated($i,$a,$id);if($r['state']==='issued'){if($r['issue_key']!==$key||$r['issue_actor']!==$a||$r['issue_revision']!==$rev||$r['payload_hash']!==$prepared['payload_hash'])throw new DomainException('company_invoice_draft_conflict');$e=$this->event($key,$i,$a,'issued',$hash);if(!$e||(int)$e['draft_id']!==$id)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$this->confirmIssued($i,$a,$r);if($externalGuard)$externalGuard();return;}
            if($r['state']!=='open'||$r['revision']!==$rev||$r!==$prepared)throw new DomainException('company_invoice_draft_stale');$context=CompanyInvoiceDraftsInput::decode($r['context_json']);$this->assertOrigin($i,$a,$payload,$r['context_hash'],$context);$q=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[self::canonicalKey($i,$id)])[0]??null;
            if(!$entered){if($q||$this->rows('SELECT request_key FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? FOR UPDATE',[$key]))throw new DomainException('company_invoice_draft_conflict');$entered=true;return;}
            if(!$q||(int)$q['issuer_id']!==$i||(int)$q['actor_id']!==$a||$q['input_hash']!==CompanyInvoiceCopiesInput::hash(CompanyInvoiceCopiesInput::canonical($payload)))throw new RuntimeException('issuer_invoice_draft_unconfirmed');$invoice=(int)$q['invoice_id'];$copies=new CompanyInvoiceCopiesStorage($this->db,$this->prefix);$dest=$copies->destinationOwned($i,$a,$invoice,true,$payload);$result=['draft_id'=>$id,'invoice_id'=>$invoice,'number'=>$dest['number'],'created_at'=>$dest['created_at'],'payload_hash'=>$r['payload_hash']];$json=CompanyInvoiceDraftsInput::json($result);$fields=['state'=>'issued','revision'=>$rev+1,'last_event_key'=>$key,'invoice_id'=>$invoice,'issue_actor'=>$a,'issue_revision'=>$rev,'issue_key'=>$key,'issue_json'=>$json,'issue_hash'=>hash('sha256',$json),'updated_at'=>gmdate('Y-m-d H:i:s')];$expected=array_replace($r,$fields);$this->update($i,$id,$fields);$this->append($i,$a,$id,$key,'issued',$hash,['header'=>$expected]);if($this->validated($i,$a,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$copies->destinationOwned($i,$a,$invoice,true,$payload);$this->assertOrigin($i,$a,$payload,$expected['context_hash'],$context);$this->access($i,$a,'create');if($externalGuard)$externalGuard();if($this->validated($i,$a,$id)!==$expected)throw new RuntimeException('issuer_invoice_draft_unconfirmed');
        };
        return(new CompanyInvoices($this->db,$this->prefix))->create($i,$a,$payload,self::canonicalKey($i,$id),$guard,array_values(array_unique(array_merge($this->tables(),$externalTables))));
    }
    public function document($issuer,$actor,$draft):array{$i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($draft);return$this->transaction(fn()=>$this->documentInOwned($i,$a,$id));}
    public function documentInOwned($issuer,$actor,$draft):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($draft);IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$r=$this->validated($i,$a,$id);$out=['id'=>$id,'issuer_id'=>$i,'created_by'=>$r['created_by'],'origin_key'=>$r['origin_key'],'state'=>$r['state'],'revision'=>$r['revision'],'payload'=>CompanyInvoiceDraftsInput::decode($r['payload_json']),'context'=>CompanyInvoiceDraftsInput::decode($r['context_json']),'source'=>$r['source_json']===null?null:CompanyInvoiceDraftsInput::decode($r['source_json']),'invoice_id'=>$r['invoice_id'],'issue'=>$r['issue_json']===null?null:CompanyInvoiceDraftsInput::decode($r['issue_json']),'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at']];$this->access($i,$a);if($this->row($i,$id)!==$r)throw new RuntimeException('issuer_invoice_draft_unconfirmed');return$out;
    }
    public function receipt($issuer,$actor,string $key):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$key=CompanyInvoiceDraftsInput::key($key);return$this->transaction(function()use($i,$a,$key):array{$this->access($i,$a);$e=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$key,$i,$a])[0]??null;if(!$e)throw new DomainException('company_invoice_draft_unavailable');$this->verifyEvent($e);$out=$this->documentInOwned($i,$a,(int)$e['draft_id']);$this->access($i,$a);return$out;});
    }
    public function page($issuer,$actor,$after=0):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);return$this->transaction(fn()=>$this->pageInOwned($i,$a,$after));
    }
    public function pageInOwned($issuer,$actor,$after=0):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$this->access($i,$a);$rows=$this->rows('SELECT id,state,revision,client_id,currency_id,invoice_id,created_at,updated_at FROM '.$this->prefix.'company_invoice_drafts WHERE issuer_id=? AND id>? ORDER BY id LIMIT 51',[$i,$after]);$next=count($rows)>50?(int)$rows[49]['id']:null;$rows=array_slice($rows,0,50);$this->access($i,$a);return['items'=>$rows,'next'=>$next];
    }
}
