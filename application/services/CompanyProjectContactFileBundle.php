<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectContactFilesStorage.php';require_once __DIR__.'/CompanyProjectFilesZip.php';
/** Native download-all from exactly the contact's own-private + staff-published file audiences. */
final class CompanyProjectContactFileBundle extends CompanyProjectContactFilesStorage
{
    private function eligible(int $issuer,int $project,array $who,bool $allowOwn):array
    {
        $rows=$this->rows('SELECT f.file_key,f.filename,f.mime,f.byte_size,f.content_hash,f.active,v.file_key AS published_file,o.file_key AS own_file FROM '.$this->p.'company_project_files f LEFT JOIN '.$this->p.'company_portal_projects_files v ON v.issuer_id=f.issuer_id AND v.project_id=f.project_id AND v.file_key=f.file_key AND v.visible=1 LEFT JOIN '.$this->p.'company_project_contact_files o ON o.issuer_id=f.issuer_id AND o.project_id=f.project_id AND o.file_key=f.file_key AND o.client_id=? AND o.contact_id=? WHERE f.issuer_id=? AND f.project_id=? AND f.task_id IS NULL AND f.active=1 AND (v.file_key IS NOT NULL OR (o.file_key IS NOT NULL AND f.actor_id=0 AND ?=\'1\')) ORDER BY f.file_key LIMIT 21 FOR UPDATE',[$who['client_id'],$who['contact_id'],$issuer,$project,$allowOwn?1:0]);if(count($rows)>20)throw new DomainException('company_project_file_limit');$bytes=0;foreach($rows as $r){self::metadata($r);if($r['published_file']===null){$origin=$this->ownMetadata($issuer,$project,$r['file_key'],$who);$receipts=$this->rows('SELECT operation,result_json FROM '.$this->p.'company_project_file_requests WHERE issuer_id=? AND project_id=? AND client_id=? AND contact_id=? AND file_key=? AND operation=\'upload\' FOR UPDATE',[$issuer,$project,$who['client_id'],$who['contact_id'],$r['file_key']]);if(count($receipts)!==1)throw new RuntimeException('issuer_project_file_unconfirmed');$this->snapshot($receipts[0],$origin,$project);}$bytes+=(int)$r['byte_size'];if($bytes>20971520)throw new DomainException('company_project_file_limit');}return $rows;
    }
    public function bundle(array $session,$issuer,$project):array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project):array{
            $ctx=$this->context($session,$issuer,$project,false,false);$rows=$this->eligible($issuer,$project,$ctx['who'],$ctx['rules']['upload_files']===1);if(!$rows)throw new DomainException('company_project_file_unavailable');$files=[];foreach($rows as $row)$files[]=$this->blob($issuer,$project,$row['file_key'],$row);$zip=CompanyProjectFilesZip::build($files);unset($files);$proof=self::hash($rows);$this->audit($issuer,$project,'company_project_contact_files_bundle',['contact_id'=>$ctx['who']['contact_id'],'manifest_hash'=>$proof,'archive_sha256'=>hash('sha256',$zip)]);if($this->context($session,$issuer,$project,false,false)!==$ctx||$this->eligible($issuer,$project,$ctx['who'],$ctx['rules']['upload_files']===1)!==$rows)throw new DomainException('company_project_file_unavailable');foreach($rows as $row)$this->blob($issuer,$project,$row['file_key'],$row);return ['content'=>$zip,'content_hash'=>hash('sha256',$zip),'manifest_hash'=>$proof];
        });
    }
    public function confirmBundle(array $session,$issuer,$project,string $proof):void
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);self::key($proof);$this->tx(function()use($session,$issuer,$project,$proof):void{$ctx=$this->context($session,$issuer,$project,false,false);$rows=$this->eligible($issuer,$project,$ctx['who'],$ctx['rules']['upload_files']===1);if(!$rows||!hash_equals($proof,self::hash($rows)))throw new DomainException('company_project_file_unavailable');foreach($rows as $row)$this->blob($issuer,$project,$row['file_key'],$row);$this->audit($issuer,$project,'company_project_contact_files_bundle_confirmed',['contact_id'=>$ctx['who']['contact_id'],'manifest_hash'=>$proof]);if($this->context($session,$issuer,$project,false,false)!==$ctx||$this->eligible($issuer,$project,$ctx['who'],$ctx['rules']['upload_files']===1)!==$rows)throw new DomainException('company_project_file_unavailable');foreach($rows as $row)$this->blob($issuer,$project,$row['file_key'],$row);});
    }
}
