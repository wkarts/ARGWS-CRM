<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContractsStorage.php';
final class CompanyTickets extends CompanyContractsStorage
{
    protected string $feature='tickets';
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','assignee_id','subject','body','priority']))throw new DomainException('company_operation_invalid_input');$data=['client_id'=>IssuerPolicy::id($input['client_id']??null),'assignee_id'=>IssuerPolicy::id($input['assignee_id']??null),'subject'=>self::text($input['subject']??null,191),'body'=>self::text($input['body']??null,12000),'priority'=>$input['priority']??'normal'];if(!in_array($data['priority'],['low','normal','high'],true))throw new DomainException('company_operation_invalid_input');return $data;
    }
    private function references(int $issuer,int $actor,array $data):void
    {
        $client=(new CompanyClients($this->db,$this->p))->get($issuer,$actor,$data['client_id']);if(!$client||$client['active']!==1)throw new DomainException('company_operation_reference_denied');$this->responsible($issuer,$data['assignee_id']);
    }
    private function row(int $issuer,int $id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_operation_access_denied');return $r;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);return $this->operation($issuer,$actor,'create',$key,'ticket_created',$data,function()use($issuer,$actor,$data):int{$this->references($issuer,$actor,$data);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_tickets(issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,?,?)',[$issuer,$data['client_id'],$data['assignee_id'],$data['subject'],$data['body'],'open',$data['priority'],$now,$now]);$id=(int)$this->db->insert_id();$row=$this->row($issuer,$id);foreach($data as $k=>$v)if((string)$row[$k]!== (string)$v)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;});
    }
    public function change($issuer,$actor,$id,$revision,$assignee,string $status,string $priority,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$assignee=IssuerPolicy::id($assignee);if(!in_array($status,['open','resolved','closed'],true)||!in_array($priority,['low','normal','high'],true))throw new DomainException('company_operation_invalid_input');
        return $this->operation($issuer,$actor,'edit',$key,'ticket_updated',[$id,$revision,$assignee,$status,$priority],function()use($issuer,$id,$revision,$assignee,$status,$priority):int{$row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');$this->responsible($issuer,$assignee);IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET assignee_id=?,status=?,priority=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$assignee,$status,$priority,date('Y-m-d H:i:s'),$issuer,$id,$revision]);$r=$this->row($issuer,$id);if((int)$r['revision']!==$revision+1||(int)$r['assignee_id']!==$assignee||$r['status']!==$status||$r['priority']!==$priority)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;});
    }
    public function reply($issuer,$actor,$id,$revision,string $body,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$body=self::text($body,12000);return $this->operation($issuer,$actor,'edit',$key,'ticket_replied',[$id,$revision,$body],function()use($issuer,$actor,$id,$revision,$body):int{$row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');if($row['status']!=='open')throw new DomainException('company_ticket_closed');if(count($this->rows('SELECT id FROM '.$this->p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_operation_limit');$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_replies(issuer_id,ticket_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$issuer,$id,$actor,$body,$now]);$reply=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'company_ticket_replies WHERE id=? AND issuer_id=? AND ticket_id=? AND actor_id=? AND body=?',[$reply,$issuer,$id,$actor,$body]))throw new RuntimeException('issuer_operation_write_unconfirmed');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$id,$revision]);if((int)$this->row($issuer,$id)['revision']!==$revision+1)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;});
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$row['replies']=$this->rows('SELECT actor_id,body,created_at FROM '.$this->p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=? ORDER BY id LIMIT 500',[$issuer,$id]);$this->authorize($issuer,$actor,'view');return $row;});
    }
    public function page($issuer,$actor,$after=0,string $search=''):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$search=self::text($search,191,false);$this->authorize($issuer,$actor,'view');$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$rows=$this->rows('SELECT id,subject,status,priority,updated_at,revision FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id>? AND (subject LIKE ? ESCAPE \'!\' OR body LIKE ? ESCAPE \'!\') ORDER BY id LIMIT 51',[$issuer,$after,$needle,$needle]);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];
    }
}
