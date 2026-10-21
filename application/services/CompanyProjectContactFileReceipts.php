<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectContactFilesStorage.php';
/** Receipt key is a locator only; every private read checks native identity and file origin. */
final class CompanyProjectContactFileReceipts extends CompanyProjectContactFilesStorage
{
    private function receipt(array $session,int $issuer,int $project,string $key):array
    {
        self::key($key);$ctx=$this->context($session,$issuer,$project);$who=$ctx['who'];$row=$this->rows('SELECT request_key,file_key,operation,result_json,created_at FROM '.$this->p.'company_project_file_requests WHERE request_key=? AND issuer_id=? AND project_id=? AND client_id=? AND contact_id=? FOR UPDATE',[$key,$issuer,$project,$who['client_id'],$who['contact_id']])[0]??null;if(!$row)throw new DomainException('company_project_file_unavailable');$meta=$this->ownMetadata($issuer,$project,$row['file_key'],$who);$snapshot=$this->snapshot($row,$meta,$project);return ['context'=>$ctx,'meta'=>$meta,'snapshot'=>$snapshot,'created_at'=>$row['created_at']];
    }
    public function controls(array $session,$issuer,$project):?array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project):?array{
            try{$ctx=$this->context($session,$issuer,$project,false,false);}catch(DomainException $e){return null;}$who=$ctx['who'];$items=$this->rows('SELECT o.file_key,o.created_at,r.request_key FROM '.$this->p.'company_project_contact_files o JOIN '.$this->p.'company_project_file_requests r ON r.issuer_id=o.issuer_id AND r.project_id=o.project_id AND r.file_key=o.file_key AND r.client_id=o.client_id AND r.contact_id=o.contact_id AND r.operation=? WHERE o.issuer_id=? AND o.project_id=? AND o.client_id=? AND o.contact_id=? ORDER BY o.created_at DESC,o.file_key LIMIT 21',['upload',$issuer,$project,$who['client_id'],$who['contact_id']]);if(count($items)>20)throw new DomainException('company_project_file_limit');$out=[];$proof=[];if($ctx['rules']['upload_files']!==1)$items=[];
            foreach($items as $item){$r=$this->receipt($session,$issuer,$project,$item['request_key']);$out[]=['request_key'=>$item['request_key'],'filename'=>$r['meta']['filename'],'active'=>(int)$r['meta']['active'],'created_at'=>$r['created_at']];$proof[$item['request_key']]=$r;}$revision=$this->state($issuer,$project);$hide=$this->canHide($issuer);if($this->context($session,$issuer,$project,false,false)!==$ctx||$this->state($issuer,$project)!==$revision||$this->canHide($issuer)!==$hide)throw new DomainException('company_project_file_unavailable');foreach($proof as $key=>$r)if($this->receipt($session,$issuer,$project,$key)!==$r)throw new DomainException('company_project_file_unavailable');return ['project_revision'=>(int)$ctx['parent']['revision'],'collection_revision'=>$revision,'can_upload'=>$ctx['rules']['upload_files']===1&&$ctx['parent']['status']==='active','can_hide'=>$hide&&$ctx['rules']['upload_files']===1&&$ctx['parent']['status']==='active','items'=>$out];
        });
    }
    public function get(array $session,$issuer,$project,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project,$key):array{
            $r=$this->receipt($session,$issuer,$project,$key);$revision=$this->state($issuer,$project);$hide=$this->canHide($issuer);$public=$this->rows('SELECT file_key FROM '.$this->p.'company_portal_projects_files WHERE issuer_id=? AND project_id=? AND file_key=? AND visible=1 FOR UPDATE',[$issuer,$project,$r['meta']['file_key']])!==[];if($this->receipt($session,$issuer,$project,$key)!==$r||$this->state($issuer,$project)!==$revision||$this->canHide($issuer)!==$hide)throw new DomainException('company_project_file_unavailable');return ['request_key'=>$key,'receipt'=>$r['snapshot'],'created_at'=>$r['created_at'],'file_available'=>(int)$r['meta']['active']===1&&$r['snapshot']['operation']==='upload','public_current'=>$public&&(int)$r['meta']['active']===1,'project_revision'=>(int)$r['context']['parent']['revision'],'collection_revision'=>$revision,'can_hide'=>$hide&&$r['context']['parent']['status']==='active'&&(int)$r['meta']['active']===1];
        });
    }
    public function file(array $session,$issuer,$project,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project,$key):array{
            $r=$this->receipt($session,$issuer,$project,$key);if($r['snapshot']['operation']!=='upload'||(int)$r['meta']['active']!==1)throw new DomainException('company_project_file_unavailable');$file=$this->blob($issuer,$project,$r['meta']['file_key'],$r['meta']);$this->audit($issuer,$project,'company_project_contact_file_author_downloaded',['contact_id'=>$r['context']['who']['contact_id'],'file_key'=>$r['meta']['file_key'],'request_key'=>$key,'sha256'=>$file['content_hash']]);if($this->receipt($session,$issuer,$project,$key)!==$r)throw new DomainException('company_project_file_unavailable');$this->blob($issuer,$project,$r['meta']['file_key'],$r['meta']);return $file;
        });
    }
    public function confirmFile(array $session,$issuer,$project,string $key,string $hash):void{if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_project_file_unavailable');$file=$this->file($session,$issuer,$project,$key);if(!hash_equals($file['content_hash'],$hash))throw new DomainException('company_project_file_unavailable');}
}
