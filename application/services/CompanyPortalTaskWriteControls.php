<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalTaskWriteStorage.php';
/** Render affordances from persisted flags; writers independently revalidate everything. */
final class CompanyPortalTaskWriteControls extends CompanyPortalTaskWriteStorage
{
 public function get(array $session,$issuer,$project,$task=null):?array
 {
  $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);$task=$task===null?null:IssuerPolicy::id($task);return $this->tx(function()use($session,$issuer,$project,$task):?array{
   $out=array_fill_keys(CompanyPortalTaskWriteSchema::FLAGS,false)+['revision'=>null,'project_revision'=>null,'own_files'=>[]];$proof=[];
   foreach(CompanyPortalTaskWriteSchema::FLAGS as $flag){try{$ctx=$this->context($session,$issuer,$project,$flag);}catch(DomainException $e){continue;}$proof[$flag]=$ctx;$out['project_revision']=(int)$ctx['parent']['revision'];if($task===null){if($flag==='create_tasks')$out[$flag]=true;continue;}
    try{$row=$this->accessibleTask($issuer,$project,$task,$ctx['who']);if($row['status']==='done')continue;$out['revision']=(int)$row['revision'];if($flag==='create_tasks')continue;if($flag==='edit_tasks'){$origin=$this->rows('SELECT contact_id,client_id,billing_state FROM '.$this->p.'company_portal_task_origins WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$origin||(int)$origin['contact_id']!==$ctx['who']['contact_id']||(int)$origin['client_id']!==$ctx['who']['client_id']||$origin['billing_state']!=='unbilled')continue;}$out[$flag]=true;
     if($flag==='upload_on_tasks'){$candidates=$this->rows("SELECT f.file_key,v.source_hash FROM {$this->p}company_portal_task_files o JOIN {$this->p}company_project_files f ON f.file_key=o.file_key AND f.issuer_id=o.issuer_id AND f.project_id=o.project_id AND f.task_id=o.task_id AND f.active=1 JOIN {$this->p}company_portal_task_children v ON v.issuer_id=f.issuer_id AND v.project_id=f.project_id AND v.task_id=f.task_id AND v.kind='file' AND v.child_key=f.file_key AND v.visible=1 WHERE o.issuer_id=? AND o.project_id=? AND o.task_id=? AND o.client_id=? AND o.contact_id=? ORDER BY f.file_key LIMIT 20 FOR UPDATE",[$issuer,$project,$task,$ctx['who']['client_id'],$ctx['who']['contact_id']]);foreach($candidates as $candidate)if(hash_equals($candidate['source_hash'],self::hash($this->child($issuer,$project,$task,'file',$candidate['file_key']))))$out['own_files'][]=$candidate['file_key'];}
    }catch(DomainException $e){continue;}
   }
   foreach($proof as $flag=>$ctx)if($this->context($session,$issuer,$project,$flag)!==$ctx)throw new DomainException('company_portal_task_unavailable');if($out['revision']!==null)$this->accessibleTask($issuer,$project,$task,$ctx['who']);return $out['project_revision']===null?null:$out;
  });
 }
}
