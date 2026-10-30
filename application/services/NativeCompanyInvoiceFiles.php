<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAttachments.php';
require_once __DIR__.'/NativeCompanyInvoiceFileSchema.php';
require_once __DIR__.'/NativeCompanyFileRows.php';
require_once __DIR__.'/NativeCompanyUploadPreferences.php';
require_once __DIR__.'/NativeCompanyLegacyInvoiceContent.php';
/** Original files IDs and views, never filesystem paths as authority. */
final class NativeCompanyInvoiceFiles
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function tables():array{return array_merge(CompanyInvoiceAttachments::tableNames(),['files','company_invoice_file_projection']);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tx(callable $fn){NativeCompanyInvoiceFileSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,self::tables(),$fn);}
    private function audit(int $i,int $a,int $invoice,int $id,string $event):void
    {
        $meta=json_encode(['file_id'=>$id,'write_nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,$event,$i,$invoice,date('Y-m-d H:i:s'),$meta]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND event=? AND metadata_json=?',[$audit,$i,$event,$meta]))throw new RuntimeException('issuer_native_file_audit_unconfirmed');
    }
    private function source(int $i,int $id):array
    {
        $r=$this->rows('SELECT s.*,f.rel_id,f.rel_type,f.file_name,f.filetype,f.visible_to_customer,f.external,f.external_link,f.staffid,f.dateadded,f.attachment_key FROM '.$this->p.'company_invoice_file_projection s JOIN '.$this->p.'files f ON f.id=s.file_id WHERE s.file_id=? AND s.issuer_id=? FOR UPDATE',[$id,$i])[0]??null;
        if(!$r||$r['rel_type']!=='invoice'||(int)$r['rel_id']!==(int)$r['invoice_id']||!preg_match('/^[a-f0-9]{32}$/D',$r['attachment_key']))throw new DomainException('native_invoice_file_denied');return$r;
    }
    private function readOwned(int $i,int $a,int $id,?int $revision=null,bool $audit=true):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$r=$this->source($i,$id);
        if((int)$r['active']!==1||($revision!==null&&(int)$r['revision']!==$revision))throw new DomainException('native_invoice_file_conflict');
        if($r['source_kind']==='legacy_fs'){
            $identity=(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($i,$a,(int)$r['invoice_id']);if($identity['lineage']!=='native_legacy_null'||!hash_equals($r['request_hash'],NativeCompanyLegacyInvoiceContent::fingerprint($r)))throw new DomainException('native_invoice_file_denied');
            $data=$audit?NativeCompanyLegacyInvoiceContent::read((int)$r['invoice_id'],$r['file_name']):['mime'=>$r['filetype']];if($audit)$this->audit($i,$a,(int)$r['invoice_id'],$id,'native_invoice_legacy_file_read');
            return $data+['id'=>$id,'file_id'=>$id,'issuer_id'=>$i,'invoice_id'=>(int)$r['invoice_id'],'filename'=>$r['file_name'],'portal_visible'=>(int)$r['visible_to_customer'],'revision'=>(int)$r['revision'],'attachment_key'=>$r['attachment_key'],'staffid'=>(int)$r['staffid'],'dateadded'=>$r['dateadded'],'can_delete'=>$this->canDelete($i,$a,$r),'file_key'=>null,'source_kind'=>'legacy_fs'];
        }
        if($r['source_kind']==='external'&&$r['file_key']===null){
            $this->authorizeParent($i,$a,(int)$r['invoice_id'],'view');$url=NativeCompanyFileContent::external($r['external'],$r['external_link']);
            $hash=hash('sha256',json_encode([$i,(int)$r['staffid'],(int)$r['invoice_id'],$r['file_name'],$r['external'],$url],JSON_THROW_ON_ERROR));if(!hash_equals($r['request_hash'],$hash))throw new RuntimeException('issuer_invoice_external_unconfirmed');
            return ['id'=>$id,'file_id'=>$id,'issuer_id'=>$i,'invoice_id'=>(int)$r['invoice_id'],'filename'=>$r['file_name'],'mime'=>'application/octet-stream','portal_visible'=>(int)$r['visible_to_customer'],'revision'=>(int)$r['revision'],'attachment_key'=>$r['attachment_key'],'file_key'=>null,'staffid'=>(int)$r['staffid'],'dateadded'=>$r['dateadded'],'can_delete'=>$this->canDelete($i,$a,$r),'external'=>$r['external'],'external_link'=>$url];
        }
        if($r['source_kind']!=='blob'||$r['file_key']===null)throw new DomainException('native_invoice_file_denied');
        if(!empty($r['external'])||!empty($r['external_link']))throw new DomainException('native_invoice_file_denied');
        $backend=new CompanyInvoiceAttachments($this->db,$this->p);$blob=$audit?$backend->downloadInOwned($i,$a,(int)$r['invoice_id'],$r['file_key'],true):$backend->inspectInOwned($i,$a,(int)$r['invoice_id'],$r['file_key'],true);
        if($r['file_name']!==$blob['filename']||$r['filetype']!==$blob['mime']||(int)$r['visible_to_customer']!==(int)$blob['portal_visible'])throw new RuntimeException('native_invoice_file_projection_invalid');
        return $blob+['id'=>$id,'file_id'=>$id,'issuer_id'=>$i,'invoice_id'=>(int)$r['invoice_id'],'revision'=>(int)$r['revision'],'attachment_key'=>$r['attachment_key'],'file_key'=>$r['file_key'],'staffid'=>(int)$r['staffid'],'dateadded'=>$r['dateadded'],'can_delete'=>$this->canDelete($i,$a,$r)];
    }
    public function upload(int $i,int $a,int $invoice,string $key,string $name,string $bytes):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'invoices','edit'))throw new DomainException('native_invoice_file_denied');
        $hash=hash('sha256',json_encode([$i,$a,$invoice,$name,hash('sha256',$bytes)],JSON_THROW_ON_ERROR));
        return$this->tx(function()use($i,$a,$invoice,$key,$name,$bytes,$hash){
            $old=$this->rows('SELECT file_id,request_hash FROM '.$this->p.'company_invoice_file_projection WHERE request_key=? AND issuer_id=? FOR UPDATE',[$key,$i])[0]??null;
            if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException('native_invoice_file_conflict');return$this->readOwned($i,$a,(int)$old['file_id']);}
            $backend=new CompanyInvoiceAttachments($this->db,$this->p);$settings=NativeCompanyUploadPreferences::read($this->db,$this->p,$i,$a);$backend->uploadNativeInOwned($i,$a,$invoice,$key,$name,$bytes,$settings['allowed_files'],NativeCompanyFileContent::maximum());$blob=$backend->downloadInOwned($i,$a,$invoice,$key,true);
            $id=NativeCompanyFileRows::insertInOwned($this->db,$this->p,['rel_id'=>$invoice,'rel_type'=>'invoice','file_name'=>$blob['filename'],'filetype'=>$blob['mime'],'visible_to_customer'=>0,'external'=>null,'external_link'=>null,'thumbnail_link'=>null,'staffid'=>$a,'contact_id'=>0,'task_comment_id'=>0,'dateadded'=>date('Y-m-d H:i:s')]);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_file_projection(file_id,issuer_id,invoice_id,source_kind,file_key,request_key,request_hash,revision,active,write_nonce) VALUES(?,?,?,\'blob\',?,?,?,1,1,?)',[$id,$i,$invoice,$key,$key,$hash,$nonce]);
            $r=$this->source($i,$id);if(!hash_equals($nonce,$r['write_nonce']))throw new RuntimeException('native_invoice_file_unconfirmed');return$this->readOwned($i,$a,$id);
        });
    }
    private function authorizeParent(int $i,int $a,int $invoice,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'invoices','view')||!$acl->can($i,$a,'invoices',$cap))throw new DomainException('native_invoice_file_denied');
        require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($i,$a,$invoice,$cap);
    }
    public function addExternal(int $i,int $a,int $invoice,string $key,string $name,string $provider,string $url):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||$name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name))throw new DomainException('native_file_external_invalid');$url=NativeCompanyFileContent::external($provider,$url);
        return$this->tx(function()use($i,$a,$invoice,$key,$name,$provider,$url){
            $this->authorizeParent($i,$a,$invoice,'edit');$hash=hash('sha256',json_encode([$i,$a,$invoice,$name,$provider,$url],JSON_THROW_ON_ERROR));
            $old=$this->rows('SELECT file_id,request_hash FROM '.$this->p.'company_invoice_file_projection WHERE issuer_id=? AND request_key=? FOR UPDATE',[$i,$key])[0]??null;if($old){if(!hash_equals($old['request_hash'],$hash))throw new DomainException('native_invoice_file_conflict');return$this->readOwned($i,$a,(int)$old['file_id'],null,false);}
            $id=NativeCompanyFileRows::insertInOwned($this->db,$this->p,['rel_id'=>$invoice,'rel_type'=>'invoice','file_name'=>$name,'filetype'=>'application/octet-stream','visible_to_customer'=>0,'external'=>$provider,'external_link'=>$url,'thumbnail_link'=>null,'staffid'=>$a,'contact_id'=>0,'task_comment_id'=>0,'dateadded'=>date('Y-m-d H:i:s')]);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_file_projection(file_id,issuer_id,invoice_id,source_kind,file_key,request_key,request_hash,revision,active,write_nonce) VALUES(?,?,?,\'external\',NULL,?,?,1,1,?)',[$id,$i,$invoice,$key,$hash,$nonce]);$r=$this->source($i,$id);if(!hash_equals($nonce,$r['write_nonce']))throw new RuntimeException('issuer_invoice_external_unconfirmed');$this->audit($i,$a,$invoice,$id,'native_invoice_external_added');return$this->readOwned($i,$a,$id,null,false);
        });
    }
    public static function nativeMetadata(array $r):array
    {
        return ['id'=>$r['id'],'attachment_id'=>$r['id'],'rel_id'=>$r['invoice_id'],'rel_type'=>'invoice','file_name'=>$r['filename'],'filetype'=>$r['mime'],'visible_to_customer'=>(int)$r['portal_visible'],'attachment_key'=>$r['attachment_key'],'key'=>$r['attachment_key'],'external'=>$r['external']??null,'external_link'=>$r['external_link']??null,'thumbnail_link'=>null,'revision'=>$r['revision'],'native_file_source'=>'invoice','staffid'=>$r['staffid'],'dateadded'=>$r['dateadded'],'can_delete'=>$r['can_delete']??false];
    }
    public function page(int $i,int $a,int $invoice):array
    {
        return $this->tx(function()use($i,$a,$invoice){
            $this->authorizeParent($i,$a,$invoice,'view');
            $out=[];foreach($this->rows('SELECT file_id FROM '.$this->p.'company_invoice_file_projection WHERE issuer_id=? AND invoice_id=? AND active=1 ORDER BY file_id',[$i,$invoice])as$r){$file=$this->readOwned($i,$a,(int)$r['file_id'],null,false);$file['can_delete']=$this->canDelete($i,$a,$file);$out[]=self::nativeMetadata($file);}return$out;
        });
    }
    public function read(int $i,int $a,int $id,?int $revision=null):array{return$this->tx(fn()=>$this->readOwned($i,$a,$id,$revision));}
    public function readForDispatchInOwned(int $i,int $a,int $id,int $revision):array{$r=$this->readOwned($i,$a,$id,$revision);if($r['file_key']===null&&($r['source_kind']??'')!=='legacy_fs')throw new DomainException('native_file_external_no_content');$this->authorizeParent($i,$a,$r['invoice_id'],'edit');return$r;}
    public function readKey(int $i,int $a,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('native_invoice_file_denied');
        return$this->tx(function()use($i,$a,$key){$r=$this->rows('SELECT f.id FROM '.$this->p.'files f JOIN '.$this->p.'company_invoice_file_projection s ON s.file_id=f.id WHERE f.attachment_key=? AND s.issuer_id=?',[$key,$i])[0]??null;if(!$r)throw new DomainException('native_invoice_file_denied');$result=$this->readOwned($i,$a,(int)$r['id']);if($result['file_key']===null&&($result['source_kind']??'')!=='legacy_fs')throw new DomainException('native_file_external_no_content');return$result;});
    }
    private function canDelete(int $i,int $a,array $source):bool
    {
        if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'invoices','edit'))return false;
        $staff=$this->rows('SELECT staffid,active,admin FROM '.$this->p.'staff WHERE staffid=?',[$a])[0]??null;
        return $staff&&(int)$staff['active']===1&&((int)$source['staffid']===$a||(int)$staff['admin']===1);
    }
    public function remove(int $i,int $a,int $id,int $revision):void
    {
        $this->tx(function()use($i,$a,$id,$revision){$source=$this->source($i,$id);$this->authorizeParent($i,$a,(int)$source['invoice_id'],'edit');if(!$this->canDelete($i,$a,$source))throw new DomainException('native_invoice_file_delete_denied');if((int)$source['active']===0&&(int)$source['revision']===$revision+1)return;
            $r=$this->readOwned($i,$a,$id,$revision);if(($r['source_kind']??'')==='legacy_fs')IssuerStorage::query($this->db,'UPDATE '.$this->p.'files SET visible_to_customer=0 WHERE id=?',[$id]);if($r['file_key']!==null)(new CompanyInvoiceAttachments($this->db,$this->p))->hideInOwned($i,$a,$r['invoice_id'],$r['file_key'],$r['content_hash'],true);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_invoice_file_projection SET active=0,revision=revision+1,write_nonce=? WHERE file_id=? AND issuer_id=? AND revision=? AND active=1',[$nonce,$id,$i,$revision]);$after=$this->source($i,$id);if((int)$after['active']!==0||(int)$after['revision']!==$revision+1||!hash_equals($nonce,$after['write_nonce']))throw new RuntimeException('issuer_native_file_write_unconfirmed');$this->audit($i,$a,$r['invoice_id'],$id,'native_invoice_file_removed');
        });
    }
    public function visibility(int $i,int $a,int $id,int $revision,bool $visible):int
    {
        return$this->tx(function()use($i,$a,$id,$revision,$visible){$source=$this->source($i,$id);$this->authorizeParent($i,$a,(int)$source['invoice_id'],'edit');
            // Exact one-step replay: revalidate current private bytes and visibility,
            // then return the prior result without changing its revision or audit.
            if((int)$source['active']===1&&(int)$source['revision']===$revision+1&&(int)$source['visible_to_customer']===(int)$visible){$this->readOwned($i,$a,$id,$revision+1,false);return$revision+1;}
            $r=$this->readOwned($i,$a,$id,$revision);if($r['file_key']!==null)(new CompanyInvoiceAttachments($this->db,$this->p))->visibilityInOwned($i,$a,$r['invoice_id'],$r['file_key'],$r['content_hash'],$visible,true);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'files SET visible_to_customer=? WHERE id=?',[(int)$visible,$id]);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_invoice_file_projection SET revision=revision+1,write_nonce=? WHERE file_id=? AND issuer_id=? AND revision=?',[$nonce,$id,$i,$revision]);$s=$this->source($i,$id);if((int)$s['revision']!==$revision+1||!hash_equals($nonce,$s['write_nonce']))throw new RuntimeException('native_invoice_file_unconfirmed');$this->readOwned($i,$a,$id,$revision+1);$this->audit($i,$a,$r['invoice_id'],$id,'native_invoice_file_visibility');return$revision+1;
        });
    }
}
