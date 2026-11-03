<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySpamSchema.php';
require_once __DIR__.'/NativeSpamFilterMatcher.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Native spam catalog, issuer-owned. Original principal routes continue using the original model. */
final class NativeCompanySpamFilters
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','issuers_audit',NativeCompanySpamSchema::TABLE,NativeCompanySpamSchema::COMMANDS];}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function relation(string$rel):void{if(!in_array($rel,['tickets','leads'],true))throw new DomainException('native_spam_relation_invalid');}
    private function admin(int$i,int$a):void{if(!(new IssuerResourceAccess($this->db,$this->p))->administrative($i,$a))throw new DomainException('native_spam_denied');}
    private function records(int$i,string$rel):array{return$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE issuer_id=? AND rel_type=? ORDER BY id',[$i,$rel]);}
    private static function dto(array$r):array{return$r+['native_id'=>NativeCompanyResourceId::encode('company_spam_filter',(int)$r['id']),'revision_token'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];}
    public function listing(int$i,int$a,string$rel):array{
        self::relation($rel);$this->admin($i,$a);NativeCompanySpamSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$rel){$this->admin($i,$a);$r=$this->records($i,$rel);$this->admin($i,$a);if($r!==$this->records($i,$rel))throw new DomainException('native_spam_changed');return array_map([self::class,'dto'],$r);});
    }
    /** Staff-facing ticket detail uses its real source authorization, not arbitrary caller-provided sender. */
    public function blockedSenderForTicket(int$i,int$a,$ticket):bool{
        require_once __DIR__.'/NativeCompanyTicketRead.php';$reader=new NativeCompanyTicketRead($this->db,$this->p);NativeCompanySpamSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$reader->tables()))),function()use($i,$a,$ticket,$reader){$t=$reader->getInOwnedTransaction($i,$a,$ticket);$r=$this->records($i,'tickets');$sender=$t['ticket_email'];$blocked=false;if(is_string($sender)&&$sender!=='')foreach($r as$f)if($f['type']==='sender'&&strtolower($f['value'])===strtolower($sender))$blocked=true;if($reader->getInOwnedTransaction($i,$a,$ticket)!==$t||$this->records($i,'tickets')!==$r)throw new DomainException('native_spam_changed');return$blocked;});
    }
    public function blockTicketSender(int$i,int$a,$ticket,string$expected,string$key):array{
        require_once __DIR__.'/NativeCompanyTicketRead.php';$t=(new NativeCompanyTicketRead($this->db,$this->p))->get($i,$a,$ticket);
        if(!is_string($t['ticket_email'])||$t['ticket_email']===''||!hash_equals($t['revision_token'],$expected))throw new DomainException('native_spam_ticket_stale');
        return$this->mutate($i,$a,'tickets','sender',$t['ticket_email'],null,null,$key,false,[$ticket,$expected]);
    }
    private static function input(string$type,string$value):void{
        if(!in_array($type,['sender','subject','phrase'],true)||$value===''||strlen($value)>64000||preg_match('//u',$value)!==1||str_contains($value,"\0"))throw new DomainException('native_spam_input_invalid');
        // Native values are literal strings, not regexes. Preserve case/whitespace exactly; matching stays native.
    }
    public function save(int$i,int$a,string$rel,string$type,string$value,$id,?string$expected,string$key):array{return$this->mutate($i,$a,$rel,$type,$value,$id,$expected,$key,false);}
    public function delete(int$i,int$a,string$rel,$id,string$expected,string$key):array{return$this->mutate($i,$a,$rel,'','',$id,$expected,$key,true);}
    private function mutate(int$i,int$a,string$rel,string$type,string$value,$id,?string$expected,string$key,bool$delete,?array$ticketProof=null):array{
        self::relation($rel);
        $authorize=function()use($i,$a,$ticketProof,$rel,$type,$value){if($ticketProof===null){$this->admin($i,$a);return;}if($rel!=='tickets'||$type!=='sender'||!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'tickets','edit'))throw new DomainException('native_spam_denied');$reader=new NativeCompanyTicketRead($this->db,$this->p);$t=$reader->getInOwnedTransaction($i,$a,$ticketProof[0]);if(!hash_equals($t['revision_token'],$ticketProof[1])||$t['ticket_email']!==$value)throw new DomainException('native_spam_ticket_stale');};
        if($ticketProof===null)$this->admin($i,$a);if(!$delete)self::input($type,$value);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_spam_input_invalid');$source=null;
        if($id!==null){$d=NativeCompanyResourceId::decode('spam_filter',$id);if($d['source_kind']!=='company_spam_filter'||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new DomainException('native_spam_input_invalid');$source=$d['source_id'];}elseif($delete||$expected!==null)throw new DomainException('native_spam_input_invalid');
        NativeCompanySpamSchema::assertValid($this->db,$this->p);$operation=$delete?'delete':($source?'edit':'create');$hash=hash('sha256',json_encode([$rel,$type,$value,$source,$expected,$ticketProof],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$ticketProof?(new NativeCompanyTicketRead($this->db,$this->p))->tables():[]))),function()use($i,$a,$rel,$type,$value,$source,$expected,$key,$delete,$operation,$hash,$authorize){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$authorize();$receipt=$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::COMMANDS.' WHERE issuer_id=? AND request_key=? FOR UPDATE',[$i,$key])[0]??null;
            if($receipt){if((int)$receipt['actor_id']!==$a||$receipt['operation']!==$operation||!hash_equals($hash,$receipt['input_hash']))throw new DomainException('native_spam_request_conflict');return['id'=>NativeCompanyResourceId::encode('company_spam_filter',(int)$receipt['result_id']),'replayed'=>true];}
            $old=null;if($source){$old=$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE issuer_id=? AND id=? AND rel_type=? FOR UPDATE',[$i,$source,$rel])[0]??null;if(!$old||!hash_equals(self::dto($old)['revision_token'],$expected)||(int)$old['revision']<1||(int)$old['revision']>=2147483647)throw new DomainException('native_spam_stale');}
            $now=date('Y-m-d H:i:s');$target=$source;
            if($delete){IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE issuer_id=? AND id=? AND revision=?',[$i,$source,$old['revision']]);if($this->rows('SELECT id FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE id=?',[$source]))throw new RuntimeException('issuer_spam_write_unconfirmed');}
            else{$row=['issuer_id'=>$i,'type'=>$type,'rel_type'=>$rel,'value'=>$value,'revision'=>$old?(int)$old['revision']+1:1,'actor_id'=>$a,'created_at'=>$old?$old['created_at']:$now,'updated_at'=>$now];if($old)IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanySpamSchema::TABLE.' SET type=?,value=?,revision=?,actor_id=?,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$type,$value,$row['revision'],$a,$now,$i,$source,$old['revision']]);else{IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanySpamSchema::TABLE.'('.implode(',',array_keys($row)).') VALUES(?,?,?,?,?,?,?,?)',array_values($row));$target=(int)$this->db->insert_id();}
                $saved=$this->rows('SELECT issuer_id,type,rel_type,value,revision,actor_id,created_at,updated_at FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE id=?',[$target])[0]??null;if(!$target||!$saved||array_map('strval',$saved)!==array_map('strval',$row))throw new RuntimeException('issuer_spam_write_unconfirmed');}
            $post=$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE issuer_id=? AND id=?',[$i,$target]);
            $audit=['actor_id'=>$a,'event'=>'native_spam_'.$operation,'issuer_id'=>$i,'subject_id'=>$target,'created_at'=>$now,'metadata_json'=>json_encode(['request_key'=>$key,'input_hash'=>$hash],JSON_THROW_ON_ERROR)];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit('.implode(',',array_keys($audit)).') VALUES(?,?,?,?,?,?)',array_values($audit));$auditId=(int)$this->db->insert_id();$stored=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??null;if(!$auditId||!$stored||array_map('strval',$stored)!==array_map('strval',$audit))throw new RuntimeException('issuer_spam_write_unconfirmed');
            $receipt=['issuer_id'=>$i,'request_key'=>$key,'operation'=>$operation,'input_hash'=>$hash,'result_id'=>$target,'actor_id'=>$a,'created_at'=>$now];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanySpamSchema::COMMANDS.'('.implode(',',array_keys($receipt)).') VALUES(?,?,?,?,?,?,?)',array_values($receipt));$saved=$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::COMMANDS.' WHERE issuer_id=? AND request_key=?',[$i,$key])[0]??null;if(!$saved||array_map('strval',$saved)!==array_map('strval',$receipt))throw new RuntimeException('issuer_spam_write_unconfirmed');$authorize();if($post!==$this->rows('SELECT * FROM '.$this->p.NativeCompanySpamSchema::TABLE.' WHERE issuer_id=? AND id=?',[$i,$target]))throw new RuntimeException('issuer_spam_write_unconfirmed');return['id'=>NativeCompanyResourceId::encode('company_spam_filter',$target),'replayed'=>false];
        });
    }
}
