<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';
require_once __DIR__.'/NativeCompanyTicketPublicSchema.php';
/** Possession grants access to one published ticket, never staff/contact identity or a company-wide session. */
final class NativeCompanyTicketPublicCapability
{
    public function __construct(private $db,private string$p,private string$secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new DomainException('native_ticket_public_unavailable');}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyTicketRead($this->db,$this->p))->tables(),[NativeCompanyTicketPublicSchema::TABLE])));}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function policy(int$i):void{
        if($i<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1',[$i]))throw new DomainException('native_ticket_public_unavailable');
        $s=new CompanySettingsStorage($this->db,$this->p);$v=$s->exists($i,'disable_ticket_public_url')?$s->get($i,'disable_ticket_public_url'):NativeCompanyTicketPreferences::defaultValue('disable_ticket_public_url');
        if($v!=='0')throw new DomainException('native_ticket_public_unavailable');
    }
    private function source(int$i,int$id):array{
        NativeCompanyTicketVisibility::assertTicket($this->db,$this->p,$i,$id);$ticket=$this->rows('SELECT id,issuer_id,client_id FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$i,$id])[0]??null;if(!$ticket)throw new DomainException('native_ticket_public_unavailable');
        $meta=$this->rows('SELECT sender_origin,sender_email,contact_id FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$i,$id])[0]??null;
        if($ticket['client_id']!==null){$client=(int)$ticket['client_id'];$owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client])[0]??null;if(!$owner||(int)$owner['owner_issuer_id']!==$i||!$this->rows('SELECT userid FROM '.$this->p.'clients WHERE userid=? AND active=1',[$client]))throw new DomainException('native_ticket_public_unavailable');if($meta&&$meta['contact_id']!==null&&!$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1',[$meta['contact_id'],$client]))throw new DomainException('native_ticket_public_unavailable');}
        elseif(!$meta||$meta['sender_origin']!=='staff_entered'||$meta['contact_id']!==null||!filter_var($meta['sender_email'],FILTER_VALIDATE_EMAIL))throw new DomainException('native_ticket_public_unavailable');
        return$ticket;
    }
    private function token(array$r):string{return hash_hmac('sha256','native-ticket-public-v1|'.$r['issuer_id'].'|'.$r['ticket_id'].'|'.$r['nonce'],$this->secret);}
    private function valid(array$r):void{foreach(['nonce','key_hash']as$k)if(!is_string($r[$k]??null)||!preg_match('/^[a-f0-9]{64}$/D',$r[$k]))throw new DomainException('native_ticket_public_unavailable');if((int)$r['enabled']!==1||(int)$r['revision']<1||!hash_equals($r['key_hash'],hash('sha256',$this->token($r))))throw new DomainException('native_ticket_public_unavailable');}
    /** Mutation caller owns issuer lock and all declarations. Never called by page rendering. */
    public function issueInOwnedTransaction(int$i,int$a,$id,?object$creationProof=null):string{
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());NativeCompanyTicketPublicSchema::assertValid($this->db,$this->p);$this->policy($i);
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$before=$reader->getInOwnedTransaction($i,$a,$id);if($before['source_kind']!=='company_ticket')throw new DomainException('native_ticket_public_unavailable');
        $source=$this->source($i,$before['source_id']);
        if($creationProof!==null){if(get_class($creationProof)!=='NativeCompanyTicketCommands')throw new DomainException('native_ticket_public_unavailable');$creationProof->assertCreatedInOwned($this->db,$this->p,$i,$a,'company_ticket',$before['source_id']);}
        elseif(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'tickets','edit'))throw new DomainException('native_ticket_public_unavailable');
        $row=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketPublicSchema::TABLE.' WHERE issuer_id=? AND ticket_id=? FOR UPDATE',[$i,$before['source_id']])[0]??null;
        if(!$row){$now=gmdate('Y-m-d H:i:s');$row=['ticket_id'=>$before['source_id'],'issuer_id'=>$i,'nonce'=>bin2hex(random_bytes(32)),'key_hash'=>'','enabled'=>1,'revision'=>1,'actor_id'=>$a,'created_at'=>$now,'updated_at'=>$now];$row['key_hash']=hash('sha256',$this->token($row));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyTicketPublicSchema::TABLE.'('.implode(',',array_keys($row)).') VALUES(?,?,?,?,?,?,?,?,?)',array_values($row));$saved=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketPublicSchema::TABLE.' WHERE issuer_id=? AND ticket_id=?',[$i,$before['source_id']])[0]??null;if(!$saved||array_map('strval',$saved)!==array_map('strval',$row))throw new RuntimeException('issuer_ticket_write_unconfirmed');$row=$saved;
            $audit=['actor_id'=>$a,'event'=>'native_ticket_public_issued','issuer_id'=>$i,'subject_id'=>$before['source_id'],'created_at'=>$now,'metadata_json'=>json_encode(['capability_hash'=>$row['key_hash'],'revision'=>1],JSON_THROW_ON_ERROR)];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit('.implode(',',array_keys($audit)).') VALUES(?,?,?,?,?,?)',array_values($audit));$aid=(int)$this->db->insert_id();$stored=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$aid])[0]??null;if(!$stored||array_map('strval',$stored)!==array_map('strval',$audit))throw new RuntimeException('issuer_ticket_write_unconfirmed');}
        $this->valid($row);$this->policy($i);if($reader->getInOwnedTransaction($i,$a,$id)!==$before||$this->source($i,$before['source_id'])!==$source)throw new DomainException('native_ticket_public_unavailable');return$this->token($row);
    }
    /** Explicit issuer is mandatory. This projection contains no message/email/name until its capability is checked. */
    public function resolve(int$i,string$key):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_ticket_public_unavailable');NativeCompanyTicketPublicSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$key){$this->policy($i);$rows=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketPublicSchema::TABLE.' WHERE issuer_id=? AND key_hash=?',[$i,hash('sha256',$key)]);if(count($rows)!==1)throw new DomainException('native_ticket_public_unavailable');$r=$rows[0];$this->valid($r);if(!hash_equals($this->token($r),$key))throw new DomainException('native_ticket_public_unavailable');
            $ticket=$this->source($i,(int)$r['ticket_id']);
            $this->policy($i);return['issuer_id'=>$i,'ticket_id'=>(int)$ticket['id'],'canonical_id'=>NativeCompanyResourceId::encode('company_ticket',(int)$ticket['id']),'capability_revision'=>(int)$r['revision'],'capability_hash'=>$r['key_hash']];
        });
    }
}
