<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';require_once __DIR__.'/CompanyOperationFilesSchema.php';
/** Native download adapter over the existing private support BLOBs, including legitimate email-only tickets. */
final class NativeCompanyTicketFiles
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyTicketRead($this->db,$this->p))->tables(),['company_ticket_files'])));}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function metadata(array$r):void{
        if(!preg_match('/^[a-f0-9]{64}$/D',(string)($r['file_key']??''))||!preg_match('/^[a-f0-9]{64}$/D',(string)($r['content_hash']??''))||(int)($r['active']??0)!==1)throw new DomainException('native_ticket_file_denied');
        $name=$r['filename']??null;$mime=$r['mime']??null;if(!is_string($name)||$name===''||strlen($name)>191||trim($name)!==$name||preg_match('//u',$name)!==1||preg_match('/[\\x00-\\x1f\\x7f\\\\\\/]/',$name)||!is_string($mime)||strlen($mime)>40||!preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~D',$mime)||(int)$r['byte_size']<1||(int)$r['byte_size']>16777215)throw new DomainException('native_ticket_file_corrupt');
    }
    public static function revision(array$row,string$parentRevision):string{self::metadata($row);if(!preg_match('/^[a-f0-9]{64}$/D',$parentRevision))throw new DomainException('native_ticket_file_denied');return hash('sha256',json_encode([$row,$parentRevision],JSON_THROW_ON_ERROR));}
    private function work(int$i,int$a,$ticket,callable$read,bool$owned=false){
        if($owned)IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyOperationFilesSchema::assertValid($this->db,$this->p);
        $run=function()use($i,$a,$ticket,$read){$r=new NativeCompanyTicketRead($this->db,$this->p);$before=$r->getInOwnedTransaction($i,$a,$ticket);if($before['source_kind']!=='company_ticket')throw new DomainException('native_ticket_file_denied');$result=$read($before);if($r->getInOwnedTransaction($i,$a,$ticket)!==$before)throw new DomainException('native_ticket_file_denied');return$result;};
        return$owned?$run():IssuerTransaction::run($this->db,$this->p,$this->tables(),$run);
    }
    private function replyAssociation(int$i,array$t,array$f):?string{
        if(!NativeCompanyTicketMergeSchema::active($this->db,$this->p))return null;
        $r=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketMergeSchema::FILES.' WHERE file_key=?',[$f['file_key']])[0]??null;if(!$r)return null;
        if((int)$r['issuer_id']!==$i||(int)$r['primary_id']!==$t['source_id']||!hash_equals($r['content_hash'],$f['content_hash']))throw new DomainException('native_ticket_file_provenance_invalid');
        $reply=$this->rows('SELECT issuer_id,primary_id,child_id FROM '.$this->p.NativeCompanyTicketMergeSchema::REPLIES.' WHERE reply_id=?',[$r['reply_id']])[0]??null;
        if(!$reply||(int)$reply['issuer_id']!==$i||(int)$reply['primary_id']!==$t['source_id']||(int)$reply['child_id']!==(int)$r['child_id'])throw new DomainException('native_ticket_file_provenance_invalid');
        $link=$this->rows('SELECT issuer_id,primary_id FROM '.$this->p.NativeCompanyTicketMergeSchema::LINKS.' WHERE child_id=?',[$r['child_id']])[0]??null;$original=$this->rows('SELECT issuer_id,ticket_id,content_hash FROM '.$this->p.'company_ticket_files WHERE file_key=?',[$r['source_file_key']])[0]??null;
        if(!$link||!$original||(int)$link['issuer_id']!==$i||(int)$link['primary_id']!==$t['source_id']||(int)$original['issuer_id']!==$i||(int)$original['ticket_id']!==(int)$r['child_id']||!hash_equals($original['content_hash'],$r['content_hash']))throw new DomainException('native_ticket_file_provenance_invalid');NativeCompanyTicketVisibility::assertReply($this->db,$this->p,$i,(int)$r['reply_id']);return NativeCompanyResourceId::encode('company_ticket_reply',(int)$r['reply_id']);
    }
    public function catalog(int$i,int$a,$ticket):array{return$this->catalogWork($i,$a,$ticket,false);}
    public function catalogInOwnedTransaction(int$i,int$a,$ticket):array{return$this->catalogWork($i,$a,$ticket,true);}
    private function catalogWork(int$i,int$a,$ticket,bool$owned):array{return$this->work($i,$a,$ticket,function(array$t)use($i){$rows=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND active=1 ORDER BY created_at,file_key',[$i,$t['source_id']]);$out=[];foreach($rows as$r){self::metadata($r);try{$reply=$this->replyAssociation($i,$t,$r);}catch(DomainException$e){if($e->getMessage()==='native_ticket_deleted')continue;throw$e;}$r['revision']=self::revision($r,$t['revision_token']);$r['ticketid']=$t['ticketid'];$r['reply_id']=$reply;$out[]=$r;}return$out;},$owned);}
    public function read(int$i,int$a,$ticket,string$key):array{return$this->readWork($i,$a,$ticket,$key,false);}
    public function readInOwnedTransaction(int$i,int$a,$ticket,string$key):array{return$this->readWork($i,$a,$ticket,$key,true);}
    private function readWork(int$i,int$a,$ticket,string$key,bool$owned):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_ticket_file_denied');
        return$this->work($i,$a,$ticket,function(array$t)use($i,$key){
            $row=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1',[$i,$t['source_id'],$key])[0]??null;if(!$row)throw new DomainException('native_ticket_file_denied');self::metadata($row);$this->replyAssociation($i,$t,$row);
            $content=$this->rows('SELECT content FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1 AND content_hash=? AND byte_size=?',[$i,$t['source_id'],$key,$row['content_hash'],$row['byte_size']])[0]['content']??null;
            if(!is_string($content)||strlen($content)!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$content)))throw new DomainException('native_ticket_file_corrupt');
            return['filename'=>$row['filename'],'mime'=>$row['mime'],'content'=>$content,'hash'=>$row['content_hash'],'revision'=>self::revision($row,$t['revision_token'])];
        },$owned);
    }
}
