<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskItems.php';
require_once __DIR__.'/NativeCompanyTaskChecklistTemplates.php';
require_once __DIR__.'/NativeCompanyTaskChecklistVisibility.php';
/** Inputs for the original checklist partial. Never reads global task/staff models. */
final class NativeCompanyTaskChecklistPresentation
{
    public function __construct(private$db,private string$p){}
    public function read(int$i,int$a,$id):array
    {
        $reader=new NativeCompanyTaskItems($this->db,$this->p);$state=$reader->read($i,$a,$id);
        $projection=new NativeCompanyTaskIndexProjection($this->db,$this->p);$item=$projection->readOne($i,$a,$id);$acl=new IssuerResourceAccess($this->db,$this->p);$edit=$acl->can($i,$a,'tasks','edit');$delete=$acl->can($i,$a,'tasks','delete');$template=$acl->can($i,$a,'checklist_templates','create');$admin=$acl->administrative($i,$a);$catalog=(new NativeCompanyTaskChecklistTemplates($this->db,$this->p))->catalog($i,$a);
        $checklists=[];$ids=[];foreach($state['items']as$row){$checklists[]=['id'=>$row['id'],'revision'=>$row['revision'],'description'=>$row['description'],'finished'=>$row['finished'],'finished_from'=>$row['finished_by'],'addedfrom'=>$row['created_by'],'assigned'=>$row['assigned_id']];foreach(['created_by','finished_by','assigned_id']as$key)if($row[$key]!==null)$ids[(int)$row[$key]]=true;}if(!$state['initialized'])foreach($state['historical_items']as$row)$checklists[]=['id'=>null,'revision'=>null,'description'=>$row['text'],'finished'=>$row['done'],'finished_from'=>null,'addedfrom'=>null,'assigned'=>null];
        $names=[];$frames=[];foreach(array_keys($ids)as$staff){$rows=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$staff])->result_array();if(count($rows)!==1)throw new DomainException('native_task_checklist_actor_unavailable');$frames[$staff]=$rows;$names[(string)$staff]=trim($rows[0]['firstname'].' '.$rows[0]['lastname']);}
        $native=['actor'=>$a,'admin'=>$admin,'can_edit'=>$edit&&$state['initialized'],'can_delete'=>$delete&&$state['initialized'],'can_create_template'=>$template&&$state['initialized'],'names'=>$names,'template_descriptions'=>array_column($catalog,'description'),'task_revision'=>(int)$item['row']['revision'],'revision'=>$state['revision'],'initialized'=>$state['initialized']];
        $visibility=new NativeCompanyTaskChecklistVisibility($this->db,$this->p);$hidden=$visibility->read($i,$a,$id);
        $out=['task_id'=>(string)$id,'checklists'=>$checklists,'task_staff_members'=>$item['assignees'],'current_user_is_creator'=>null,'hide_completed_items'=>$hidden,'native_task_checklist'=>$native];
        if($reader->read($i,$a,$id)!==$state||$projection->readOne($i,$a,$id)!==$item||(new NativeCompanyTaskChecklistTemplates($this->db,$this->p))->catalog($i,$a)!==$catalog||$visibility->read($i,$a,$id)!==$hidden||$acl->can($i,$a,'tasks','edit')!==$edit||$acl->can($i,$a,'tasks','delete')!==$delete||$acl->can($i,$a,'checklist_templates','create')!==$template||$acl->administrative($i,$a)!==$admin)throw new DomainException('native_task_checklist_changed');foreach($frames as$staff=>$rows)if(IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$staff])->result_array()!==$rows)throw new DomainException('native_task_checklist_changed');return$out;
    }
}
