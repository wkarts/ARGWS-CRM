<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerCertificateSchema.php';
require_once __DIR__.'/IssuerCertificateVault.php';
require_once __DIR__.'/IssuerResourceAccess.php';
require_once __DIR__.'/IssuerTransaction.php';

/** Company-local credential management only. No signing, transport or plaintext export. */
final class IssuerCertificateManager
{
    private IssuerCertificateVault $vault;
    private IssuerResourceAccess $access;
    public function __construct(private $db,private string $prefix,CI_Encryption $encryption)
    {
        IssuerCertificateSchema::definitions($prefix);
        $this->vault=new IssuerCertificateVault($encryption); $this->access=new IssuerResourceAccess($db,$prefix);
    }
    private function rows(string $sql,array $params=[]): array { return IssuerStorage::query($this->db,$sql,$params)->result_array(); }
    private function tables(): array { return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuer_certificate_credentials','issuer_certificate_audit']; }
    private function transaction(callable $operation)
    {
        IssuerCertificateSchema::assertValid($this->db,$this->prefix);
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),$operation);
    }
    /** settings/edit is the CRM's contextual company administrator grant; no global fallback. */
    public function authorize(int $issuer,int $actor): void
    {
        if (!$this->access->can($issuer,$actor,'settings','edit')) { throw new DomainException('issuer_certificate_denied'); }
    }
    private function lockedIdentity(int $issuer,int $actor): array
    {
        $this->access->lockFinancialIdentity($issuer,$actor);
        $this->rows('SELECT id FROM '.$this->prefix.'issuers_installation_identity WHERE id=1 FOR UPDATE');
        $this->authorize($issuer,$actor);
        $row=$this->rows('SELECT id,legal_name,tax_id,active,identity_status FROM '.$this->prefix.'issuers_registry WHERE id=? FOR UPDATE',[$issuer])[0]??null;
        if (!$row || (int)$row['active']!==1) { throw new DomainException('issuer_certificate_denied'); }
        return $row;
    }
    private function row(int $issuer): ?array
    {
        return $this->rows('SELECT issuer_id,revision,status,pfx_cipher,password_cipher,metadata_json,fingerprint_sha256,configured_by,created_at,updated_at FROM '.$this->prefix.'issuer_certificate_credentials WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;
    }
    private function metadata(array $row): array
    {
        try {
            $metadata=json_decode($row['metadata_json'],true,8,JSON_THROW_ON_ERROR);
            if (!is_array($metadata) || ($metadata['fingerprint_sha256']??null)!==$row['fingerprint_sha256']) { throw new RuntimeException(); }
            $this->vault->assertBound((int)$row['issuer_id'],(int)$row['revision'],$metadata,$row);
            return $metadata;
        } catch (Throwable $error) { throw new DomainException('issuer_certificate_envelope_invalid'); }
    }
    public function page(int $issuer,int $actor): array
    {
        $this->authorize($issuer,$actor);
        return $this->transaction(function()use($issuer,$actor){
            $identity=$this->lockedIdentity($issuer,$actor); $row=$this->row($issuer);
            $page=['issuer_id'=>$issuer,'company_name'=>$identity['legal_name'],'revision'=>(int)($row['revision']??0),'status'=>$row['status']??'unconfigured','metadata'=>null,'updated_at'=>$row['updated_at']??null];
            if ($row && $row['status']==='stored') {
                try { $metadata=$this->metadata($row); }
                catch (DomainException $error) { $page['status']='integrity_failed'; $this->authorize($issuer,$actor); return $page; }
                $page['metadata']=$metadata;
                if ($metadata['document']!==null && !hash_equals($metadata['document'],(string)$identity['tax_id'])) { $page['status']='document_changed'; }
                elseif (gmdate('Y-m-d H:i:s') >= $metadata['valid_to']) { $page['status']='expired'; }
                elseif (gmdate('Y-m-d H:i:s') < $metadata['valid_from']) { $page['status']='not_yet_valid'; }
            } elseif ($row && $row['status']!=='removed') { throw new DomainException('issuer_certificate_envelope_invalid'); }
            $this->authorize($issuer,$actor); return $page;
        });
    }
    private function revision(int $revision): void
    {
        if ($revision<0 || $revision>=2147483647) { throw new DomainException('issuer_certificate_stale'); }
    }
    public function replace(int $issuer,int $actor,int $revision,string $pfx,string $password): void
    {
        $this->authorize($issuer,$actor); $this->revision($revision);
        // Untrusted PKCS#12 KDF work must not hold the installation-wide authorization lock.
        $before=$this->rows('SELECT tax_id FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1',[$issuer])[0]??null;
        if ($before===null) { throw new DomainException('issuer_certificate_denied'); }
        $metadata=IssuerCertificateParser::inspect($pfx,$password,(string)$before['tax_id']);
        $this->transaction(function()use($issuer,$actor,$revision,$pfx,$password,$metadata,$before){
            $identity=$this->lockedIdentity($issuer,$actor); $old=$this->row($issuer);
            if ((int)($old['revision']??0)!==$revision || $identity['tax_id']!==$before['tax_id']) { throw new DomainException('issuer_certificate_stale'); }
            if ($metadata['document']!==null && !hash_equals($metadata['document'],(string)$identity['tax_id'])) { throw new DomainException('issuer_certificate_document_mismatch'); }
            if (gmdate('Y-m-d H:i:s') >= $metadata['valid_to']) { throw new DomainException('issuer_certificate_expired'); }
            if (gmdate('Y-m-d H:i:s') < $metadata['valid_from']) { throw new DomainException('issuer_certificate_not_yet_valid'); }
            $cipher=$this->vault->seal($issuer,$revision+1,$metadata,$pfx,$password); $stamp=gmdate('Y-m-d H:i:s');
            $fields=['revision'=>$revision+1,'status'=>'stored','pfx_cipher'=>$cipher['pfx_cipher'],'password_cipher'=>$cipher['password_cipher'],
                'metadata_json'=>json_encode($metadata,JSON_THROW_ON_ERROR),'fingerprint_sha256'=>$metadata['fingerprint_sha256'],'configured_by'=>$actor,'updated_at'=>$stamp];
            $this->write($issuer,$old,$fields+['created_at'=>$old['created_at']??$stamp]);
            $this->audit($issuer,$actor,$old&&$old['status']==='stored'?'replaced':'uploaded',$revision+1,$metadata['fingerprint_sha256']);
            $this->authorize($issuer,$actor);
            $current=$this->lockedIdentity($issuer,$actor);
            if ($current['tax_id']!==$identity['tax_id']) { throw new DomainException('issuer_certificate_document_mismatch'); }
        });
    }
    public function remove(int $issuer,int $actor,int $revision): void
    {
        $this->authorize($issuer,$actor); $this->revision($revision);
        $this->transaction(function()use($issuer,$actor,$revision){
            $this->lockedIdentity($issuer,$actor); $old=$this->row($issuer);
            if (!$old || (int)$old['revision']!==$revision || $old['status']!=='stored') { throw new DomainException('issuer_certificate_stale'); }
            $this->write($issuer,$old,['revision'=>$revision+1,'status'=>'removed','pfx_cipher'=>'','password_cipher'=>'','metadata_json'=>'{}','fingerprint_sha256'=>'','configured_by'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')]);
            $this->audit($issuer,$actor,'removed',$revision+1,$old['fingerprint_sha256']); $this->authorize($issuer,$actor);
        });
    }
    private function write(int $issuer,?array $old,array $fields): void
    {
        if ($old===null) {
            $fields=['issuer_id'=>$issuer]+$fields;
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuer_certificate_credentials (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
        } else {
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuer_certificate_credentials SET '.implode(',',array_map(static fn($key)=>'`'.$key.'`=?',array_keys($fields))).' WHERE issuer_id=? AND revision=?',array_merge(array_values($fields),[$issuer,(int)$old['revision']]));
        }
        $actual=$this->row($issuer);
        foreach ($fields as $key=>$value) { if ($actual===null || (string)$actual[$key] !== (string)$value) { throw new RuntimeException('issuer_certificate_write_unconfirmed'); } }
    }
    private function audit(int $issuer,int $actor,string $event,int $revision,string $fingerprint): void
    {
        $stamp=gmdate('Y-m-d H:i:s');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuer_certificate_audit (issuer_id,actor_id,event,revision,fingerprint_sha256,result,created_at) VALUES (?,?,?,?,?,?,?)',[$issuer,$actor,$event,$revision,$fingerprint,'success',$stamp]);
        $id=(int)$this->db->insert_id();
        $actual=$this->rows('SELECT id FROM '.$this->prefix.'issuer_certificate_audit WHERE id=? AND issuer_id=? AND actor_id=? AND event=? AND revision=? AND fingerprint_sha256=? AND result=?',[$id,$issuer,$actor,$event,$revision,$fingerprint,'success']);
        if (count($actual)!==1) { throw new RuntimeException('issuer_certificate_write_unconfirmed'); }
    }
}
