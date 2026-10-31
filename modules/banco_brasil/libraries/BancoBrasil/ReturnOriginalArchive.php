<?php
declare(strict_types=1);
namespace BancoBrasil;

/** Internal storage participant; caller owns the scoped import/download transaction and ACL. */
final class ReturnOriginalArchive
{
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    public function read(array $file):?array
    {
        $result=$this->db->where('return_file_id',(int)$file['id'])->where('issuer_id',(int)$file['issuer_id'])->get($this->p.'bb_return_originals');
        if(!$result)throw new \RuntimeException('bb_return_original_unavailable');
        $rows=$result->result_array();if(!$rows)return null;
        if(count($rows)!==1)throw new \DomainException('bb_return_original_corrupt');
        $row=$rows[0];
        foreach(['return_file_id'=>(int)$file['id'],'issuer_id'=>(int)$file['issuer_id'],'connection_id'=>(int)$file['connection_id'],'scope_key'=>$file['scope_key'],'content_hash'=>$file['content_hash']]as$key=>$value){
            if((string)($row[$key]??'')!==(string)$value)throw new \DomainException('bb_return_original_corrupt');
        }
        $bytes=$row['content']??null;
        if(!is_string($bytes)||strlen($bytes)<1||strlen($bytes)>16*1024*1024
            ||(int)($row['byte_count']??0)!==strlen($bytes)
            ||!hash_equals((string)$file['content_hash'],hash('sha256',$bytes)))throw new \DomainException('bb_return_original_corrupt');
        return $row;
    }
    public function retain(array $file,int $actor,string $bytes):void
    {
        if($actor<1||strlen($bytes)<1||strlen($bytes)>16*1024*1024
            ||!hash_equals((string)$file['content_hash'],hash('sha256',$bytes)))throw new \DomainException('bb_return_original_mismatch');
        $old=$this->read($file);
        if($old){if($old['content']!==$bytes)throw new \DomainException('bb_return_original_mismatch');return;}
        $row=['return_file_id'=>(int)$file['id'],'issuer_id'=>(int)$file['issuer_id'],'connection_id'=>(int)$file['connection_id'],
            'scope_key'=>$file['scope_key'],'content_hash'=>$file['content_hash'],'byte_count'=>strlen($bytes),'content'=>$bytes,
            'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')];
        if(!$this->db->insert($this->p.'bb_return_originals',$row))throw new \RuntimeException('bb_return_original_write_failed');
        $saved=$this->read($file);
        foreach($row as$key=>$value)if((string)($saved[$key]??'')!==(string)$value)throw new \RuntimeException('bb_return_original_write_unconfirmed');
    }
}
