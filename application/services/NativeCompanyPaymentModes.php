<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPaymentModesSchema.php';require_once __DIR__.'/NativeCompanyResourceId.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Offline configuration only. A mode never asserts a provider receipt or changes the financial ledger. */
final class NativeCompanyPaymentModes
{
    private const FLAGS=['active','show_on_pdf','selected_by_default','invoices_only','expenses_only'];
    public function __construct(private $db,private string $p){NativeCompanyPaymentModesSchema::definitions($p);}
    public function tableNames():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','company_payment_modes','payment_modes','invoices','invoicepaymentrecords','expenses','company_invoice_drafts'];}
    private function rows(string$sql,array$params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    private function access(int$i,int$a,string$cap='view',?string$usage=null):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if($usage!==null){if(!in_array($usage,['invoices','expenses'],true)||!$acl->can($i,$a,$usage,'view'))throw new DomainException('native_payment_mode_denied');}
        elseif(!$acl->administrative($i,$a)||!$acl->can($i,$a,'settings',$cap))throw new DomainException('native_payment_mode_denied');
        return$acl;
    }
    public static function input(array$input):array
    {
        if(array_diff(array_keys($input),array_merge(['name','description'],self::FLAGS)))throw new DomainException('native_payment_mode_invalid');
        $data=[];foreach(['name'=>100,'description'=>16777215]as$key=>$max){$v=$input[$key]??'';if(!is_string($v)||preg_match('//u',$v)!==1||($key==='name'?mb_strlen($v):strlen($v))>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v)||($key==='name'&&trim($v)===''))throw new DomainException('native_payment_mode_invalid');$data[$key]=$v;}
        foreach(self::FLAGS as$key){$v=$input[$key]??0;if(!in_array($v,[0,1,'0','1',true,false],true))throw new DomainException('native_payment_mode_invalid');$data[$key]=(int)$v;}
        if($data['invoices_only']&&$data['expenses_only'])throw new DomainException('native_payment_mode_invalid');return$data;
    }
    private function dto(array$row,string$kind,int$issuer):array
    {
        $source=IssuerPolicy::id($row['id']);$data=array_intersect_key($row,array_flip(array_merge(['name','description'],self::FLAGS)));
        if($kind==='payment_mode'&&($data['description']??null)===null)$data['description']='';
        foreach(self::FLAGS as$key){if(!in_array((string)($data[$key]??null),['0','1'],true))throw new DomainException('native_payment_mode_corrupt');$data[$key]=(int)$data[$key];}
        if(!is_string($data['name']??null)||!is_string($data['description']??null))throw new DomainException('native_payment_mode_corrupt');
        if($kind==='company_payment_mode'&&((int)($row['revision']??0)<1||self::input($data)!==$data))throw new DomainException('native_payment_mode_corrupt');
        $revision=hash('sha256',json_encode([$issuer,$kind,$row],JSON_THROW_ON_ERROR));
        return $data+['id'=>NativeCompanyResourceId::encode($kind,$source),'source_kind'=>$kind,'source_id'=>$source,'issuer_id'=>$issuer,'revision'=>$revision,'is_online'=>false];
    }
    public function listing(int$i,int$a,?string$usage=null,bool$includeInactive=true):array
    {
        $acl=$this->access($i,$a,'view',$usage);NativeCompanyPaymentModesSchema::assertValid($this->db,$this->p);$primary=$acl->primaryId();$out=[];
        foreach($this->rows('SELECT * FROM '.$this->p.'company_payment_modes WHERE issuer_id=? ORDER BY id',[$i])as$row)$out[]=$this->dto($row,'company_payment_mode',$i);
        if($i===$primary)foreach($this->rows('SELECT * FROM '.$this->p.'payment_modes ORDER BY id')as$row)$out[]=$this->dto($row,'payment_mode',$i);
        $out=array_values(array_filter($out,static fn($r)=>($includeInactive||$r['active']===1)&&($usage!=='invoices'||$r['expenses_only']===0)&&($usage!=='expenses'||$r['invoices_only']===0)));
        usort($out,static fn($x,$y)=>[$x['name'],$x['id']]<=>[$y['name'],$y['id']]);$this->access($i,$a,'view',$usage);if($acl->primaryId()!==$primary)throw new DomainException('native_payment_mode_denied');return$out;
    }
    private function row(int$i,$id):array
    {
        $ref=NativeCompanyResourceId::decode('payment_mode',$id);
        if($ref['source_kind']==='company_payment_mode')$rows=$this->rows('SELECT * FROM '.$this->p.'company_payment_modes WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$ref['source_id']]);
        else{if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$i)throw new DomainException('native_payment_mode_denied');$rows=$this->rows('SELECT * FROM '.$this->p.'payment_modes WHERE id=? FOR UPDATE',[$ref['source_id']]);}
        if(count($rows)!==1)throw new DomainException('native_payment_mode_denied');return[$ref,$rows[0],$this->dto($rows[0],$ref['source_kind'],$i)];
    }
    public function get(int$i,int$a,$id,?string$usage=null,bool$includeInactive=true):array
    {
        $this->access($i,$a,'view',$usage);NativeCompanyPaymentModesSchema::assertValid($this->db,$this->p);[$ref,$row,$dto]=$this->row($i,$id);
        if((!$includeInactive&&!$dto['active'])||($usage==='invoices'&&$dto['expenses_only'])||($usage==='expenses'&&$dto['invoices_only']))throw new DomainException('native_payment_mode_unavailable');
        $this->access($i,$a,'view',$usage);if($this->row($i,$id)[1]!==$row)throw new DomainException('native_payment_mode_changed');return$dto;
    }
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!=='1')throw new RuntimeException('native_payment_mode_write_unconfirmed');
    }
    private function audit(int$i,int$a,string$event,array$ref,string$hash):void
    {
        $stamp=gmdate('Y-m-d H:i:s');$metadata=json_encode(['source_kind'=>$ref['source_kind'],'source_id'=>$ref['source_id'],'hash'=>$hash,'receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$i,$a,$ref['source_id'],$event,$stamp,$metadata]);$id=(int)$this->db->insert_id();$this->assertInserted();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND created_at=? AND metadata_json=?',[$id,$i,$a,$ref['source_id'],$event,$stamp,$metadata]))throw new RuntimeException('native_payment_mode_audit_unconfirmed');
    }
    public function save(int$i,int$a,array$input,$id=null,?string$expected=null):array
    {
        $input=self::input($input);
        return IssuerTransaction::run($this->db,$this->p,$this->tableNames(),function()use($i,$a,$input,$id,$expected){
            $acl=$this->access($i,$a,'edit');$acl->lockFinancialIdentity($i,$a);NativeCompanyPaymentModesSchema::assertValid($this->db,$this->p);
            if($id===null){
                if($expected!==null)throw new DomainException('native_payment_mode_invalid');$fields=$input+['issuer_id'=>$i,'revision'=>1,'created_by'=>$a];
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_payment_modes('.implode(',',array_keys($fields)).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
                $source=(int)$this->db->insert_id();$this->assertInserted();$expectedRevision=1;if($source<1)throw new RuntimeException('native_payment_mode_write_unconfirmed');$id=NativeCompanyResourceId::encode('company_payment_mode',$source);
            }else{
                [$ref,$before,$dto]=$this->row($i,$id);if(!is_string($expected)||!hash_equals($dto['revision'],$expected))throw new DomainException('native_payment_mode_stale');
                $table=$ref['source_kind']==='company_payment_mode'?'company_payment_modes':'payment_modes';$values=$input;$expectedRevision=null;
                if($table==='company_payment_modes'){if((int)$before['revision']>=4294967295)throw new DomainException('native_payment_mode_revision_overflow');$values['revision']=$expectedRevision=(int)$before['revision']+1;}
                $set=implode(',',array_map(static fn($k)=>$k.'=?',array_keys($values)));$args=array_values($values);$args[]=$ref['source_id'];$where='id=?';if($table==='company_payment_modes'){$where.=' AND issuer_id=?';$args[]=$i;}
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.$set.' WHERE '.$where,$args);
            }
            [$ref,$after,$dto]=$this->row($i,$id);if($expectedRevision!==null&&(int)$after['revision']!==$expectedRevision)throw new RuntimeException('native_payment_mode_write_unconfirmed');foreach($input as$key=>$value)if((string)$after[$key]!== (string)$value)throw new RuntimeException('native_payment_mode_write_unconfirmed');
            $this->audit($i,$a,'native_payment_mode_save',$ref,$dto['revision']);$this->access($i,$a,'edit');if($this->row($i,$id)[1]!==$after)throw new RuntimeException('native_payment_mode_write_unconfirmed');return$dto;
        });
    }
    private function assertUnreferenced(int$i,array$ref):void
    {
        $canonical=(string)$ref['canonical_id'];
        // Direct native offline IDs retain their own identity; provider strings are never reinterpreted.
        foreach(['invoicepaymentrecords','expenses']as$table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE paymentmode=? LIMIT 1',[$canonical]))throw new DomainException('native_payment_mode_referenced');
        foreach($this->rows('SELECT allowed_payment_modes FROM '.$this->p.'invoices WHERE issuer_id=?'.((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$i?' OR issuer_id IS NULL':''),[$i])as$row){
            $raw=$row['allowed_payment_modes'];if($raw===null||$raw==='')continue;
            $modes=@unserialize($raw,['allowed_classes'=>false]);if(!is_array($modes))throw new DomainException('native_payment_mode_history_review');
            foreach($modes as$mode){if(!is_string($mode)&&!is_int($mode))throw new DomainException('native_payment_mode_history_review');if((string)$mode===$canonical)throw new DomainException('native_payment_mode_referenced');}
        }
        if($this->db->table_exists($this->p.'company_invoice_drafts'))foreach($this->rows('SELECT payload_json FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=?',[$i])as$row){
            $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload))throw new DomainException('native_payment_mode_history_review');
            foreach($payload['native_payment_modes']??[]as$mode)if((string)$mode===$canonical)throw new DomainException('native_payment_mode_referenced');
        }
    }
    public function delete(int$i,int$a,$id,string$expected):void
    {
        IssuerTransaction::run($this->db,$this->p,$this->tableNames(),function()use($i,$a,$id,$expected){
            $acl=$this->access($i,$a,'edit');$acl->lockFinancialIdentity($i,$a);NativeCompanyPaymentModesSchema::assertValid($this->db,$this->p);[$ref,$before,$dto]=$this->row($i,$id);
            if(!hash_equals($dto['revision'],$expected))throw new DomainException('native_payment_mode_stale');$this->assertUnreferenced($i,$ref);
            $table=$ref['source_kind']==='company_payment_mode'?'company_payment_modes':'payment_modes';$where='id=?';$params=[$ref['source_id']];if($table==='company_payment_modes'){$where.=' AND issuer_id=?';$params[]=$i;}
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$params);
            if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$where,$params))throw new RuntimeException('native_payment_mode_write_unconfirmed');
            $this->audit($i,$a,'native_payment_mode_delete',$ref,$dto['revision']);$this->access($i,$a,'edit');
            if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$where,$params))throw new RuntimeException('native_payment_mode_write_unconfirmed');
        });
    }

}
