<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectContactFilesSchema.php';require_once __DIR__.'/CompanyProjectsSchema.php';require_once __DIR__.'/CompanyProjectExtrasSchema.php';require_once __DIR__.'/CompanyPortalOperationsSchema.php';require_once __DIR__.'/CompanyPortal.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Exact file parents and native contact authority; never adopts a task or directory-shared client. */
abstract class CompanyProjectContactFilesStorage
{
    protected ?CompanyPortal $portal=null;
    public function __construct(protected $db,protected string $p,?string $secret=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');if($secret!==null)$this->portal=new CompanyPortal($db,$p,$secret);}
    protected function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','issuers_audit','company_projects','company_project_files','company_portal_projects','company_portal_projects_files'],array_keys(CompanyProjectContactFilesSchema::definitions($this->p)));}
    protected function tx(callable $work){CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyPortalOperationsSchema::assertValid($this->db,$this->p);CompanyProjectContactFilesSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    protected static function hash(array $data):string{return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));}
    protected static function revision($v,bool $zero=false):int{if((!is_int($v)&&!is_string($v))||!preg_match($zero?'/^(0|[1-9][0-9]*)$/D':'/^[1-9][0-9]*$/D',(string)$v)||(int)$v>2147483646)throw new DomainException('company_project_file_invalid');return (int)$v;}
    protected static function key(string $key):string{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_file_unavailable');return $key;}
    protected function parent(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT p.id,p.issuer_id,p.client_id,p.name,p.status,p.revision FROM '.$this->p.'company_projects p JOIN '.$this->p.'issuers_clients o ON o.client_id=p.client_id AND o.owner_issuer_id=p.issuer_id JOIN '.$this->p.'clients c ON c.userid=o.client_id AND c.active=1 WHERE p.issuer_id=? AND p.id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$r)throw new DomainException('company_project_file_unavailable');return $r;
    }
    protected function rules(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT upload_files,project_files,revision FROM '.$this->p.'company_project_file_upload_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??['upload_files'=>0,'project_files'=>0,'revision'=>0];foreach(['upload_files','project_files'] as $f){if(!in_array((string)$r[$f],['0','1'],true))throw new DomainException('company_project_file_unavailable');$r[$f]=(int)$r[$f];}$r['revision']=self::revision($r['revision'],true);return $r;
    }
    protected function state(int $issuer,int $project):int{$r=$this->rows('SELECT revision FROM '.$this->p.'company_project_file_states WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??null;return $r?self::revision($r['revision']):0;}
    protected function bump(int $issuer,int $project,int $revision):void
    {
        if($revision>=2147483646)throw new DomainException('company_project_file_limit');if($revision===0)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_project_file_states(issuer_id,project_id,revision) VALUES(?,?,1)',[$issuer,$project]);else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_project_file_states SET revision=revision+1 WHERE issuer_id=? AND project_id=? AND revision=?',[$issuer,$project,$revision]);if($this->state($issuer,$project)!==$revision+1)throw new RuntimeException('issuer_project_file_unconfirmed');
    }
    protected function context(array $session,int $issuer,int $project,bool $writing=false,bool $needsUpload=true):array
    {
        $who=$this->portal?->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_project_file_unavailable');$parent=$this->parent($issuer,$project);$rules=$this->rules($issuer,$project);if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],$writing?['active']:['active','completed','cancelled'],true)||($needsUpload&&$rules['upload_files']!==1)||$rules['project_files']!==1||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_file_unavailable');return ['who'=>$who,'parent'=>$parent,'rules'=>$rules];
    }
    protected function canHide(int $issuer):bool
    {
        if(!class_exists('CompanyProjectFileSettings',false)&&is_file(__DIR__.'/CompanyProjectFileSettings.php'))require_once __DIR__.'/CompanyProjectFileSettings.php';if(!class_exists('CompanyProjectFileSettings',false))return false;try{return CompanyProjectFileSettings::allowContactDeleteInOwnedTransaction($this->db,$this->p,$issuer);}catch(DomainException $invalid){return false;}
    }
    protected function ownMetadata(int $issuer,int $project,string $file,array $who):array
    {
        self::key($file);$r=$this->rows('SELECT f.file_key,f.filename,f.mime,f.byte_size,f.content_hash,f.active,o.author_name,o.created_at FROM '.$this->p.'company_project_contact_files o JOIN '.$this->p.'company_project_files f ON f.issuer_id=o.issuer_id AND f.project_id=o.project_id AND f.file_key=o.file_key WHERE o.issuer_id=? AND o.project_id=? AND o.client_id=? AND o.contact_id=? AND o.file_key=? AND f.task_id IS NULL AND f.actor_id=0 FOR UPDATE',[$issuer,$project,$who['client_id'],$who['contact_id'],$file])[0]??null;if(!$r)throw new DomainException('company_project_file_unavailable');self::metadata($r);if(!is_string($r['author_name'])||preg_match('/^.{0,383}$/usD',$r['author_name'])!==1)throw new RuntimeException('issuer_project_file_unconfirmed');return $r;
    }
    protected static function metadata(array $r):void
    {
        if(!is_string($r['filename']??null)||strlen($r['filename'])>191||preg_match('//u',$r['filename'])!==1||!in_array($r['mime']??null,['application/pdf','image/png'],true)||!preg_match('/^[0-9]+$/D',(string)($r['byte_size']??''))||(int)$r['byte_size']<8||(int)$r['byte_size']>2097152||!preg_match('/^[a-f0-9]{64}$/D',$r['content_hash']??'')||!in_array((string)($r['active']??''),['0','1'],true))throw new RuntimeException('issuer_project_file_unconfirmed');
    }
    protected function blob(int $issuer,int $project,string $file,array $meta,bool $active=true):array
    {
        $r=$this->rows('SELECT filename,mime,byte_size,content_hash,content FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id IS NULL AND file_key=?'.($active?' AND active=1':'').' FOR UPDATE',[$issuer,$project,$file])[0]??null;if(!$r)throw new DomainException('company_project_file_unavailable');foreach(['filename','mime','byte_size','content_hash'] as $f)if((string)($meta[$f]??'')!==(string)$r[$f])throw new RuntimeException('issuer_project_file_unconfirmed');if(strlen($r['content'])!==(int)$r['byte_size']||!hash_equals($r['content_hash'],hash('sha256',$r['content'])))throw new RuntimeException('issuer_project_file_unconfirmed');return $r;
    }
    protected function snapshot(array $row,array $meta,int $project):array
    {
        if(!is_string($row['result_json']??null)||strlen($row['result_json'])>16384)throw new RuntimeException('issuer_project_file_unconfirmed');try{$data=json_decode($row['result_json'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException $invalid){throw new RuntimeException('issuer_project_file_unconfirmed');}
        if(!is_array($data)||array_keys($data)!==['operation','project_id','file_key','author_name','data']||!in_array($data['operation'],['upload','hide'],true)||$data['operation']!==($row['operation']??null)||$data['project_id']!==$project||$data['file_key']!==$meta['file_key']||$data['author_name']!==$meta['author_name']||!is_array($data['data'])||array_keys($data['data'])!==['filename','mime','byte_size','content_hash','active']||!in_array($data['data']['active'],[0,1,'0','1'],true)||(int)$data['data']['active']!==($data['operation']==='upload'?1:0))throw new RuntimeException('issuer_project_file_unconfirmed');foreach(['filename','mime','byte_size','content_hash'] as $f)if((!is_string($data['data'][$f])&&!is_int($data['data'][$f]))||(string)$data['data'][$f]!==(string)$meta[$f])throw new RuntimeException('issuer_project_file_unconfirmed');return $data;
    }
    protected function audit(int $issuer,int $project,string $event,array $data,int $actor=0):void
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$project,gmdate('Y-m-d H:i:s'),$json]);if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[(int)$this->db->insert_id(),$actor,$issuer,$project,$event,$json]))throw new RuntimeException('issuer_project_file_unconfirmed');
    }
}
