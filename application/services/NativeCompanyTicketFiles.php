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
    private function work(int$i,int$a,$ticket,callable$read){
        CompanyOperationFilesSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$ticket,$read){$r=new NativeCompanyTicketRead($this->db,$this->p);$before=$r->getInOwnedTransaction($i,$a,$ticket);if($before['source_kind']!=='company_ticket')throw new DomainException('native_ticket_file_denied');$result=$read($before);if($r->getInOwnedTransaction($i,$a,$ticket)!==$before)throw new DomainException('native_ticket_file_denied');return$result;});
    }
    public function catalog(int$i,int$a,$ticket):array{return$this->work($i,$a,$ticket,function(array$t)use($i){$rows=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND active=1 ORDER BY created_at,file_key',[$i,$t['source_id']]);foreach($rows as&$r){self::metadata($r);$r['revision']=self::revision($r,$t['revision_token']);$r['ticketid']=$t['ticketid'];}return$rows;});}
    public function read(int$i,int$a,$ticket,string$key):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_ticket_file_denied');
        return$this->work($i,$a,$ticket,function(array$t)use($i,$key){
            $row=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1',[$i,$t['source_id'],$key])[0]??null;if(!$row)throw new DomainException('native_ticket_file_denied');self::metadata($row);
            $content=$this->rows('SELECT content FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1 AND content_hash=? AND byte_size=?',[$i,$t['source_id'],$key,$row['content_hash'],$row['byte_size']])[0]['content']??null;
            if(!is_string($content)||strlen($content)!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$content)))throw new DomainException('native_ticket_file_corrupt');
            return['filename'=>$row['filename'],'mime'=>$row['mime'],'content'=>$content,'hash'=>$row['content_hash'],'revision'=>self::revision($row,$t['revision_token'])];
        });
    }
}
