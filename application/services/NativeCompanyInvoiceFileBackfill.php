<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceFiles.php';
/** Migration-only projection of proven private attachment lineage, not adoption of NULL invoices. */
final class NativeCompanyInvoiceFileBackfill
{
    public static function run($db,string $p):void
    {
        NativeCompanyInvoiceFileSchema::assertValid($db,$p);CompanyInvoiceAttachmentSchema::assertValid($db,$p);
        IssuerTransaction::run($db,$p,NativeCompanyInvoiceFiles::tables(),function()use($db,$p){
            foreach(IssuerStorage::query($db,'SELECT file_key FROM '.$p.'company_invoice_attachments ORDER BY file_key')->result_array()as$key){
                $r=IssuerStorage::query($db,'SELECT a.*,i.issuer_id AS parent_issuer,d.issuer_id AS document_issuer FROM '.$p.'company_invoice_attachments a LEFT JOIN '.$p.'invoices i ON i.id=a.invoice_id LEFT JOIN '.$p.'issuers_invoice_documents d ON d.invoice_id=i.id WHERE a.file_key=? FOR UPDATE',[$key['file_key']])->result_array();
                if(count($r)!==1)throw new RuntimeException('issuer_native_invoice_file_backfill_invalid');$r=$r[0];
                                $nativeNull=$r['parent_issuer']===null&&(new IssuerResourceAccess($db,$p))->primaryId()===(int)$r['issuer_id']&&count(IssuerStorage::query($db,'SELECT file_id FROM '.$p.'company_invoice_file_projection WHERE file_key=? AND issuer_id=? AND invoice_id=? AND source_kind=?',[$r['file_key'],$r['issuer_id'],$r['invoice_id'],'blob'])->result_array())===1;
                if((int)$r['issuer_id']<1||(!$nativeNull&&((int)$r['parent_issuer']!==(int)$r['issuer_id']||(int)$r['document_issuer']!==(int)$r['issuer_id']))||(int)$r['actor_id']<1||!preg_match('/^[a-f0-9]{64}$/D',$r['file_key'])||!in_array($r['mime'],['application/pdf','image/png','text/plain','application/octet-stream'],true)||strlen($r['content'])!==(int)$r['byte_size']||(int)$r['byte_size']<1||(int)$r['byte_size']>20971520||!hash_equals($r['content_hash'],hash('sha256',$r['content']))||!in_array((int)$r['active'],[0,1],true)||!in_array((int)$r['portal_visible'],[0,1],true))throw new RuntimeException('issuer_native_invoice_file_backfill_invalid');
                $old=IssuerStorage::query($db,'SELECT s.*,f.rel_id,f.rel_type,f.file_name,f.filetype,f.visible_to_customer FROM '.$p.'company_invoice_file_projection s JOIN '.$p.'files f ON f.id=s.file_id WHERE s.file_key=? FOR UPDATE',[$r['file_key']])->result_array();
                if($old){$v=$old[0];if(count($old)!==1||(int)$v['issuer_id']!==(int)$r['issuer_id']||(int)$v['invoice_id']!==(int)$r['invoice_id']||(int)$v['rel_id']!==(int)$r['invoice_id']||$v['rel_type']!=='invoice'||$v['file_name']!==$r['filename']||$v['filetype']!==$r['mime']||(int)$v['visible_to_customer']!==(int)$r['portal_visible']||(int)$v['active']!==(int)$r['active'])throw new RuntimeException('issuer_native_invoice_file_backfill_conflict');continue;}
                $id=NativeCompanyFileRows::insertInOwned($db,$p,['rel_id'=>(int)$r['invoice_id'],'rel_type'=>'invoice','file_name'=>$r['filename'],'filetype'=>$r['mime'],'visible_to_customer'=>(int)$r['portal_visible'],'external'=>null,'external_link'=>null,'thumbnail_link'=>null,'staffid'=>(int)$r['actor_id'],'contact_id'=>0,'task_comment_id'=>0,'dateadded'=>$r['created_at']]);
                $nonce=bin2hex(random_bytes(32));$hash=hash('sha256','migration474:'.$r['file_key'].':'.$r['content_hash']);
                IssuerStorage::query($db,'INSERT INTO '.$p.'company_invoice_file_projection(file_id,issuer_id,invoice_id,source_kind,file_key,request_key,request_hash,revision,active,write_nonce) VALUES(?,?,?,\'blob\',?,?,?,1,?,?)',[$id,$r['issuer_id'],$r['invoice_id'],$r['file_key'],$r['file_key'],$hash,$r['active'],$nonce]);
                $confirm=IssuerStorage::query($db,'SELECT write_nonce FROM '.$p.'company_invoice_file_projection WHERE file_id=? AND issuer_id=? AND file_key=?',[$id,$r['issuer_id'],$r['file_key']])->result_array();if(count($confirm)!==1||!hash_equals($nonce,$confirm[0]['write_nonce']))throw new RuntimeException('issuer_native_invoice_file_backfill_unconfirmed');
            }
            $primary=(new IssuerResourceAccess($db,$p))->primaryId();
            if($primary!==null){
                $legacy=IssuerStorage::query($db,'SELECT f.* FROM '.$p.'files f JOIN '.$p.'invoices i ON i.id=f.rel_id LEFT JOIN '.$p.'company_invoice_file_projection s ON s.file_id=f.id WHERE f.rel_type=? AND i.issuer_id IS NULL AND s.file_id IS NULL ORDER BY f.id',['invoice'])->result_array();
                foreach($legacy as$f){
                    if(!preg_match('/^[a-f0-9]{32}$/D',(string)$f['attachment_key'])||$f['file_name']===''||basename($f['file_name'])!==$f['file_name']||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$f['file_name']))throw new RuntimeException('issuer_native_invoice_file_backfill_invalid');
                    if(!empty($f['external'])){NativeCompanyFileContent::external($f['external'],$f['external_link']);$kind='external';$hash=hash('sha256',json_encode([$primary,(int)$f['staffid'],(int)$f['rel_id'],$f['file_name'],$f['external'],$f['external_link']],JSON_THROW_ON_ERROR));}
                    else{$kind='legacy_fs';$hash=NativeCompanyLegacyInvoiceContent::fingerprint($f);}
                    $nonce=bin2hex(random_bytes(32));$key=hash('sha256','migration474:legacy-native-file:'.$f['id']);IssuerStorage::query($db,'INSERT INTO '.$p.'company_invoice_file_projection(file_id,issuer_id,invoice_id,source_kind,file_key,request_key,request_hash,revision,active,write_nonce) VALUES(?,?,?,?,NULL,?,?,1,1,?)',[$f['id'],$primary,$f['rel_id'],$kind,$key,$hash,$nonce]);
                    $check=IssuerStorage::query($db,'SELECT write_nonce FROM '.$p.'company_invoice_file_projection WHERE file_id=? AND issuer_id=?',[$f['id'],$primary])->row_array();if(!hash_equals($nonce,$check['write_nonce']??''))throw new RuntimeException('issuer_native_invoice_file_backfill_unconfirmed');
                }
            }
        });
    }
}
