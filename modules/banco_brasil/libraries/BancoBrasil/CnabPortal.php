<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Cnab400Preparation.php';
require_once __DIR__.'/CheckoutSchema.php';
require_once APPPATH.'services/CompanyPortal.php';

/** Explicit publication of a reviewed CNAB document, never a remittance or bank operation. */
final class CnabPortal
{
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function run(callable $work)
    {
        CheckoutSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge(
            (new Cnab400Preparation($this->db,$this->p))->issuanceTables(),
            ['bb_checkout_publications','clients','contacts','contact_permissions','issuers_clients']
        ))),$work);
    }
    private function instrument(int $issuer,int $actor,int $origin):array
    {
        $acl=new \IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'banco_brasil','manage'))throw new \DomainException('bb_checkout_access_denied');
        $o=$this->rows('SELECT * FROM '.$this->p.'bb_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
        if(!$o||$o['provider']!=='banco_brasil'||(int)$o['active_slot']!==1)throw new \DomainException('bb_checkout_unavailable');
        if($o['channel']==='cnab240'){
            $links=$this->rows('SELECT batch_id FROM '.$this->p.'bb_batch_origins WHERE origin_id=? FOR UPDATE',[$origin]);
            if(count($links)!==1)throw new \DomainException('bb_checkout_unavailable');
            $boleto=(new Origination($this->db,$this->p))->boletoInOwnedTransaction($issuer,$actor,(int)$links[0]['batch_id'],(int)$o['invoice_id']);
        }elseif($o['channel']==='cnab400'){
            $links=$this->rows('SELECT id FROM '.$this->p.'bb_cnab_preparations WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer]);
            if(count($links)!==1)throw new \DomainException('bb_checkout_unavailable');
            $boleto=(new Cnab400Preparation($this->db,$this->p))->boletoInOwnedTransaction($issuer,$actor,(int)$links[0]['id']);
        }else throw new \DomainException('bb_checkout_unavailable');
        $invoice=$this->rows('SELECT clientid FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$o['invoice_id'],$issuer])[0]??null;
        if(!$invoice)throw new \DomainException('bb_checkout_unavailable');
        return ['invoice_id'=>(int)$o['invoice_id'],'origin_id'=>$origin,'client_id'=>(int)$invoice['clientid'],
            'revision'=>hash('sha256',json_encode(['cnab-portal-143',$origin,$o['payload_hash'],$boleto],JSON_THROW_ON_ERROR)),
            'line'=>$boleto['line'],'amount'=>$boleto['snapshot']['title']['amount'],'due_date'=>$boleto['snapshot']['title']['due_date'],
            'environment'=>$boleto['environment'],'bank_state'=>$boleto['bank_state'],'cnab_boleto'=>$boleto];
    }
    public function staffInstrument(int $issuer,int $actor,int $origin):array
    {
        return $this->run(fn()=>$this->instrument($issuer,$actor,$origin));
    }
    public function publish(int $issuer,int $actor,int $origin,string $revision):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new \DomainException('bb_checkout_revision_invalid');
        $this->run(function()use($issuer,$actor,$origin,$revision){
            $instrument=$this->instrument($issuer,$actor,$origin);
            if(!hash_equals($instrument['revision'],$revision))throw new \DomainException('bb_checkout_stale');
            $old=$this->rows('SELECT issuer_id FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? FOR UPDATE',[$origin]);
            if($old&&(int)$old[0]['issuer_id']!==$issuer)throw new \DomainException('bb_checkout_access_denied');
            $now=gmdate('Y-m-d H:i:s');
            if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'bb_checkout_publications SET instrument_revision=?,published_by=?,enabled=1,updated_at=? WHERE origin_id=? AND issuer_id=?',[$revision,$actor,$now,$origin,$issuer]);
            else \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'bb_checkout_publications(origin_id,issuer_id,instrument_revision,published_by,enabled,created_at,updated_at) VALUES(?,?,?,?,1,?,?)',[$origin,$issuer,$revision,$actor,$now,$now]);
            $saved=$this->rows('SELECT * FROM '.$this->p.'bb_checkout_publications WHERE origin_id=? AND issuer_id=?',[$origin,$issuer])[0]??[];
            if(($saved['instrument_revision']??null)!==$revision||(int)($saved['enabled']??0)!==1||(int)($saved['published_by']??0)!==$actor)throw new \RuntimeException('bb_checkout_write_unconfirmed');
            $metadata=json_encode(['invoice_id'=>$instrument['invoice_id'],'instrument_revision'=>$revision,'bank_state'=>$instrument['bank_state']],JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'bb_cnab_checkout_published',$issuer,$origin,$now,$metadata]);
            $audit=(int)$this->db->insert_id();
            if(count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$actor,'bb_cnab_checkout_published',$issuer,$origin,$metadata]))!==1)throw new \RuntimeException('bb_checkout_write_unconfirmed');
        });
    }
    public function portalInstrument(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
    {
        return $this->run(fn()=>$this->portalOwned($portal,$session,$issuer,$invoice));
    }
    public function portalPage(\CompanyPortal $portal,array $session,int $issuer,array $invoices):array
    {
        if(count($invoices)>50)throw new \DomainException('bb_checkout_limit');
        return $this->run(function()use($portal,$session,$issuer,$invoices){$available=[];
            foreach($invoices as $invoice){$id=\IssuerPolicy::id($invoice);try{$this->portalOwned($portal,$session,$issuer,$id);$available[$id]=true;}catch(\DomainException $denied){}}
            return $available;
        });
    }
    private function portalOwned(\CompanyPortal $portal,array $session,int $issuer,int $invoice):array
    {
        $portal->assertStorageBinding($this->db,$this->p);
            $who=$portal->invoiceIdentity($session,$issuer,true);
            if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1 FOR UPDATE',[$who['contact_id']]))!==1
                ||!$this->rows('SELECT id FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? AND clientid=? AND status IN (1,4) FOR UPDATE',[$invoice,$issuer,$who['client_id']]))throw new \DomainException('bb_checkout_access_denied');
            $rows=$this->rows('SELECT l.* FROM '.$this->p.'bb_checkout_publications l JOIN '.$this->p.'bb_origins o ON o.id=l.origin_id AND o.issuer_id=l.issuer_id WHERE l.issuer_id=? AND o.invoice_id=? AND l.enabled=1 FOR UPDATE',[$issuer,$invoice]);
            if(count($rows)!==1)throw new \DomainException('bb_checkout_unavailable');$publication=$rows[0];
            // The contact never obtains staff capabilities. The explicit publisher must still be authorized.
            $instrument=$this->instrument($issuer,(int)$publication['published_by'],(int)$publication['origin_id']);
            if($instrument['invoice_id']!==$invoice||$instrument['client_id']!==(int)$who['client_id']||!hash_equals($publication['instrument_revision'],$instrument['revision']))throw new \DomainException('bb_checkout_stale');
            if($portal->invoiceIdentity($session,$issuer,true)!==$who)throw new \DomainException('bb_checkout_access_denied');
            unset($instrument['revision']);return $instrument;
    }
}
