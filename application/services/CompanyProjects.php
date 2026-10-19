<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsStorage.php';
final class CompanyProjects extends CompanyProjectsStorage
{
    protected string $feature='projects';
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','responsible_id','name','description','start_date','due_date','status']))throw new DomainException('company_project_invalid_input');
        $data=['client_id'=>IssuerPolicy::id($input['client_id']??null),'responsible_id'=>IssuerPolicy::id($input['responsible_id']??null),'name'=>self::text($input['name']??null,191),'description'=>self::text($input['description']??'',4000,false),'start_date'=>self::date($input['start_date']??null),'due_date'=>self::date($input['due_date']??null),'status'=>$input['status']??'active'];
        if(!in_array($data['status'],['active','completed','cancelled'],true)||$data['due_date']<$data['start_date'])throw new DomainException('company_project_invalid_input');return $data;
    }
    private function references(int $issuer,int $actor,array $data):void
    {
        $client=(new CompanyClients($this->db,$this->p))->get($issuer,$actor,$data['client_id']);if(!$client||$client['active']!==1)throw new DomainException('company_project_reference_denied');$this->responsible($issuer,$data['responsible_id']);
    }
    private function row(int $issuer,int $id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_project_access_denied');return $r;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='active')throw new DomainException('company_project_invalid_input');
        return $this->operation($issuer,$actor,'create',$key,'project_created',$data,function()use($issuer,$actor,$data):int{$this->references($issuer,$actor,$data);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_projects(issuer_id,client_id,responsible_id,name,description,start_date,due_date,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',[$issuer,$data['client_id'],$data['responsible_id'],$data['name'],$data['description'],$data['start_date'],$data['due_date'],$data['status'],$now,$now]);$id=(int)$this->db->insert_id();$row=$this->row($issuer,$id);foreach($data as $f=>$v)if((string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;});
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);
        return $this->operation($issuer,$actor,'edit',$key,'project_updated',[$id,$revision,$data],function()use($issuer,$actor,$id,$revision,$data):int{
            $old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_project_stale');if($old['status']!=='active')throw new DomainException('company_project_closed');
            if((int)$old['client_id']!==$data['client_id'])throw new DomainException('company_project_reference_denied');$this->references($issuer,$actor,$data);
            if($data['status']!=='active'&&$this->rows('SELECT id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND status<>? LIMIT 1 FOR UPDATE',[$issuer,$id,'done']))throw new DomainException('company_project_open_tasks');
            if($this->rows('SELECT id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND (due_date>? OR due_date<?) LIMIT 1 FOR UPDATE',[$issuer,$id,$data['due_date'],$data['start_date']]))throw new DomainException('company_project_invalid_input');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_projects SET responsible_id=?,name=?,description=?,start_date=?,due_date=?,status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['responsible_id'],$data['name'],$data['description'],$data['start_date'],$data['due_date'],$data['status'],date('Y-m-d H:i:s'),$issuer,$id,$revision]);
            $stored=$this->row($issuer,$id);if((int)$stored['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($data as $f=>$v)if((string)$stored[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;
        });
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$this->authorize($issuer,$actor,'view');return $row;});
    }
    public function page($issuer,$actor,$after=0):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT id,name,status,due_date,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND id>? ORDER BY id LIMIT 51',[$issuer,$after]);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];
    }
}
