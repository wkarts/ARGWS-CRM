<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyNativeContactMessage.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
/** Original confirmation action, explicit issuer ownership and one primary contact. */
final class NativeCompanyCustomerRegistration
{
    public function __construct(private$db,private string$p,private string$secret){}
    private function source(int$issuer,int$actor,int$client):array
    {
        if(!(new IssuerResourceAccess($this->db,$this->p))->administrative($issuer,$actor))throw new DomainException('company_client_access_denied');
        $row=IssuerStorage::query($this->db,'SELECT c.userid,c.active,c.registration_confirmed FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=?',[$client,$issuer])->row_array();
        if(!$row)throw new DomainException('company_client_access_denied');
        $contacts=IssuerStorage::query($this->db,'SELECT id,userid,active,email,password FROM '.$this->p.'contacts WHERE userid=? AND is_primary=1 ORDER BY id',[$client])->result_array();if(count($contacts)!==1)throw new DomainException('company_contact_invalid_primary');
        return['client'=>$row,'contact'=>$contacts[0]];
    }
    public function revision(int$issuer,int$actor,int$client):string{return hash('sha256',json_encode($this->source($issuer,$actor,$client),JSON_THROW_ON_ERROR));}
    public function confirm(int$issuer,int$actor,int$client,string$revision,string$base,callable$decrypt):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_client_stale');$mail=new CompanyNativeContactMessage($this->db,$this->p,$this->secret);
        IssuerTransaction::run($this->db,$this->p,$mail->tables(),function()use($issuer,$actor,$client,$revision,$base,$decrypt,$mail):void{
            $before=$this->source($issuer,$actor,$client);if(!hash_equals($this->revision($issuer,$actor,$client),$revision))throw new DomainException('company_client_stale');
            if((int)$before['client']['registration_confirmed']===1)throw new DomainException('company_client_stale');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'clients SET active=1,registration_confirmed=1 WHERE userid=?',[$client]);IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET active=1 WHERE id=? AND userid=?',[$before['contact']['id'],$client]);
            $after=$this->source($issuer,$actor,$client);if((int)$after['client']['active']!==1||(int)$after['client']['registration_confirmed']!==1||(int)$after['contact']['active']!==1||(int)$after['contact']['id']!==(int)$before['contact']['id'])throw new RuntimeException('issuer_client_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$client,'native_customer_registration_confirmed');
            $mail->queueInOwned($issuer,$actor,$client,(int)$before['contact']['id'],$base,$decrypt);$this->source($issuer,$actor,$client);
        });
    }
}
