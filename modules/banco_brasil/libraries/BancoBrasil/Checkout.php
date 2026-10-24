<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ApiService.php';
require_once __DIR__.'/CheckoutSchema.php';
require_once APPPATH.'services/CompanyPortal.php';

/** Explicit publication of an existing validated instrument; never creates a bank charge. */
final class Checkout
{
    private ApiService $api;
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $this->api=new ApiService($db,$p);
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function run(callable $work)
    {
        CheckoutSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge(ApiService::receiptTables(),['bb_checkout_publications','clients','contacts','contact_permissions','issuers_clients']))),$work);
    }
    public function publish($issuer,$actor,$origin,string $revision,bool $enabled):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new \DomainException('bb_checkout_revision_invalid');
        $this->run(function()use($issuer,$actor,$origin,$revision,$enabled){
            $acl=new \IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
            if(!$acl->can($issuer,$actor,'banco_brasil','manage'))throw new \DomainException('bb_checkout_access_denied');
            if(!$enabled){
                $owned=$this->rows('SELECT id FROM '.$this->p.'bb_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer]);
                if(!$owned)throw new \DomainException('bb_checkout_access_denied');
                $publication=$this->rows('SELECT enabled FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer]);
                if(!$publication||(int)$publication[0]['enabled']===0)return;
                \IssuerStorage::query($this->db,'UPDATE '.$this->p.'bb_checkout_publications SET enabled=0,updated_at=? WHERE origin_id=? AND issuer_id=?',[gmdate('Y-m-d H:i:s'),$origin,$issuer]);
                if($this->rows('SELECT origin_id FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? AND issuer_id=? AND enabled<>0',[$origin,$issuer]))throw new \RuntimeException('bb_checkout_write_unconfirmed');
                \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'bb_checkout_hidden',$issuer,$origin,gmdate('Y-m-d H:i:s')]);
                return;
            }
            $instrument=$this->api->checkoutInstrumentInOwnedTransaction($issuer,$actor,$origin);
            if(!hash_equals($instrument['revision'],$revision))throw new \DomainException('bb_checkout_stale');
            $old=$this->rows('SELECT * FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? FOR UPDATE',[$origin]);
            if($old&&(int)$old[0]['issuer_id']!==$issuer)throw new \DomainException('bb_checkout_access_denied');
            $now=gmdate('Y-m-d H:i:s');
            if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'bb_checkout_publications SET instrument_revision=?,published_by=?,enabled=?,updated_at=? WHERE origin_id=? AND issuer_id=?',[$revision,$actor,(int)$enabled,$now,$origin,$issuer]);
            elseif($enabled)\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'bb_checkout_publications(origin_id,issuer_id,instrument_revision,published_by,enabled,created_at,updated_at) VALUES(?,?,?,?,1,?,?)',[$origin,$issuer,$revision,$actor,$now,$now]);
            $saved=$this->rows('SELECT enabled,instrument_revision FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? AND issuer_id=?',[$origin,$issuer]);
            if(($enabled&&!$saved)||($saved&&((int)$saved[0]['enabled']!==(int)$enabled||$saved[0]['instrument_revision']!==$revision)))throw new \RuntimeException('bb_checkout_write_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$enabled?'bb_checkout_published':'bb_checkout_hidden',$issuer,$origin,$now]);
        });
    }
    public function staffInstrument($issuer,$actor,$origin):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        return $this->run(fn()=>$this->api->checkoutInstrumentInOwnedTransaction($issuer,$actor,$origin));
    }
    private function portalOwned(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
    {
        $portal->assertStorageBinding($this->db,$this->p);
        $who=$portal->invoiceIdentity($session,$issuer,true);
        if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1 FOR UPDATE',[$who['contact_id']]))!==1)throw new \DomainException('bb_checkout_access_denied');
        if(!$this->rows('SELECT id FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? AND clientid=? AND status IN (1,4) FOR UPDATE',[$invoice,$issuer,$who['client_id']]))throw new \DomainException('bb_checkout_access_denied');
        $rows=$this->rows('SELECT l.* FROM '.$this->p.'bb_checkout_publications l JOIN '.$this->p.'bb_origins o ON o.id=l.origin_id AND o.issuer_id=l.issuer_id WHERE l.issuer_id=? AND o.invoice_id=? AND l.enabled=1 FOR UPDATE',[$issuer,$invoice]);
        if(count($rows)!==1)throw new \DomainException('bb_checkout_unavailable');$publication=$rows[0];
        $instrument=$this->api->checkoutInstrumentInOwnedTransaction($issuer,(int)$publication['published_by'],(int)$publication['origin_id']);
        if($instrument['invoice_id']!==$invoice||$instrument['client_id']!==(int)$who['client_id']||!hash_equals($publication['instrument_revision'],$instrument['revision']))throw new \DomainException('bb_checkout_stale');
        unset($instrument['revision'],$instrument['profile_id']);return $instrument;
    }
    public function portalInstrument(\CompanyPortal $portal,array $session,$issuer,$invoice):array
    {
        $issuer=\IssuerPolicy::id($issuer);$invoice=\IssuerPolicy::id($invoice);
        return $this->run(fn()=>$this->portalOwned($portal,$session,$issuer,$invoice));
    }
    public function portalPage(\CompanyPortal $portal,array $session,$issuer,array $invoices):array
    {
        $issuer=\IssuerPolicy::id($issuer);if(count($invoices)>50)throw new \DomainException('bb_checkout_limit');
        return $this->run(function()use($portal,$session,$issuer,$invoices){$available=[];foreach($invoices as $invoice){$id=\IssuerPolicy::id($invoice);try{$this->portalOwned($portal,$session,$issuer,$id);$available[$id]=true;}catch(\DomainException $denied){}}return $available;});
    }
}
