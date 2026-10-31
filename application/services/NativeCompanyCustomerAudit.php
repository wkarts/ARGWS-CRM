<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Confirms this exact audit insert, not a coincidentally matching earlier lastInsertId. */
final class NativeCompanyCustomerAudit
{
    public static function record($db,string $prefix,int $issuer,int $actor,int $subject,string $event):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');
        $metadata=json_encode(['native_write_nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);$at=gmdate('Y-m-d H:i:s');
        IssuerStorage::query($db,'INSERT INTO '.$prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$subject,$metadata,$at]);
        $id=(int)$db->insert_id();$row=IssuerStorage::query($db,'SELECT actor_id,event,issuer_id,subject_id,metadata_json,created_at FROM '.$prefix.'issuers_audit WHERE id=?',[$id])->row_array();
        if(!$row||(int)$row['actor_id']!==$actor||$row['event']!==$event||(int)$row['issuer_id']!==$issuer||(int)$row['subject_id']!==$subject||$row['metadata_json']!==$metadata||$row['created_at']!==$at)throw new RuntimeException('issuer_client_write_unconfirmed');
    }
}
