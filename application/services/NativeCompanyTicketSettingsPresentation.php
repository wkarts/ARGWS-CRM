<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCommands.php';
/** Explicit editor data for the existing ticket-settings partial. No global contact/project hydration. */
final class NativeCompanyTicketSettingsPresentation
{
    public function __construct(private$db,private string$p){}
    public function read(int$i,int$a,$id):array{
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','edit'))throw new DomainException('native_ticket_update_denied');
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$before=$reader->get($i,$a,$id);if(!$before['metadata'])throw new DomainException('native_ticket_metadata_required');$m=$before['metadata'];$options=$before['policy']['values'];
        $contacts=new NativeCompanyTicketContacts($this->db,$this->p);$contact=null;
        if($before['contactid']!==null)$contact=IssuerTransaction::run($this->db,$this->p,$contacts->tables(),fn()=>$contacts->getForEditInOwnedTransaction($i,$a,$before['contactid'],$before['userid']));
        $projectName='';$project=null;if($m['project_source_id']!==null){$project=NativeCompanyResourceId::encode($m['project_source_kind'],(int)$m['project_source_id']);$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$project);$projectName=$ref['name'];}
        $tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($i,$a,'ticket',$id);$fields=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$fields->definitions($i,$a,'tickets',[],'edit');$values=$fields->values($i,$a,'tickets',$id,'edit');
        $staff=(new NativeCompanyTicketCommands($this->db,$this->p))->staffOptions($i,$a);foreach($staff as&$s)$s['active']=1;unset($s); // staffOptions proves active=1 before projection.
        if($before['assigned']>0&&!in_array($before['assigned'],array_map('intval',array_column($staff,'staffid')),true)){
            // An existing assignment is provenance, not a new membership grant. Keep it visible rather than silently submitting 'unassigned'.
            $existing=IssuerStorage::query($this->db,'SELECT staffid,firstname,lastname,active FROM '.$this->p.'staff WHERE staffid=?',[$before['assigned']])->row_array();if(!$existing)throw new DomainException('native_ticket_assignee_unavailable');$staff[]=$existing;
        }
        $departments=new NativeCompanyDepartments($this->db,$this->p);$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);
        $t=(object)($before+['project_id'=>$project,'email'=>$contact['email']??$before['ticket_email'],'ticket_emails'=>$contact['ticket_emails']??null]);
        $out=['ticket'=>$t,'staff'=>$staff,'departments'=>!$acl->administrative($i,$a)&&$options['staff_access_only_assigned_departments']==='1'?$departments->forStaff($i,$a):$departments->catalog($i,$a),'priorities'=>$catalog->list($i,$a,'priority'),'services'=>$catalog->list($i,$a,'service'),
            'native_ticket_presentation'=>['actor'=>$a,'options'=>$options,'inline_service'=>$acl->administrative($i,$a),'project_name'=>$projectName,'contact'=>$contact,'tags_value'=>implode(',',array_column($tags['tags'],'name')),'tags_revision'=>$tags['revision'],'custom_fields_revision'=>hash('sha256',json_encode([$definitions,$values],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))]];
        if(!$acl->can($i,$a,'tickets','edit')||$reader->get($i,$a,$id)!==$before)throw new DomainException('native_ticket_changed');return$out;
    }
}
