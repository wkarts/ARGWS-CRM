<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskChecklistSchema.php';require_once __DIR__.'/NativeCompanyResourceId.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Same native checklist-template catalog/actions, resolved by persisted company lineage. */
final class NativeCompanyTaskChecklistTemplates
{
    public function __construct(private$db,private string$p){NativeCompanyTaskChecklistSchema::definitions($p);}
    public function tableNames():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit','tasks_checklist_templates'],array_keys(NativeCompanyTaskChecklistSchema::definitions($this->p)));}
    private function rows(string$q,array$a=[]):array{return IssuerStorage::query($this->db,$q,$a)->result_array();}
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();if((string)($row['affected']??'')!=='1')throw new RuntimeException('issuer_task_template_unconfirmed');
    }
    private function authorize(int$i,int$a,string$cap='view'):bool
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tasks','view')||($cap!=='view'&&!$acl->can($i,$a,'checklist_templates',$cap)))throw new DomainException('native_task_template_denied');return$acl->primaryId()===$i;
    }
    private function lineage(int$i,int$a,string$cap='view'):string
    {
        $primary=$this->authorize($i,$a,$cap);NativeCompanyTaskChecklistSchema::assertValid($this->db,$this->p);
        if($primary&&$this->rows('SELECT id FROM '.$this->p.'company_task_checklist_templates WHERE issuer_id=? LIMIT 1',[$i]))throw new DomainException('native_task_template_lineage_conflict');
        return$primary?'task_checklist_template':'company_task_checklist_template';
    }
    private static function fingerprint(array$row):string{return hash('sha256',json_encode(array_map(static fn($v)=>$v===null?null:(string)$v,$row),JSON_THROW_ON_ERROR));}
    private function row(int$i,string$source,int$id,bool$lock=false):array
    {
        $sql=$source==='task_checklist_template'?'SELECT id,description FROM '.$this->p.'tasks_checklist_templates WHERE id=?':'SELECT * FROM '.$this->p.'company_task_checklist_templates WHERE issuer_id=? AND id=?';$args=$source==='task_checklist_template'?[$id]:[$i,$id];$rows=$this->rows($sql.($lock?' FOR UPDATE':''),$args);if(count($rows)!==1)throw new DomainException('native_task_template_denied');return$rows[0];
    }
    private function dto(string$source,array$row):array{return['id'=>NativeCompanyResourceId::encode($source,$row['id']),'description'=>$row['description'],'revision'=>self::fingerprint($row),'source_kind'=>$source,'source_id'=>(int)$row['id']];}
    public function catalog(int$i,int$a):array
    {
        $source=$this->lineage($i,$a);$sql=$source==='task_checklist_template'?'SELECT id,description FROM '.$this->p.'tasks_checklist_templates ORDER BY description,id LIMIT 5001':'SELECT * FROM '.$this->p.'company_task_checklist_templates WHERE issuer_id=? ORDER BY description,id LIMIT 5001';$args=$source==='task_checklist_template'?[]:[$i];$rows=$this->rows($sql,$args);if(count($rows)>5000)throw new DomainException('native_task_template_limit');if($source!==$this->lineage($i,$a)||$this->rows($sql,$args)!==$rows)throw new DomainException('native_task_template_changed');return array_map(fn($r)=>$this->dto($source,$r),$rows);
    }
    public function get(int$i,int$a,$canonical):array
    {
        $source=$this->lineage($i,$a);$id=NativeCompanyResourceId::decode('task_checklist_template',$canonical);if($id['source_kind']!==$source)throw new DomainException('native_task_template_denied');$row=$this->row($i,$source,$id['source_id']);if($source!==$this->lineage($i,$a)||$this->row($i,$source,$id['source_id'])!==$row)throw new DomainException('native_task_template_changed');return$this->dto($source,$row);
    }
    private static function description($v):string{if(!is_string($v)||trim($v)===''||mb_strlen($v)>4000||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('native_task_template_invalid');return$v;}
    public function create(int$i,int$a,string$description,string$key):array{return$this->write($i,$a,'create',['description'=>self::description($description)],$key);}
    public function delete(int$i,int$a,$canonical,string$revision,string$key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_task_template_invalid');return$this->write($i,$a,'delete',['id'=>(string)$canonical,'revision'=>$revision],$key);
    }
    private function write(int$i,int$a,string$operation,array$input,string$key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_task_template_invalid');$source=$this->lineage($i,$a,$operation);$hash=hash('sha256',json_encode([$source,$input],JSON_THROW_ON_ERROR));
        return IssuerTransaction::run($this->db,$this->p,$this->tableNames(),function()use($i,$a,$operation,$input,$key,$source,$hash){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);if($this->lineage($i,$a,$operation)!==$source)throw new DomainException('native_task_template_denied');
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_task_checklist_template_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){if((int)$receipt['issuer_id']!==$i||(int)$receipt['actor_id']!==$a||$receipt['operation']!==$operation||$receipt['source_kind']!==$source||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('native_task_template_request_conflict');$id=(int)$receipt['result_id'];if($operation==='create')return$this->dto($source,$this->row($i,$source,$id,true));return['success'=>true,'id'=>NativeCompanyResourceId::encode($source,$id)];}
            if($operation==='create'){
                if($source==='task_checklist_template')IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'tasks_checklist_templates(description) VALUES(?)',[$input['description']]);
                else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_checklist_templates(issuer_id,description,revision,created_by,created_at) VALUES(?,?,1,?,?)',[$i,$input['description'],$a,date('Y-m-d H:i:s')]);
                $this->assertInserted();$id=(int)$this->db->insert_id();$row=$this->row($i,$source,$id,true);if($row['description']!==$input['description'])throw new RuntimeException('issuer_task_template_unconfirmed');$result=$this->dto($source,$row);
            }else{
                $decoded=NativeCompanyResourceId::decode('task_checklist_template',$input['id']);if($decoded['source_kind']!==$source)throw new DomainException('native_task_template_denied');$id=$decoded['source_id'];$row=$this->row($i,$source,$id,true);if(!hash_equals(self::fingerprint($row),$input['revision']))throw new DomainException('native_task_template_stale');$table=$source==='task_checklist_template'?'tasks_checklist_templates':'company_task_checklist_templates';$where=$source==='task_checklist_template'?'id=?':'issuer_id=? AND id=?';$args=$source==='task_checklist_template'?[$id]:[$i,$id];IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$args);if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$where,$args))throw new RuntimeException('issuer_task_template_unconfirmed');$result=['success'=>true,'id'=>NativeCompanyResourceId::encode($source,$id)];
            }
            $nonce=bin2hex(random_bytes(16));$meta=json_encode(['source_kind'=>$source,'request_key'=>$key,'input_hash'=>$hash,'nonce'=>$nonce],JSON_THROW_ON_ERROR);$now=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$i,$a,$id,'native_task_template_'.$operation,$meta,$now]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$i,$a,$id,'native_task_template_'.$operation,$meta]))throw new RuntimeException('issuer_task_template_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_checklist_template_requests(request_key,issuer_id,actor_id,operation,source_kind,input_hash,result_id,created_at) VALUES(?,?,?,?,?,?,?,?)',[$key,$i,$a,$operation,$source,$hash,$id,$now]);
            $actual=$this->rows('SELECT issuer_id,actor_id,operation,source_kind,input_hash,result_id FROM '.$this->p.'company_task_checklist_template_requests WHERE request_key=?',[$key])[0]??null;if($actual===null||array_map('strval',$actual)!==array_map('strval',['issuer_id'=>$i,'actor_id'=>$a,'operation'=>$operation,'source_kind'=>$source,'input_hash'=>$hash,'result_id'=>$id]))throw new RuntimeException('issuer_task_template_unconfirmed');
            if($operation==='create'&&$this->row($i,$source,$id,true)!==$row)throw new RuntimeException('issuer_task_template_unconfirmed');if($operation==='delete'&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$where,$args))throw new RuntimeException('issuer_task_template_unconfirmed');if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$i,$a,$id,'native_task_template_'.$operation,$meta]))throw new RuntimeException('issuer_task_template_unconfirmed');if($this->lineage($i,$a,$operation)!==$source)throw new DomainException('native_task_template_denied');return$result;
        });
    }
}
