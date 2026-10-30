<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPusherProfile.php';
require_once __DIR__.'/CompanyTasks.php';

/** One source kind only: an owned task, its current parent and current assigned staff recipient. */
final class CompanyTaskPusherNotice
{
    public function __construct(private $db,private string $p){}
    public function tables():array{return ['company_tasks','company_projects'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function receiver(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);foreach([['pusher','view'],['tasks','view'],['projects','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_pusher_denied');
    }
    public function permissionStamp(int $issuer,int $actor):string
    {
        $this->receiver($issuer,$actor);return hash('sha256',json_encode([array_map(fn($r)=>['active'=>(int)$r['active'],'admin'=>(int)$r['admin']],$this->rows('SELECT active,admin FROM '.$this->p.'staff WHERE staffid=?',[$actor])),array_map(fn($r)=>['can_view'=>(int)$r['can_view']],$this->rows('SELECT can_view FROM '.$this->p.'issuers_staff WHERE issuer_id=? AND staff_id=?',[$issuer,$actor])),$this->rows('SELECT feature,capability FROM '.$this->p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? ORDER BY feature,capability',[$issuer,$actor]),array_map(fn($r)=>['master_staff_id'=>(int)$r['master_staff_id']],$this->rows('SELECT master_staff_id FROM '.$this->p.'issuers_installation_identity WHERE id=1'))],JSON_THROW_ON_ERROR));
    }
    public function snapshotInOwned(int $issuer,int $actor,int $task):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge((new CompanyPusherProfile($this->db,$this->p))->tables(),$this->tables()));
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);foreach([['pusher','publish'],['tasks','edit'],['tasks','view'],['projects','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_pusher_denied');
        $row=$this->rows('SELECT t.id,t.issuer_id,t.project_id,t.assignee_id,t.name,t.description,t.status,t.due_date,t.checklist_json,t.revision,p.revision project_revision,p.status project_status FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.issuer_id=? AND t.id=? FOR UPDATE',[$issuer,$task])[0]??null;if(!$row)throw new DomainException('company_pusher_denied');
        $recipient=(int)$row['assignee_id'];$this->receiver($issuer,$recipient);foreach(['id','issuer_id','project_id','assignee_id','revision','project_revision']as$f)$row[$f]=(int)$row[$f];$row['actor_permissions']=$this->permissionStamp($issuer,$actor);$row['recipient_permissions']=$this->permissionStamp($issuer,$recipient);return ['row'=>$row,'recipient_id'=>$recipient,'source_hash'=>hash('sha256',json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
    }
}
