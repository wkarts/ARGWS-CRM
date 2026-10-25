<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Native queue maintenance. Envelope bodies, tokens and attachments never enter the Settings DTO. */
final class NativeCompanyMailQueue
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','issuers_audit','staff','mail_queue'];}
    private function auth(int $i,int $a,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'settings',$cap))throw new DomainException('native_mail_queue_denied');}
    public function page(int $i,int $a):array
    {
        $this->auth($i,$a,'view');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a){
            $this->auth($i,$a,'view');$out=[];
            foreach(IssuerStorage::query($this->db,'SELECT id,email,headers,status FROM '.$this->p.'mail_queue WHERE issuer_id=? ORDER BY id',[$i])->result_array()as$row){
                set_error_handler(static function(){throw new DomainException('native_mail_queue_invalid');});try{$headers=unserialize($row['headers'],['allowed_classes'=>false]);}finally{restore_error_handler();}
                if(!is_array($headers)||!is_string($headers['subject']??$headers['Subject']??null))throw new DomainException('native_mail_queue_invalid');
                $out[]=(object)['id'=>IssuerPolicy::id($row['id']),'email'=>(string)$row['email'],'status'=>(string)$row['status'],'subject'=>$headers['subject']??mb_decode_mimeheader($headers['Subject'])];
            }
            $this->auth($i,$a,'view');return$out;
        });
    }
    private function affected(int $expected):void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!==(string)$expected)throw new RuntimeException('issuer_native_mail_queue_write_unconfirmed');
    }
    public function remove(int $i,int $a,?int $id):void
    {
        $this->auth($i,$a,'edit');if($id!==null)IssuerPolicy::id($id);
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id){
            $this->auth($i,$a,'edit');$where='issuer_id=?'.($id===null?'':' AND id=?');$args=$id===null?[$i]:[$i,$id];
            $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'mail_queue WHERE '.$where.' FOR UPDATE',$args)->result_array();
            if($id!==null&&count($rows)!==1)throw new DomainException('native_mail_queue_denied');
            // Delete only queue projections, never delivery receipts or append-only audit/history.
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'mail_queue WHERE '.$where,$args);
            $this->affected(count($rows));
            $event='native_mail_queue_removed';$stamp=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$a,$event,$i,$id,$stamp]);$this->affected(1);$audit=(int)$this->db->insert_id();
            if($audit<1||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND created_at=?',[$audit,$a,$i,$event,$stamp])->row_array())throw new RuntimeException('issuer_native_mail_queue_write_unconfirmed');
            $this->auth($i,$a,'edit');
        });
    }
}
