<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';

/** Single-use SMTP dispatch permit. Source authorization belongs to the same owned claim transaction. */
final class NativeCompanyMailDispatchFence
{
    public static function tables():array{return['issuers_lock','issuers_registry','mail_queue','company_native_mail_dispatch_fences'];}
    /** Fresh metadata avoids a stale framework cache downgrading an owned envelope. */
    public static function find($db,string $p,$queue):?array
    {
        self::prefix($p);$queue=IssuerPolicy::id($queue);$pdo=$db->pdo??$db->conn_id??null;
        $sql=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? "SELECT name FROM sqlite_master WHERE type='table' AND name=?"
            : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?';
        if(!IssuerStorage::query($db,$sql,[$p.'company_native_mail_dispatch_fences'])->result_array())return null;
        return IssuerStorage::query($db,'SELECT * FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$queue])->row_array()?:null;
    }
    public static function definitions(string $p):array
    {
        self::prefix($p);
        return ['company_native_mail_dispatch_fences'=>"CREATE TABLE IF NOT EXISTS `{$p}company_native_mail_dispatch_fences` (
            queue_id INT NOT NULL, issuer_id INT UNSIGNED NOT NULL, kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            binding_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            attempt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, created_at DATETIME NOT NULL,
            dispatched_at DATETIME NULL, completed_at DATETIME NULL,
            PRIMARY KEY(queue_id), UNIQUE KEY queue_owner(issuer_id,queue_id),
            FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id)
        ) ENGINE=InnoDB"];
    }
    public static function ensure($db,string $p):void{foreach(self::definitions($p)as$sql)IssuerStorage::query($db,$sql);self::assertValid($db,$p);}
    public static function assertValid($db,string $p):void
    {
        self::prefix($p);$pdo=$db->pdo??$db->conn_id??null;if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        $expected=['queue_id'=>['int','NO',null],'issuer_id'=>['int unsigned','NO',null],'kind'=>['varchar(32)','NO','ascii_bin'],'binding_hash'=>['char(64)','NO','ascii_bin'],'state'=>['varchar(16)','NO','ascii_bin'],'attempt_key'=>['char(64)','YES','ascii_bin'],'created_at'=>['datetime','NO',null],'dispatched_at'=>['datetime','YES',null],'completed_at'=>['datetime','YES',null]];
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$p.'company_native_mail_dispatch_fences'])->result_array();
        if(array_column($rows,'COLUMN_NAME')!==array_keys($expected))throw new RuntimeException('issuer_mail_fence_schema_invalid');
        foreach($rows as$row){$type=preg_replace('/\bint\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']));if([$type,$row['IS_NULLABLE'],$row['COLLATION_NAME']]!==$expected[$row['COLUMN_NAME']]||!IssuerStorage::sqlNullDefault($row)||!empty($row['EXTRA']))throw new RuntimeException('issuer_mail_fence_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'company_native_mail_dispatch_fences'])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_mail_fence_schema_invalid');
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$p.'company_native_mail_dispatch_fences')->result_array()as$row){if($row['Sub_part']!==null||(int)$row['Non_unique']!==0)throw new RuntimeException('issuer_mail_fence_schema_invalid');$indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];}
        foreach(['PRIMARY'=>['queue_id'],'queue_owner'=>['issuer_id','queue_id']]as$key=>$columns){$actual=$indexes[$key]??[];ksort($actual);if(array_values($actual)!==$columns)throw new RuntimeException('issuer_mail_fence_schema_invalid');}
    }
    private static function prefix(string $p):void{if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private static function validate($issuer,$queue,string $kind,string $hash):void
    {
        IssuerPolicy::id($issuer);IssuerPolicy::id($queue);
        if(!in_array($kind,['reminder','contact','file','verification','ticket','smtp_test','event','staff_invite','privacy','customer_upload','contract','financial_document'],true)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_mail_fence_invalid');
    }
    public static function prepareInOwnedTransaction($db,string $p,int $issuer,int $queue,string $kind,string $hash):void
    {
        self::prefix($p);self::validate($issuer,$queue,$kind,$hash);IssuerTransaction::assertOwned($db,$p,self::tables());
        $mail=IssuerStorage::query($db,'SELECT id FROM '.$p.'mail_queue WHERE id=? AND issuer_id=? AND status=?',[$queue,$issuer,'pending'])->row_array();if(!$mail)throw new DomainException('company_mail_fence_invalid');
        $existing=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$queue])->row_array();
        if($existing){if((int)$existing['issuer_id']!==$issuer||$existing['kind']!==$kind||!hash_equals($existing['binding_hash'],$hash))throw new DomainException('company_mail_fence_conflict');return;}
        IssuerStorage::query($db,'INSERT INTO '.$p.'company_native_mail_dispatch_fences(queue_id,issuer_id,kind,binding_hash,state,attempt_key,created_at,dispatched_at,completed_at) VALUES(?,?,?,?,?,NULL,?,NULL,NULL)',[$queue,$issuer,$kind,$hash,'prepared',gmdate('Y-m-d H:i:s')]);
        $row=IssuerStorage::query($db,'SELECT state,binding_hash FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=? AND issuer_id=?',[$queue,$issuer])->row_array();if(($row['state']??null)!=='prepared'||($row['binding_hash']??null)!==$hash)throw new RuntimeException('issuer_mail_fence_write_unconfirmed');
    }
    /** Caller must commit independently and confirm that commit BEFORE any socket/SMTP invocation. */
    public static function claimInOwnedTransaction($db,string $p,int $issuer,int $queue,string $kind,string $hash):string
    {
        self::prefix($p);self::validate($issuer,$queue,$kind,$hash);IssuerTransaction::assertOwned($db,$p,self::tables());
        $row=IssuerStorage::query($db,'SELECT f.* FROM '.$p.'company_native_mail_dispatch_fences f JOIN '.$p.'mail_queue q ON q.id=f.queue_id AND q.issuer_id=f.issuer_id WHERE f.queue_id=? AND f.issuer_id=? AND q.status=? FOR UPDATE',[$queue,$issuer,'sending'])->row_array();
        if(!$row||$row['kind']!==$kind||!hash_equals($row['binding_hash'],$hash)||$row['state']!=='prepared'||$row['attempt_key']!==null)throw new DomainException('company_mail_dispatch_review_required');
        $key=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');IssuerStorage::query($db,'UPDATE '.$p.'company_native_mail_dispatch_fences SET state=?,attempt_key=?,dispatched_at=? WHERE queue_id=? AND issuer_id=? AND state=?', ['dispatched',$key,$now,$queue,$issuer,'prepared']);
        $saved=IssuerStorage::query($db,'SELECT state,attempt_key FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=? AND issuer_id=?',[$queue,$issuer])->row_array();if(($saved['state']??null)!=='dispatched'||($saved['attempt_key']??null)!==$key)throw new RuntimeException('issuer_mail_fence_write_unconfirmed');return$key;
    }
    /** Technical receipt only: never re-authorizes a revoked source or sends another message. */
    public static function complete($db,string $p,int $issuer,int $queue,string $kind,string $hash,string $attempt,bool $sent):void
    {
        self::prefix($p);self::validate($issuer,$queue,$kind,$hash);if(!preg_match('/^[a-f0-9]{64}$/D',$attempt))throw new DomainException('company_mail_fence_invalid');
        IssuerTransaction::run($db,$p,self::tables(),static function()use($db,$p,$issuer,$queue,$kind,$hash,$attempt,$sent):void{
            $row=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=? AND issuer_id=? FOR UPDATE',[$queue,$issuer])->row_array();$state=$sent?'sent':'unknown';
            if(!$row||$row['kind']!==$kind||!hash_equals($row['binding_hash'],$hash)||!is_string($row['attempt_key'])||!hash_equals($row['attempt_key'],$attempt))throw new DomainException('company_mail_fence_invalid');
            if($row['state']===$state)return;if($row['state']!=='dispatched')throw new DomainException('company_mail_fence_conflict');
            IssuerStorage::query($db,'UPDATE '.$p.'company_native_mail_dispatch_fences SET state=?,completed_at=? WHERE queue_id=? AND issuer_id=? AND state=?',[$state,gmdate('Y-m-d H:i:s'),$queue,$issuer,'dispatched']);
            if((IssuerStorage::query($db,'SELECT state FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=? AND issuer_id=?',[$queue,$issuer])->row_array()['state']??null)!==$state)throw new RuntimeException('issuer_mail_fence_write_unconfirmed');
        });
    }
}
