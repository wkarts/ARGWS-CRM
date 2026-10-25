<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyFilterSchema.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Persistence for the existing app-filters component; never a SQL expression source. */
final class NativeCompanyFilters
{
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;
        $sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!=='1')throw new RuntimeException('native_source_insert_unconfirmed');
    }

    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private NativeCompanyInvoiceTable|NativeCompanyProjectTable $table)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $this->authorize();
    }
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','issuers_audit','staff','company_native_filters','company_native_filter_defaults','filters','filter_defaults'];}
    private function authorize():void
    {
        // The final table owns its exact native identifier, feature ACL and rule catalog.
        if(!method_exists($this->table,'contextIdentity')||$this->table->contextIdentity()!==['issuer_id'=>$this->issuer,'actor_id'=>$this->actor])throw new DomainException('native_filter_denied');
        $contract=match(get_class($this->table)){
            NativeCompanyInvoiceTable::class=>['feature'=>'invoices','view'=>'invoices','ids'=>['invoices','project_invoices']],
            NativeCompanyProjectTable::class=>['feature'=>'projects','view'=>'projects','ids'=>['projects']],
            default=>throw new DomainException('native_filter_denied'),
        };
        if(!in_array($this->table->id(),$contract['ids'],true)||$this->table->viewName()!==$contract['view']
            ||!(new IssuerResourceAccess($this->db,$this->p))->can($this->issuer,$this->actor,$contract['feature'],'view'))throw new DomainException('native_filter_denied');
    }
    private function normalize(array $builder):array
    {
        if(!method_exists($this->table,'normalizeBuilder'))throw new DomainException('native_filter_rules_unavailable');
        return$this->table->normalizeBuilder($builder);
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function transaction(callable $work)
    {
        $this->authorize();NativeCompanyFilterSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($work){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($this->issuer,$this->actor);$this->authorize();$result=$work();$this->authorize();return$result;});
    }
    private function primary():bool{return(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$this->issuer;}
    private function dto(array $row,string $source):array
    {
        $builder=json_decode($row[$source==='filter'?'builder':'builder_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($builder))throw new DomainException('native_filter_invalid');$builder=$this->normalize($builder);
        return ['id'=>NativeCompanyResourceId::encode($source,$row['id']),'issuer_id'=>$this->issuer,'staff_id'=>(int)$row['staff_id'],'identifier'=>$row['identifier'],'name'=>$row['name'],'is_shared'=>(int)$row['is_shared'],'builder'=>$builder,'revision'=>$source==='filter'?hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)):(string)$row['revision']];
    }
    public function listing():array
    {
        return$this->transaction(function(){
            $id=$this->table->id();$view=$this->table->viewName();$out=[];
            foreach($this->rows('SELECT * FROM '.$this->p.'company_native_filters WHERE issuer_id=? AND identifier=? AND view_name=? AND (staff_id=? OR is_shared=1) ORDER BY id',[$this->issuer,$id,$view,$this->actor])as$r)$out[]=$this->dto($r,'company_filter');
            if($this->primary())foreach($this->rows('SELECT * FROM '.$this->p.'filters WHERE identifier=? AND (staff_id=? OR is_shared=1) ORDER BY id',[$id,$this->actor])as$r)$out[]=$this->dto($r,'filter');
            $default=$this->rows('SELECT source_kind,filter_id FROM '.$this->p.'company_native_filter_defaults WHERE issuer_id=? AND staff_id=? AND identifier=? AND view_name=?',[$this->issuer,$this->actor,$id,$view])[0]??null;
            if(!$default&&$this->primary()){$r=$this->rows('SELECT filter_id FROM '.$this->p.'filter_defaults WHERE staff_id=? AND identifier=? AND view=?',[$this->actor,$id,$view]);if(count($r)>1)throw new DomainException('native_filter_default_ambiguous');if($r)$default=['source_kind'=>'filter','filter_id'=>$r[0]['filter_id']];}
            $defaultId=$default&&$default['source_kind']!=='none'?NativeCompanyResourceId::encode($default['source_kind'],$default['filter_id']):null;
            foreach($out as&$row)$row['is_default']=$row['id']===$defaultId;unset($row);return$out;
        });
    }
    private function row($id,bool $mutate=false):array
    {
        $ref=NativeCompanyResourceId::decode('filter',$id);$identifier=$this->table->id();
        if($ref['source_kind']==='filter'){
            if(!$this->primary())throw new DomainException('native_filter_denied');
            $r=$this->rows('SELECT * FROM '.$this->p.'filters WHERE id=? AND identifier=? FOR UPDATE',[$ref['source_id'],$identifier])[0]??null;
        }else{$r=$this->rows('SELECT * FROM '.$this->p.'company_native_filters WHERE issuer_id=? AND id=? AND identifier=? AND view_name=? FOR UPDATE',[$this->issuer,$ref['source_id'],$identifier,$this->table->viewName()])[0]??null;}
        if(!$r||((int)$r['staff_id']!==$this->actor&&($mutate?!(new IssuerResourceAccess($this->db,$this->p))->administrative($this->issuer,$this->actor):(int)$r['is_shared']!==1)))throw new DomainException('native_filter_denied');
        return[$ref,$r,$this->dto($r,$ref['source_kind'])];
    }
    private function audit(string $event,int $id):void
    {
        $at=gmdate('Y-m-d H:i:s');$receipt=json_encode(['receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$this->issuer,$this->actor,$id,$event,$at,$receipt]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND created_at=? AND metadata_json=?',[$audit,$this->issuer,$this->actor,$id,$event,$at,$receipt]))throw new RuntimeException('native_filter_audit_unconfirmed');
    }
    public function save(array $input,$id=null,?string $revision=null,?bool $isDefault=null):array
    {
        if(array_diff(array_keys($input),['name','is_shared','builder'])||!is_string($input['name']??null)||trim($input['name'])===''||strlen($input['name'])>191||preg_match('//u',$input['name'])!==1||preg_match('/[\x00-\x1f\x7f]/',$input['name'])||!in_array($input['is_shared']??null,[0,1,'0','1'],true)||!is_array($input['builder']??null))throw new DomainException('native_filter_invalid');
        $builder=$this->normalize($input['builder']);$json=json_encode($builder,JSON_THROW_ON_ERROR);if(strlen($json)>65535)throw new DomainException('native_filter_invalid');
        return$this->transaction(function()use($input,$id,$revision,$json,$isDefault){
            $expectedRevision=null;if($id===null){$expectedRevision=1;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_filters(issuer_id,staff_id,identifier,view_name,name,is_shared,builder_json,revision) VALUES(?,?,?,?,?,?,?,1)',[$this->issuer,$this->actor,$this->table->id(),$this->table->viewName(),$input['name'],(int)$input['is_shared'],$json]);$this->assertInserted();$id=NativeCompanyResourceId::encode('company_filter',$this->db->insert_id());}
            else{[$ref,$old,$dto]=$this->row($id,true);if($revision===null||!hash_equals($dto['revision'],$revision))throw new DomainException('native_filter_stale');
                if($ref['source_kind']==='filter')IssuerStorage::query($this->db,'UPDATE '.$this->p.'filters SET name=?,is_shared=?,builder=? WHERE id=?',[$input['name'],(int)$input['is_shared'],$json,$ref['source_id']]);
                else{$expectedRevision=(int)$old['revision']+1;if((int)$old['revision']>=2147483647)throw new DomainException('native_filter_revision_overflow');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_filters SET name=?,is_shared=?,builder_json=?,revision=revision+1 WHERE issuer_id=? AND id=? AND revision=?',[$input['name'],(int)$input['is_shared'],$json,$this->issuer,$ref['source_id'],$old['revision']]);}
            }
            [$ref,$row,$dto]=$this->row($id,true);if(($expectedRevision!==null&&(int)$row['revision']!==$expectedRevision)||$row['name']!==$input['name']||(int)$row['is_shared']!==(int)$input['is_shared']||$row[$ref['source_kind']==='filter'?'builder':'builder_json']!==$json)throw new RuntimeException('native_filter_write_unconfirmed');$this->audit('native_filter_saved',$ref['source_id']);
            if($isDefault===true)$this->writeDefault($id);
            elseif($isDefault===false){$current=$this->currentDefault();if($current&&$current['source_kind']===$ref['source_kind']&&(int)$current['filter_id']===$ref['source_id'])$this->writeDefault(null);}
            $current=$this->currentDefault();$dto['is_default']=$current&&$current['source_kind']===$ref['source_kind']&&(int)$current['filter_id']===$ref['source_id'];return$dto;
        });
    }
    public function delete($id,string $revision):void
    {
        $this->transaction(function()use($id,$revision){[$ref,$row,$dto]=$this->row($id,true);if(!hash_equals($dto['revision'],$revision))throw new DomainException('native_filter_stale');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_filter_defaults SET source_kind=?,filter_id=0 WHERE issuer_id=? AND source_kind=? AND filter_id=?',['none',$this->issuer,$ref['source_kind'],$ref['source_id']]);
            if($ref['source_kind']==='filter'){
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'filter_defaults WHERE filter_id=?',[$ref['source_id']]);
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'filters WHERE id=?',[$ref['source_id']]);
                $remaining=$this->rows('SELECT id FROM '.$this->p.'filters WHERE id=?',[$ref['source_id']]);
            }else{
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_native_filters WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$ref['source_id'],$row['revision']]);
                $remaining=$this->rows('SELECT id FROM '.$this->p.'company_native_filters WHERE issuer_id=? AND id=?',[$this->issuer,$ref['source_id']]);
            }
            if($remaining)throw new RuntimeException('native_filter_write_unconfirmed');$this->audit('native_filter_deleted',$ref['source_id']);
        });
    }
    private function currentDefault():?array
    {
        $args=[$this->issuer,$this->actor,$this->table->id(),$this->table->viewName()];
        $row=$this->rows('SELECT source_kind,filter_id FROM '.$this->p.'company_native_filter_defaults WHERE issuer_id=? AND staff_id=? AND identifier=? AND view_name=?',$args)[0]??null;
        if(!$row&&$this->primary()){$legacy=$this->rows('SELECT filter_id FROM '.$this->p.'filter_defaults WHERE staff_id=? AND identifier=? AND view=?',array_slice($args,1));if(count($legacy)>1)throw new DomainException('native_filter_default_ambiguous');if($legacy)$row=['source_kind'=>'filter','filter_id'=>$legacy[0]['filter_id']];}
        return$row;
    }
    private function writeDefault($id):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $args=[$this->issuer,$this->actor,$this->table->id(),$this->table->viewName()];$ref=$id===null?null:$this->row($id)[0];
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_native_filter_defaults WHERE issuer_id=? AND staff_id=? AND identifier=? AND view_name=?',$args);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_filter_defaults(issuer_id,staff_id,identifier,view_name,source_kind,filter_id) VALUES(?,?,?,?,?,?)',array_merge($args,[$ref['source_kind']??'none',$ref['source_id']??0]));
        $r=$this->rows('SELECT source_kind,filter_id FROM '.$this->p.'company_native_filter_defaults WHERE issuer_id=? AND staff_id=? AND identifier=? AND view_name=?',$args)[0]??null;if(!$r||$r['source_kind']!==($ref['source_kind']??'none')||(int)$r['filter_id']!==($ref['source_id']??0))throw new RuntimeException('native_filter_write_unconfirmed');$this->audit('native_filter_default',$ref['source_id']??0);
    }
    public function default($id):void{$this->transaction(fn()=>$this->writeDefault($id));}
}
