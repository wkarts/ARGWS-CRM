<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectTable.php';
require_once __DIR__.'/NativeCompanyProjectRead.php';
require_once __DIR__.'/NativeCompanyWorkAssignmentsSchema.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Native project filters use actual project facts, never a staff eligibility or billing catalogue. */
final class NativeCompanyProjectTableFactory
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function data(int$issuer,int$actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_project_access_denied');$primary=$acl->primaryId();
        $summary=(new NativeCompanyProjectRead($this->db,$this->p))->summary($issuer,$actor,'_l');NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);
        $ownedSql='SELECT DISTINCT s.staffid,s.firstname,s.lastname FROM '.$this->p.'company_project_members m JOIN '.$this->p.'company_projects p ON p.issuer_id=m.issuer_id AND p.id=m.project_id JOIN '.$this->p.'staff s ON s.staffid=m.staff_id WHERE m.issuer_id=? AND m.active=1 ORDER BY s.staffid';
        $owned=$this->rows($ownedSql,[$issuer]);$members=$owned;$legacy=[];$legacySql=null;
        if($primary===$issuer){$legacySql='SELECT DISTINCT s.staffid,s.firstname,s.lastname FROM '.$this->p.'project_members m JOIN '.$this->p.'projects p ON p.id=m.project_id JOIN '.$this->p.'staff s ON s.staffid=m.staff_id WHERE '.\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'projects','p').' ORDER BY s.staffid';$legacy=$this->rows($legacySql);$members=array_merge($members,$legacy);}
        $unique=[];foreach($members as$row){$id=IssuerPolicy::id($row['staffid']);if($acl->can($issuer,$id,'projects','view'))$unique[$id]=['value'=>(string)$id,'label'=>trim($row['firstname'].' '.$row['lastname'])];}ksort($unique,SORT_NUMERIC);
        $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($issuer,$actor,'projects',['active'=>1]);
        if(!$acl->can($issuer,$actor,'projects','view')||$acl->primaryId()!==$primary||$this->rows($ownedSql,[$issuer])!==$owned||($legacySql!==null&&$this->rows($legacySql)!==$legacy))throw new DomainException('native_project_snapshot_changed');
        $options=['billing_type'=>[['value'=>'1','label'=>_l('project_billing_type_fixed_cost')],['value'=>'2','label'=>_l('project_billing_type_project_hours')],['value'=>'3','label'=>_l('project_billing_type_project_task_hours_hourly_rate')]],'status'=>array_map(static fn($r)=>['value'=>$r['id'],'label'=>$r['name']],$summary['statuses']),'members'=>array_values($unique)];
        return['options'=>$options,'summary'=>$summary,'customDefinitions'=>$definitions];
    }
    public function make(int$issuer,int$actor,string$identifier='projects',array$savedFilters=[]):NativeCompanyProjectTable
    {$data=$this->data($issuer,$actor);return new NativeCompanyProjectTable($this->db,$this->p,$issuer,$actor,$identifier,$data['options'],$savedFilters,$data['customDefinitions']);}
}
