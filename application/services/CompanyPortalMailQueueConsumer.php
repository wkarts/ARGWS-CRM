<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalRecoveryMail.php';

/** Revalidates the precise pending reset before SMTP, including token consumption and password changes. */
final class CompanyPortalMailQueueConsumer
{
    public function __construct(private$db,private string$p,private string$secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new RuntimeException('company_portal_configuration_required');}
    public static function assertQueueRouting($db,string$p,object$email,array$headers):void
    {
        if(!CompanyPortalMailSchema::present($db,$p)){if(array_key_exists(CompanyPortalRecoveryMail::HEADER,$headers))throw new DomainException('company_portal_mail_denied');return;}
        $row=IssuerStorage::query($db,'SELECT issuer_id,delivery_key FROM '.$p.'company_portal_mail_deliveries WHERE queue_id=?',[$email->id])->result_array()[0]??null;$key=$headers[CompanyPortalRecoveryMail::HEADER]??null;
        if(!$row){if($key!==null)throw new DomainException('company_portal_mail_denied');return;}
        if((int)($email->issuer_id??0)!==(int)$row['issuer_id']||!is_string($key)||!hash_equals($row['delivery_key'],$key)||array_key_exists('companyInvoiceDelivery',$headers)||array_key_exists('companySalesDelivery',$headers))throw new DomainException('company_portal_mail_denied');
    }
    public function state(object$email,string$key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_portal_mail_denied');CompanyPortalMailSchema::assertValid($this->db,$this->p);$emitter=new CompanyPortalRecoveryMail($this->db,$this->p,$this->secret);
        return IssuerTransaction::run($this->db,$this->p,$emitter->tables(),function()use($email,$key){$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_portal_mail_deliveries WHERE delivery_key=? AND queue_id=? FOR UPDATE',[$key,$email->id])->result_array()[0]??null;if(!$row||(int)($email->issuer_id??0)!==(int)$row['issuer_id']||!hash_equals($row['payload_hash'],CompanyPortalMailIdentity::payload($email))||$row['expires_at']<=date('Y-m-d H:i:s')||$email->cc!==''||$email->bcc!==''||$email->attachments!=='')throw new DomainException('company_portal_mail_denied');
            $issuer=(int)$row['issuer_id'];$contact=CompanyPortalMailIdentity::contact($this->db,$this->p,$issuer,null,(int)$row['contact_id']);$token=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_portal_tokens WHERE token_hash=? AND issuer_id=? AND contact_id=? FOR UPDATE',[$row['token_hash'],$issuer,$row['contact_id']])->result_array()[0]??null;
            if(!$contact||!$token||(int)$contact['userid']!==(int)$row['client_id']||$contact['email']!==$email->email||!hash_equals($row['recipient_hash'],CompanyPortalMailIdentity::recipient($contact))||!hash_equals($row['identity_hash'],CompanyPortalMailIdentity::stamp($contact,$this->secret))||!hash_equals($row['identity_hash'],$token['identity_hash'])||(int)$token['client_id']!==(int)$row['client_id']||$token['expires_at']!==$row['expires_at'])throw new DomainException('company_portal_mail_denied');
            $state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($issuer);if($row['transport_kind']!==$state['kind']||(int)$row['transport_epoch']!==$state['epoch']||!hash_equals($row['configuration_hash'],$state['configuration_hash']))throw new DomainException('company_portal_mail_stale');return ['issuer_id'=>$issuer,'kind'=>$state['kind'],'epoch'=>$state['epoch']];});
    }
    public function assertDispatch(object$email,string$key):void{$this->state($email,$key);}
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('company_portal_mail_runtime_only');}
}
