<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurringStorage.php';
require_once __DIR__.'/NativeCompanyCatalogUi.php';

/** Explicit reviewed proposals. A confirmation changes future templates only, never issues an invoice. */
final class CompanyInvoiceRepricing extends CompanyInvoiceRecurringStorage
{
    public const ZERO='0000000000000000000000000000000000000000000000000000000000000000';
    public function __construct($db,string $prefix,private ?Closure $clock=null){parent::__construct($db,$prefix);}
    private function now():string
    {
        $now=$this->clock!==null?($this->clock)():gmdate('Y-m-d H:i:s');
        $parsed=is_string($now)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$now,new DateTimeZone('UTC')):false;
        if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$now)throw new RuntimeException('invoice_reprice_clock_invalid');return$now;
    }

    public function tables():array
    {
        $tables=array_merge(parent::tables(),CompanyInvoiceRepricingSchema::tables());
        foreach(['company_items','company_taxes','company_item_groups','items','taxes','items_groups','company_custom_fields','company_custom_field_values']as$t)if($this->db->table_exists($this->prefix.$t))$tables[]=$t;
        return array_values(array_unique($tables));
    }
    private function transaction(int $i,int $a,callable $fn,bool $write=true)
    {
        CompanyInvoiceRepricingSchema::assertValid($this->db,$this->prefix);
        return$this->scheduleTx($i,$a,$fn,$write);
    }
    private function eligible(array $r):void
    {
        if(!in_array($r['state'],['active','paused'],true))throw new DomainException('invoice_reprice_schedule_busy_or_closed');
    }
    private function future(array $r):array{return CompanyInvoiceRecurringPrices::payload($this->db,$this->prefix,$r,$r['next_date']);}
    private function links(int $i,int $id,array $payload):array
    {
        $out=[];
        foreach($this->rows('SELECT * FROM '.$this->prefix.'company_invoice_schedule_item_links WHERE issuer_id=? AND schedule_id=? ORDER BY line_index FOR UPDATE',[$i,$id])as$row){
            $index=(int)$row['line_index'];
            if(!isset($payload['items'][$index])||!hash_equals($row['line_hash'],CompanyInvoiceRecurringPrices::lineHash($payload['items'][$index])))throw new DomainException('invoice_reprice_link_changed');
            $metadata=['line_index'=>$index,'item_id'=>(string)$row['item_id'],'line_hash'=>$row['line_hash'],'revision'=>$row['revision'],'actor_id'=>(int)$row['actor_id'],'reviewed_at'=>$row['reviewed_at']];
            $proof=$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$i,$row['actor_id'],$id,'invoice_reprice_item_reviewed',self::encode($metadata)]);
            if(count($proof)!==1)throw new RuntimeException('invoice_reprice_link_corrupt');
            $out[$index]=['item_id'=>(string)$row['item_id'],'revision'=>$row['revision'],'line_hash'=>$row['line_hash']];
        }
        return$out;
    }
    private function sourceHash(array $r,array $payload,array $links):string{return self::hash([$payload,$links,$r['context_hash'],$r['config_hash'],$r['transport_hash']]);}
    private function item(int $i,int $a,string $id):array{return(new NativeCompanyCatalogUi($this->db,$this->prefix,$i,$a))->itemForInvoiceEditor($id);}
    private function saveLink(int $i,int $a,int $id,int $index,string $item,array $line):void
    {
        $values=['item_id'=>$item,'line_hash'=>CompanyInvoiceRecurringPrices::lineHash($line),'revision'=>bin2hex(random_bytes(32)),'actor_id'=>$a,'reviewed_at'=>$this->now()];
        $existing=$this->rows('SELECT line_index FROM '.$this->prefix.'company_invoice_schedule_item_links WHERE issuer_id=? AND schedule_id=? AND line_index=? FOR UPDATE',[$i,$id,$index]);
        if($existing)IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_invoice_schedule_item_links SET item_id=?,line_hash=?,revision=?,actor_id=?,reviewed_at=? WHERE issuer_id=? AND schedule_id=? AND line_index=?',[...array_values($values),$i,$id,$index]);
        else$this->insert('company_invoice_schedule_item_links',['issuer_id'=>$i,'schedule_id'=>$id,'line_index'=>$index]+$values);
        $this->scheduleAudit($i,$a,$id,'invoice_reprice_item_reviewed',['line_index'=>$index]+$values);
    }
    public function reviewLink($issuer,$actor,$schedule,$index,string $item,string $payloadHash,string $linkRevision):void
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($schedule);self::key($payloadHash);self::key($linkRevision);
        if((!is_int($index)&&!is_string($index))||!preg_match('/^(0|[1-9][0-9]{0,2})$/D',(string)$index))throw new DomainException('invoice_reprice_invalid');$index=(int)$index;
        $this->transaction($i,$a,function()use($i,$a,$id,$index,$item,$payloadHash,$linkRevision){
            $r=$this->schedule($i,$id);$this->eligible($r);$payload=$this->future($r);$links=$this->links($i,$id,$payload);
            if(!hash_equals(self::hash($payload),$payloadHash)||!hash_equals($links[$index]['revision']??self::ZERO,$linkRevision))throw new DomainException('invoice_reprice_stale');
            if(!isset($payload['items'][$index]))throw new DomainException('invoice_reprice_invalid');$catalog=$this->item($i,$a,$item);
            $this->saveLink($i,$a,$id,$index,(string)$catalog['id'],$payload['items'][$index]);
        });
    }
    public function directory($issuer,$actor):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);
        return$this->transaction($i,$a,function()use($i,$a){$out=[];
            foreach($this->rows('SELECT id FROM '.$this->prefix.'company_invoice_schedules WHERE issuer_id=? ORDER BY id',[$i])as$ref){
                $r=$this->schedule($i,(int)$ref['id']);if(!in_array($r['state'],['active','paused','processing','paused_processing'],true))continue;
                $payload=$this->future($r);$links=$this->links($i,(int)$r['id'],$payload);$context=$this->decode($r['context_json']);
                $out[]=['id'=>(int)$r['id'],'state'=>$r['state'],'customer'=>$context['recipient']['name'],'currency'=>$context['currency']['name'],'next_date'=>$r['next_date'],'payload'=>$payload,'payload_hash'=>self::hash($payload),'links'=>$links,'source_hash'=>$this->sourceHash($r,$payload,$links),'payment_modes'=>$payload['native_preferences']['allowed_payment_modes']??[]];
            }
            return$out;
        },false);
    }
    public static function timing(string $timezone,?string $local):?string
    {
        if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))throw new DomainException('invoice_reprice_timezone_invalid');
        if($local===null||$local==='')return null;
        $zone=new DateTimeZone($timezone);$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,$zone);
        if(!$date||$date->format('Y-m-d\TH:i')!==$local)throw new DomainException('invoice_reprice_time_invalid');
        // Reject nonexistent or ambiguous wall times rather than selecting an offset silently.
        $wall=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('UTC'))->getTimestamp();$matches=[];
        foreach($zone->getTransitions($wall-172800,$wall+172800)?:[]as$transition){$stamp=$wall-$transition['offset'];if((new DateTimeImmutable('@'.$stamp))->setTimezone($zone)->format('Y-m-d\TH:i')===$local)$matches[$stamp]=true;}
        if(count($matches)!==1)throw new DomainException('invoice_reprice_time_ambiguous');
        return$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    private function normalize(array $input):array
    {
        if(array_diff(array_keys($input),['rules','targets','timezone','execute_local','filters'])||!is_array($input['rules']??null)||!array_is_list($input['rules'])||!is_array($input['targets']??null)||!array_is_list($input['targets'])||!is_string($input['timezone']??null)||!is_string($input['execute_local']??'')||count($input['rules'])<1||count($input['rules'])>100||count($input['targets'])<1||count($input['targets'])>200)throw new DomainException('invoice_reprice_invalid');
        self::timing($input['timezone'],$input['execute_local']??'');$rules=[];
        foreach($input['rules']as$rule){if(!is_array($rule)||array_keys($rule)!==['item_id','rate']||!is_string($rule['item_id'])||!is_string($rule['rate'])||!preg_match('/^(0|[1-9][0-9]{0,10})(?:\.[0-9]{1,4})?$/D',$rule['rate'])||isset($rules[$rule['item_id']]))throw new DomainException('invoice_reprice_rule_invalid');$rules[$rule['item_id']]=$rule['rate'];}
        $seen=[];
        foreach($input['targets']as&$target){
            if(!is_array($target)||array_diff(array_keys($target),['schedule_id','source_hash','lines','additions'])||!is_array($target['lines']??null)||!array_is_list($target['lines'])||!is_array($target['additions']??null)||!array_is_list($target['additions'])||count($target['lines'])+count($target['additions'])<1||count($target['lines'])+count($target['additions'])>200)throw new DomainException('invoice_reprice_invalid');
            $target['schedule_id']=IssuerPolicy::id($target['schedule_id']??null);if(isset($seen[$target['schedule_id']])||!is_string($target['source_hash']??null))throw new DomainException('invoice_reprice_invalid');self::key($target['source_hash']);$seen[$target['schedule_id']]=true;$indices=[];
            foreach($target['lines']as&$line){if(!is_array($line)||array_diff(array_keys($line),['index','item_id','qty'])||(!is_int($line['index']??null)&&!is_string($line['index']??null))||!preg_match('/^(0|[1-9][0-9]{0,2})$/D',(string)$line['index'])||!is_string($line['item_id']??null)||!isset($rules[$line['item_id']])||isset($indices[(int)$line['index']])||isset($line['qty'])&&!is_string($line['qty']))throw new DomainException('invoice_reprice_invalid');$line['index']=(int)$line['index'];$indices[$line['index']]=true;$line['qty']=$line['qty']??'';}unset($line);
            foreach($target['additions']as$line)if(!is_array($line)||array_keys($line)!==['item_id','qty']||!is_string($line['item_id'])||!isset($rules[$line['item_id']])||!is_string($line['qty'])||$line['qty']==='')throw new DomainException('invoice_reprice_invalid');
        }unset($target);
        $filters=$input['filters']??[];if(!is_array($filters)||array_diff(array_keys($filters),['item','payment_mode']))throw new DomainException('invoice_reprice_invalid');foreach($filters as$value)if(!is_string($value)||strlen($value)>64)throw new DomainException('invoice_reprice_invalid');
        return['rules'=>$input['rules'],'targets'=>$input['targets'],'timezone'=>$input['timezone'],'execute_local'=>$input['execute_local']??'','filters'=>$filters];
    }
    private function calculation(array $payload):array{return CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));}
    private function simulate(int $i,int $a,array $input):array
    {
        $rules=[];$catalog=[];
        foreach($input['rules']as$rule){$item=$this->item($i,$a,$rule['item_id']);$catalog[$rule['item_id']]=$item;$rules[$rule['item_id']]=$rule['rate'];}
        $out=[];
        foreach($input['targets']as$target){
            $id=$target['schedule_id'];$r=$this->schedule($i,$id);$this->eligible($r);$before=$this->future($r);$links=$this->links($i,$id,$before);
            if(!hash_equals($target['source_hash'],$this->sourceHash($r,$before,$links)))throw new DomainException('invoice_reprice_stale');
            $after=$before;$changes=[];$added=[];
            foreach($target['lines']as$line){$index=$line['index'];$item=$line['item_id'];if(!isset($links[$index])||$links[$index]['item_id']!==$item||!isset($before['items'][$index]))throw new DomainException('invoice_reprice_review_required');
                $after['items'][$index]['rate']=$rules[$item];if($line['qty']!=='')$after['items'][$index]['qty']=$line['qty'];$changes[]=['index'=>$index,'item_id'=>$item,'kind'=>'change'];
            }
            if($target['additions']&&count($links)!==count($before['items']))throw new DomainException('invoice_reprice_review_required');
            foreach($target['additions']as$line){$item=$line['item_id'];if(in_array($item,array_column($links,'item_id'),true)||isset($added[$item]))throw new DomainException('invoice_reprice_item_already_present');$c=$catalog[$item];$taxes=[];
                foreach([['taxname','taxrate'],['taxname_2','taxrate_2']]as[$name,$rate])if(isset($c[$name],$c[$rate])&&$c[$name]!==null&&$c[$name]!=='')$taxes[]=['name'=>(string)$c[$name],'rate'=>(string)$c[$rate]];
                $index=count($after['items']);$after['items'][]=['description'=>$c['description'],'qty'=>$line['qty'],'rate'=>$rules[$item],'taxes'=>$taxes,'long_description'=>$c['long_description']??'','unit'=>$c['unit']??''];$added[$item]=true;$changes[]=['index'=>$index,'item_id'=>$item,'kind'=>'addition'];
            }
            $after['items']=CompanyInvoiceLines::normalize($after['items'],$after['calculation_contract']??null);self::input($after);$context=$this->decode($r['context_json']);
            $out[]=['schedule_id'=>$id,'source_hash'=>$target['source_hash'],'customer'=>$context['recipient']['name'],'currency'=>$context['currency']['name'],'before'=>$before,'after'=>$after,'before_amounts'=>$this->calculation($before),'after_amounts'=>$this->calculation($after),'changes'=>$changes];
        }
        return['rules'=>array_map(fn($r)=>$r+['catalog'=>$catalog[$r['item_id']]],$input['rules']),'targets'=>$out,'timezone'=>$input['timezone'],'execute_local'=>$input['execute_local'],'filters'=>$input['filters']];
    }
    public function preview($issuer,$actor,array $input,string $key):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);$input=$this->normalize($input);$hash=self::hash($input);$execute=self::timing($input['timezone'],$input['execute_local']);
        if($execute!==null&&$execute<=$this->now())throw new DomainException('invoice_reprice_time_past');
        return$this->transaction($i,$a,function()use($i,$a,$input,$hash,$key,$execute){
            $old=$this->rows('SELECT issuer_id,actor_id,input_hash FROM '.$this->prefix.'company_invoice_reprice_proposals WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$i||(int)$old['actor_id']!==$a||!hash_equals($old['input_hash'],$hash))throw new DomainException('invoice_reprice_conflict');return$this->read($i,$key);}
            $preview=$this->simulate($i,$a,$input);$revision=self::hash([$key,$i,$a,$hash,self::hash($preview)]);
            $this->insert('company_invoice_reprice_proposals',['request_key'=>$key,'issuer_id'=>$i,'actor_id'=>$a,'state'=>'preview','revision'=>$revision,'input_json'=>self::encode($input),'input_hash'=>$hash,'preview_json'=>self::encode($preview),'preview_hash'=>self::hash($preview),'timezone'=>$input['timezone'],'execute_at'=>$execute,'created_at'=>$this->now(),'confirmed_at'=>null,'applied_at'=>null]);
            $this->scheduleAudit($i,$a,0,'invoice_reprice_preview',['request_key'=>$key,'revision'=>$revision,'input_hash'=>$hash,'preview_hash'=>self::hash($preview)]);
            return$this->read($i,$key);
        });
    }
    private function read(int $i,string $key):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_reprice_proposals WHERE issuer_id=? AND request_key=? FOR UPDATE',[$i,$key])[0]??null;if(!$r)throw new DomainException('invoice_reprice_denied');
        foreach(['input','preview']as$field){$r[$field]=$this->decode($r[$field.'_json']);if(!hash_equals($r[$field.'_hash'],self::hash($r[$field])))throw new RuntimeException('invoice_reprice_proposal_corrupt');}
        if(!hash_equals($r['revision'],self::hash([$key,$i,(int)$r['actor_id'],$r['input_hash'],$r['preview_hash']]))||$r['timezone']!==$r['input']['timezone']||$r['execute_at']!==self::timing($r['input']['timezone'],$r['input']['execute_local'])||!in_array($r['state'],['preview','scheduled','applied','cancelled'],true))throw new RuntimeException('invoice_reprice_proposal_corrupt');
        $proof=$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$i,$r['actor_id'],0,'invoice_reprice_preview',self::encode(['request_key'=>$key,'revision'=>$r['revision'],'input_hash'=>$r['input_hash'],'preview_hash'=>$r['preview_hash']])]);
        if(count($proof)!==1)throw new RuntimeException('invoice_reprice_proposal_corrupt');
        return$r;
    }
    public function proposal($issuer,$actor,string $key):array{$i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);return$this->transaction($i,$a,fn()=>$this->read($i,$key),false);}
    public function proposals($issuer,$actor):array{$i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);return$this->transaction($i,$a,fn()=>$this->rows('SELECT request_key,state,created_at,execute_at,timezone FROM '.$this->prefix.'company_invoice_reprice_proposals WHERE issuer_id=? ORDER BY created_at DESC LIMIT 100',[$i]),false);}
    private function apply(int $i,int $a,array $r):array
    {
        $current=$this->simulate($i,$a,$r['input']);if(!hash_equals(self::hash($current),$r['preview_hash']))throw new DomainException('invoice_reprice_stale');
        $now=$this->now();IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_invoice_reprice_proposals SET state=?,confirmed_at=COALESCE(confirmed_at,?),applied_at=? WHERE issuer_id=? AND request_key=?',['applied',$now,$now,$i,$r['request_key']]);
        foreach($current['targets']as$target){$id=$target['schedule_id'];$schedule=$this->schedule($i,$id);
            $row=['issuer_id'=>$i,'schedule_id'=>$id,'actor_id'=>$a,'proposal_key'=>$r['request_key'],'effective_date'=>$schedule['next_date'],'before_hash'=>self::hash($target['before']),'payload_json'=>self::encode($target['after']),'payload_hash'=>self::hash($target['after']),'created_at'=>$now];
            $this->insert('company_invoice_schedule_prices',$row);$this->scheduleAudit($i,$a,$id,'invoice_reprice_applied',CompanyInvoiceRecurringPrices::auditMetadata($row));
            foreach($target['changes']as$change)if($change['kind']==='addition')$this->saveLink($i,$a,$id,$change['index'],$change['item_id'],$target['after']['items'][$change['index']]);
            $this->setSchedule($i,$id,['revision'=>(int)$schedule['revision']+1,'updated_at'=>$now]);
            if($this->future($this->schedule($i,$id))!==$target['after'])throw new RuntimeException('invoice_reprice_write_unconfirmed');
        }
        return$this->read($i,$r['request_key']);
    }
    public function confirm($issuer,$actor,string $key,string $revision):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);self::key($revision);
        return$this->transaction($i,$a,function()use($i,$a,$key,$revision){$r=$this->read($i,$key);
            if((int)$r['actor_id']!==$a||!hash_equals($r['revision'],$revision))throw new DomainException('invoice_reprice_stale');if(in_array($r['state'],['applied','scheduled'],true))return$r;if($r['state']!=='preview')throw new DomainException('invoice_reprice_closed');
            if(!hash_equals(self::hash($this->simulate($i,$a,$r['input'])),$r['preview_hash']))throw new DomainException('invoice_reprice_stale');
            if($r['execute_at']!==null){if($r['execute_at']<=$this->now())throw new DomainException('invoice_reprice_time_past');IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_invoice_reprice_proposals SET state=?,confirmed_at=? WHERE issuer_id=? AND request_key=?',['scheduled',$this->now(),$i,$key]);return$this->read($i,$key);}
            return$this->apply($i,$a,$r);
        });
    }
    public function execute($issuer,$actor,string $key):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);
        return$this->transaction($i,$a,function()use($i,$a,$key){$r=$this->read($i,$key);if((int)$r['actor_id']!==$a)throw new DomainException('invoice_reprice_denied');if($r['state']==='applied')return$r;if($r['state']!=='scheduled'||$r['execute_at']===null||$r['execute_at']>$this->now())throw new DomainException('invoice_reprice_not_due');return$this->apply($i,$a,$r);});
    }
    public function cancel($issuer,$actor,string $key,string $revision):void
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);self::key($key);self::key($revision);
        $this->transaction($i,$a,function()use($i,$a,$key,$revision){$r=$this->read($i,$key);if(!hash_equals($r['revision'],$revision)||!in_array($r['state'],['preview','scheduled','cancelled'],true))throw new DomainException('invoice_reprice_stale');IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_invoice_reprice_proposals SET state=? WHERE issuer_id=? AND request_key=?',['cancelled',$i,$key]);});
    }
    public function processDue():array
    {
        if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active())throw new DomainException('invoice_reprice_worker_context');CompanyInvoiceRepricingSchema::assertValid($this->db,$this->prefix);$out=['applied'=>0,'blocked'=>0];
        $rows=$this->rows('SELECT issuer_id,actor_id,request_key FROM '.$this->prefix.'company_invoice_reprice_proposals WHERE state=? AND execute_at<=? ORDER BY execute_at,request_key LIMIT 50',['scheduled',$this->now()]);
        foreach($rows as$r){try{$this->execute((int)$r['issuer_id'],(int)$r['actor_id'],$r['request_key']);$out['applied']++;}catch(Throwable $error){$out['blocked']++;}}
        return$out;
    }
}
