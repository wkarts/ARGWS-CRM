<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** The existing invoice settings counter for named issuers only. Principal keeps its original native writer. */
final class NativeCompanyInvoiceSequencePreferences
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_sequences','issuers_invoice_documents','issuers_audit'];}
    private function access(int $issuer,int $actor,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'settings',$cap))throw new DomainException('native_invoice_sequence_denied');}
    private static function number($value):string{if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,19}$/D',$value)||strlen($value)===20&&strcmp($value,'18446744073709551614')>0)throw new DomainException('native_invoice_sequence_invalid');return$value;}
    private static function compare(string $a,string $b):int{return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
    public function readInOwnedTransaction(int $issuer,int $actor):array
    {return$this->readFrame($issuer,$actor,fn()=>$this->access($issuer,$actor,'view'));}
    /** Invoice creation can inspect, never reserve or modify, its own next number without settings privileges. */
    public function readForInvoiceCreation(int $issuer,int $actor):array
    {
        $verify=function()use($issuer,$actor):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'invoices','create'))throw new DomainException('native_invoice_sequence_denied');};
        $verify();return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->readFrame($issuer,$actor,$verify));
    }
    /** Same create authority, inside the exact canonical issuer transaction; no nested transaction or reservation. */
    public function readForInvoiceCreationInOwned(int $issuer,int $actor):array
    {
        $verify=function()use($issuer,$actor):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'invoices','create'))throw new DomainException('native_invoice_sequence_denied');};
        $verify();return $this->readFrame($issuer,$actor,$verify);
    }
    /** A persisted open draft may be edited without granting permission to issue it. */
    public function readForDraftEditor(int $issuer,int $actor,$canonicalDraft):array
    {
        require_once __DIR__.'/CompanyInvoiceDrafts.php';require_once __DIR__.'/NativeCompanyResourceId.php';
        $ref=NativeCompanyResourceId::decode('invoice',$canonicalDraft);if($ref['source_kind']!=='company_invoice_draft')throw new DomainException('native_invoice_sequence_denied');
        $drafts=new CompanyInvoiceDrafts($this->db,$this->p);
        $verify=function()use($issuer,$actor,$ref,$drafts):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'invoices','edit'))throw new DomainException('native_invoice_sequence_denied');$draft=$drafts->documentInOwned($issuer,$actor,$ref['source_id']);if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');};
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$drafts->tables()))),fn()=>$this->readFrame($issuer,$actor,$verify));
    }
    private function readFrame(int $issuer,int $actor,callable $verify):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$verify();
        $rows=IssuerStorage::query($this->db,'SELECT next_number FROM '.$this->p.'issuers_sequences WHERE issuer_id=? AND series=? FOR UPDATE',[$issuer,'INV'])->result_array();
        if(count($rows)>1)throw new RuntimeException('native_invoice_sequence_ambiguous');
        $next=self::number($rows?(string)$rows[0]['next_number']:'1');
        $documents=IssuerStorage::query($this->db,'SELECT number FROM '.$this->p.'issuers_invoice_documents WHERE issuer_id=? AND series=?',[$issuer,'INV'])->result_array();
        foreach($documents as$row){$number=self::number((string)$row['number']);if(self::compare($number,$next)>=0)throw new RuntimeException('native_invoice_sequence_conflicts_with_issued_document');}
        $verify();return ['next_number'=>$next,'revision'=>hash('sha256',json_encode([$issuer,'INV',$rows?true:false,$next],JSON_THROW_ON_ERROR))];
    }
    public function read(int $issuer,int $actor):array{$this->access($issuer,$actor,'view');return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->readInOwnedTransaction($issuer,$actor));}
    public function saveInOwnedTransaction(int $issuer,int $actor,string $next,string $revision):array
    {
        $next=self::number($next);$this->access($issuer,$actor,'edit');$before=$this->readInOwnedTransaction($issuer,$actor);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($before['revision'],$revision))throw new DomainException('native_invoice_sequence_stale');
        if(self::compare($next,$before['next_number'])<0)throw new DomainException('native_invoice_sequence_regression');
        if($next===$before['next_number'])return$before;
        if(self::compare($next,'2147483647')>0)throw new DomainException('native_invoice_sequence_unemittable');
        $exists=IssuerStorage::query($this->db,'SELECT next_number FROM '.$this->p.'issuers_sequences WHERE issuer_id=? AND series=?',[$issuer,'INV'])->row_array();
        if($exists)IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_sequences SET next_number=? WHERE issuer_id=? AND series=? AND next_number=?',[$next,$issuer,'INV',$before['next_number']]);
        else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_sequences(issuer_id,series,next_number) VALUES(?,?,?)',[$issuer,'INV',$next]);
        $after=$this->readInOwnedTransaction($issuer,$actor);if($after['next_number']!==$next)throw new RuntimeException('native_invoice_sequence_write_unconfirmed');
        $metadata=json_encode(['series'=>'INV','before'=>$before['next_number'],'after'=>$next,'nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,event,metadata_json,created_at) VALUES(?,?,?,?,?)',[$issuer,$actor,'native_invoice_sequence_advanced',$metadata,gmdate('Y-m-d H:i:s')]);
        $receipt=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND event=? AND metadata_json=?',[$issuer,$actor,'native_invoice_sequence_advanced',$metadata])->result_array();
        if(count($receipt)!==1)throw new RuntimeException('native_invoice_sequence_audit_unconfirmed');$this->access($issuer,$actor,'edit');return$after;
    }
    public function save(int $issuer,int $actor,string $next,string $revision):array{$this->access($issuer,$actor,'edit');return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->saveInOwnedTransaction($issuer,$actor,$next,$revision));}
}
