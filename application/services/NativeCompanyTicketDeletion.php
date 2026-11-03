<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';
/** Explicit append-only native deletion; no historical row, receipt, attachment or author is destroyed. */
final class NativeCompanyTicketDeletion
{
    public function __construct(private $db,private string$p){}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int$i,int$a):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);foreach(['view','edit']as$cap)if(!$acl->can($i,$a,'tickets',$cap))throw new DomainException('native_ticket_delete_denied');
        $policy=(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($i,$a);
        if(!$acl->administrative($i,$a)&&$policy['values']['allow_non_admin_members_to_delete_tickets_and_replies']!=='1')throw new DomainException('native_ticket_delete_denied');
    }
    public function delete(int$i,int$a,$ticket,string$expected,string$nonce,$reply=null,?string$replyExpected=null):array
    {
        $plan=$this->plan($i,$a,$ticket,$expected,$nonce,$reply,$replyExpected);
        return IssuerTransaction::run($this->db,$this->p,$plan['tables'],$plan['run']);
    }
    /** Every selected tombstone and its original receipt commits or rolls back as one issuer command. */
    public function deleteMany(int$i,int$a,array$selected,string$nonce):array
    {
        if(!$selected||count($selected)>100||!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new DomainException('native_ticket_delete_invalid');ksort($selected,SORT_STRING);
        $hash=hash('sha256',json_encode($selected,JSON_THROW_ON_ERROR));$plans=[];$tables=[];
        foreach($selected as$id=>$expected){if(!is_string($expected))throw new DomainException('native_ticket_delete_invalid');$childNonce=hash('sha256','native-ticket-bulk-delete|'.$i.'|'.$a.'|'.$nonce.'|'.$hash.'|'.$id);$plan=$this->plan($i,$a,(string)$id,$expected,$childNonce);$plans[]=$plan;$tables=array_merge($tables,$plan['tables']);}
        $first=NativeCompanyResourceId::decode('ticket',(string)array_key_first($selected))['source_id'];
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique($tables)),function()use($i,$a,$selected,$nonce,$hash,$first,$plans){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$this->authorize($i,$a);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_ticket_requests WHERE request_key=? FOR UPDATE',[$nonce])[0]??null;
            if($receipt&&((int)$receipt['issuer_id']!==$i||(int)$receipt['actor_id']!==$a||$receipt['operation']!=='native_tickets_bulk_deleted'||!hash_equals($receipt['input_hash'],$hash)||(int)$receipt['result_id']!==$first))throw new DomainException('native_ticket_request_conflict');
            foreach($plans as$plan){$r=($plan['run'])();if($r['replayed']!==($receipt!==null))throw new RuntimeException('issuer_ticket_write_unconfirmed');}
            if(!$receipt){$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$nonce,$i,$a,$hash,'native_tickets_bulk_deleted',$first,$now]);if(!$this->rows('SELECT request_key FROM '.$this->p.'company_ticket_requests WHERE request_key=? AND issuer_id=? AND actor_id=? AND input_hash=? AND operation=? AND result_id=?',[$nonce,$i,$a,$hash,'native_tickets_bulk_deleted',$first]))throw new RuntimeException('issuer_ticket_write_unconfirmed');}
            $this->authorize($i,$a);return['success'=>true,'deleted'=>count($selected),'replayed'=>$receipt!==null];
        });
    }
    private function plan(int$i,int$a,$ticket,string$expected,string$nonce,$reply=null,?string$replyExpected=null):array
    {
        foreach([$expected,$nonce]as$v)if(!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('native_ticket_delete_invalid');
        $d=NativeCompanyResourceId::decode('ticket',$ticket);if($d['source_kind']!=='company_ticket')throw new DomainException('native_ticket_delete_denied');$rid=null;
        if($reply!==null){$r=NativeCompanyResourceId::decode('ticket_reply',$reply);if($r['source_kind']!=='company_ticket_reply'||!is_string($replyExpected)||!preg_match('/^[a-f0-9]{64}$/D',$replyExpected))throw new DomainException('native_ticket_delete_invalid');$rid=$r['source_id'];}
        elseif($replyExpected!==null)throw new DomainException('native_ticket_delete_invalid');
        if(!NativeCompanyTicketVisibility::enabled($this->db,$this->p))throw new DomainException('native_ticket_delete_schema_not_ready');
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$table=$rid===null?NativeCompanyTicketLifecycleSchema::TICKETS:NativeCompanyTicketLifecycleSchema::REPLIES;$field=$rid===null?'ticket_id':'reply_id';$source=$rid??$d['source_id'];$sourceHash=$rid===null?$expected:$replyExpected;
        $inputHash=hash('sha256',json_encode([$ticket,$expected,$reply,$replyExpected],JSON_THROW_ON_ERROR));$operation=$rid===null?'native_ticket_deleted':'native_ticket_reply_deleted';
        $tables=array_values(array_unique(array_merge($reader->tables(),array_keys(NativeCompanyTicketReplySchema::definitions($this->p)),['company_portal_support_replies','company_ticket_requests'])));
        $execute=function()use($i,$a,$ticket,$expected,$nonce,$reply,$replyExpected,$d,$rid,$reader,$table,$field,$source,$sourceHash,$inputHash,$operation){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$this->authorize($i,$a);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_ticket_requests WHERE request_key=? FOR UPDATE',[$nonce])[0]??null;
            if($receipt){
                if((int)$receipt['issuer_id']!==$i||(int)$receipt['actor_id']!==$a||(int)$receipt['result_id']!==$d['source_id']||$receipt['operation']!==$operation||!hash_equals($receipt['input_hash'],$inputHash))throw new DomainException('native_ticket_request_conflict');
                $this->confirm($table,$field,$source,$i,$a,$d['source_id'],$nonce,$sourceHash);
                if($rid===null)$reader->deletionReceiptAuthority($i,$a,$ticket,$nonce,$sourceHash);else$reader->getInOwnedTransaction($i,$a,$ticket);
                $this->authorize($i,$a);return['success'=>true,'deleted'=>true,'replayed'=>true,'ticketid'=>$ticket,'replyid'=>$reply];
            }
            $before=$reader->getInOwnedTransaction($i,$a,$ticket);if(!hash_equals($before['revision_token'],$expected))throw new DomainException('native_ticket_stale');
            if($rid!==null){$matches=array_values(array_filter($reader->repliesInOwnedTransaction($i,$a,$ticket),fn($r)=>$r['source_id']===$rid));if(count($matches)!==1||!hash_equals($matches[0]['revision_token'],$replyExpected))throw new DomainException('native_ticket_reply_stale');}
            if($this->rows('SELECT '.$field.' FROM '.$this->p.$table.' WHERE '.$field.'=?',[$source]))throw new DomainException('native_ticket_deleted');
            $now=date('Y-m-d H:i:s');$fields=[$field=>$source];if($rid!==null)$fields['ticket_id']=$d['source_id'];$fields+=['issuer_id'=>$i,'actor_id'=>$a,'source_hash'=>$sourceHash,'request_key'=>$nonce,'created_at'=>$now];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'('.implode(',',array_keys($fields)).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$this->confirm($table,$field,$source,$i,$a,$d['source_id'],$nonce,$sourceHash);
            $row=$this->rows('SELECT revision FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$i,$d['source_id']])[0]??null;
            if(!$row||(int)$row['revision']<1||(int)$row['revision']>=2147483647)throw new DomainException('native_ticket_revision_invalid');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$i,$d['source_id'],(int)$row['revision']]);
            $after=$this->rows('SELECT revision FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$i,$d['source_id']])[0]??null;if(!$after||(int)$after['revision']!==(int)$row['revision']+1)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $meta=json_encode(['request_nonce'=>$nonce,'reply_id'=>$rid,'source_hash'=>$sourceHash],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,$operation,$i,$d['source_id'],$now,$meta]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$a,$i,$d['source_id'],$operation,$meta]))throw new RuntimeException('issuer_ticket_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$nonce,$i,$a,$inputHash,$operation,$d['source_id'],$now]);
            if(!$this->rows('SELECT request_key FROM '.$this->p.'company_ticket_requests WHERE request_key=? AND issuer_id=? AND actor_id=? AND input_hash=? AND operation=? AND result_id=?',[$nonce,$i,$a,$inputHash,$operation,$d['source_id']]))throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $this->confirm($table,$field,$source,$i,$a,$d['source_id'],$nonce,$sourceHash);$this->authorize($i,$a);
            if($rid===null)$reader->deletionReceiptAuthority($i,$a,$ticket,$nonce,$sourceHash);else$reader->getInOwnedTransaction($i,$a,$ticket);
            return['success'=>true,'deleted'=>true,'replayed'=>false,'ticketid'=>$ticket,'replyid'=>$reply];
        };
        return ['tables'=>$tables,'run'=>$execute];
    }
    private function confirm(string$table,string$field,int$source,int$i,int$a,int$ticket,string$nonce,string$hash):void
    {
        $r=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$field.'=?',[$source])[0]??null;
        if(!$r||(int)$r['issuer_id']!==$i||(int)$r['actor_id']!==$a||(int)$r['ticket_id']!==$ticket||!hash_equals($r['request_key'],$nonce)||!hash_equals($r['source_hash'],$hash))throw new RuntimeException('issuer_ticket_write_unconfirmed');
    }
}
