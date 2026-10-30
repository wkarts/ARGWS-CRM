<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
/** Receipt of an explicit creation-time metadata transfer; never rewrites historical sources on replay. */
final class NativeCompanyInvoiceDraftMetadata
{
    public function __construct(private $db,private string $p){}
    public static function tables($db,string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $c=$db->pdo??$db->conn_id??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$out=[];
        foreach([['company_custom_fields','company_custom_field_values'],['company_work_tags','company_work_tag_links','tags','taggables']]as$group){$names=array_map(static fn($name)=>$p.$name,$group);$marks=implode(',',array_fill(0,count($names),'?'));
            $rows=IssuerStorage::query($db,$sqlite?"SELECT name FROM sqlite_master WHERE type='table' AND name IN ($marks)":"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)",$names)->result_array();
            $present=array_map(static fn($row)=>(string)array_values($row)[0],$rows);
            // Pre-474 installations already have legacy tags; those alone are not an owned metadata installation.
            if($group[0]==='company_work_tags'&&!in_array($p.'company_work_tags',$present,true)&&!in_array($p.'company_work_tag_links',$present,true))continue;
            if($rows&&count($rows)!==count($names))throw new RuntimeException('native_invoice_metadata_schema_incomplete');if($rows)$out=array_merge($out,$group);
        }
        return$out;
    }
    public function transfer(int $issuer,int $actor,int $draft,int $revision,int $invoice,CompanyInvoices $writer):array
    {
        $tables=self::tables($this->db,$this->p);IssuerTransaction::assertOwned($this->db,$this->p,array_merge($tables,['issuers_audit']));
        $writer->createdLineIdsInOwned($issuer,$actor,$invoice,CompanyInvoiceDrafts::canonicalKey($issuer,$draft));
        $data=['issuer_id'=>$issuer,'actor_id'=>$actor,'draft_id'=>$draft,'draft_revision'=>$revision,'invoice_id'=>$invoice,'fields_hash'=>null,'tags_hash'=>null];
        if(in_array('company_custom_fields',$tables,true))$data['fields_hash']=(new NativeCompanyCustomFields($this->db,$this->p))->transferCreatedDraftInvoiceFieldsInOwned($issuer,$actor,$draft,$invoice,$writer);
        if(in_array('company_work_tags',$tables,true)){
            $tags=new NativeCompanyResourceTags($this->db,$this->p);$canonical=NativeCompanyResourceId::encode('company_invoice_draft',$draft);$source=$tags->read($issuer,$actor,'invoice',$canonical);$target=$tags->createInvoiceTagsInOwnedTransaction($issuer,$actor,$invoice,array_column($source['tags'],'name'),$writer);
            if($tags->read($issuer,$actor,'invoice',$canonical)!==$source||array_column($target['tags'],'name')!==array_column($source['tags'],'name'))throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');
            $data['tags_hash']=hash('sha256',json_encode(['source'=>$source,'target'=>$target],JSON_THROW_ON_ERROR));
        }
        $json=CompanyInvoiceDraftsInput::json($data);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$draft,'native_invoice_draft_metadata_transferred',$json,gmdate('Y-m-d H:i:s')]);
        $receipt=['audit_id'=>(int)$this->db->insert_id(),'hash'=>hash('sha256',$json)];$this->verify($issuer,$actor,$draft,$revision,$invoice,$receipt);return$receipt;
    }
    public function verify(int $issuer,int $actor,int $draft,int $revision,int $invoice,array $receipt):void
    {
        if(array_keys($receipt)!==['audit_id','hash']||!is_int($receipt['audit_id'])||$receipt['audit_id']<1||!is_string($receipt['hash'])||!preg_match('/^[a-f0-9]{64}$/D',$receipt['hash']))throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');
        $row=IssuerStorage::query($this->db,'SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$receipt['audit_id']])->row_array();
        if(!$row||(int)$row['issuer_id']!==$issuer||(int)$row['actor_id']!==$actor||(int)$row['subject_id']!==$draft||$row['event']!=='native_invoice_draft_metadata_transferred'||!hash_equals($receipt['hash'],hash('sha256',$row['metadata_json'])))throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');
        $data=CompanyInvoiceDraftsInput::decode($row['metadata_json']);if(array_keys($data)!==['issuer_id','actor_id','draft_id','draft_revision','invoice_id','fields_hash','tags_hash'])throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');if(array_intersect_key($data,array_flip(['issuer_id','actor_id','draft_id','draft_revision','invoice_id']))!==['issuer_id'=>$issuer,'actor_id'=>$actor,'draft_id'=>$draft,'draft_revision'=>$revision,'invoice_id'=>$invoice])throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');
        foreach(['fields_hash','tags_hash']as$key)if(!array_key_exists($key,$data)||($data[$key]!==null&&(!is_string($data[$key])||!preg_match('/^[a-f0-9]{64}$/D',$data[$key]))))throw new RuntimeException('issuer_native_invoice_metadata_transfer_unconfirmed');
    }
}
