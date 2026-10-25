<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCatalogSchema.php';
require_once __DIR__.'/NativeCompanyTicketVisibility.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
final class NativeCompanyTicketCatalog
{
    private const TYPES=[
        'status'=>['ticket_status','tickets_status','ticketstatusid'],
        'priority'=>['ticket_priority','tickets_priorities','priorityid'],
        'service'=>['ticket_service','services','serviceid'],
        'predefined_reply'=>['ticket_predefined_reply','tickets_predefined_replies','id'],
    ];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $values=[]):array{return IssuerStorage::query($this->db,$sql,$values)->result_array();}
    private static function type(string $kind):array{return self::TYPES[$kind]??throw new DomainException('native_ticket_catalog_kind_invalid');}
    private function access(int $issuer,int $actor,bool $write=false):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($issuer,$actor)||($write?!$acl->administrative($issuer,$actor):(!$acl->administrative($issuer,$actor)&&!$acl->can($issuer,$actor,'tickets','view'))))throw new DomainException('native_ticket_catalog_denied');return$acl;
    }
    private function project(string $kind,array $row,bool $owned):array
    {
        [$type,,$key]=self::type($kind);$source=$owned?'company_'.$type:$type;$id=(int)$row[$owned?'id':$key];$snapshot=$row;ksort($snapshot);
        $out=[$key=>NativeCompanyResourceId::encode($source,$id),'native_id'=>NativeCompanyResourceId::encode($source,$id),'source_kind'=>$source,'source_id'=>$id,'name'=>$row['name'],'revision_token'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
        if($owned){$out['code']=$row['code'];$out['source_revision']=(int)$row['revision'];if($out['source_revision']<1)throw new DomainException('native_ticket_catalog_revision_invalid');}
        if($kind==='status')$out+=['isdefault'=>(int)$row[$owned?'is_default':'isdefault'],'statuscolor'=>$row[$owned?'color':'statuscolor'],'statusorder'=>(int)$row[$owned?'sort_order':'statusorder']];
        if($kind==='predefined_reply')$out['message']=NativeCompanyContentHtml::purify((string)$row['message']);
        return$out;
    }
    public function list(int $issuer,int $actor,string $kind):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();[$type,$table,$key]=self::type($kind);$out=[];
        foreach($this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? ORDER BY sort_order,id',[$issuer,$kind])as$row)$out[]=$this->project($kind,$row,true);
        if($primary===$issuer)foreach($this->rows('SELECT * FROM '.$this->p.$table.' ORDER BY '.$key)as$row)$out[]=$this->project($kind,$row,false);
        $this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('native_ticket_catalog_denied');return$out;
    }
    public function get(int $issuer,int $actor,string $kind,$id):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();[$type,$table,$key]=self::type($kind);$d=NativeCompanyResourceId::decode($type,$id);$owned=$d['source_kind']==='company_'.$type;
        if(!$owned&&$primary!==$issuer)throw new DomainException('native_ticket_catalog_denied');
        $row=$owned?($this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND id=?',[$issuer,$kind,$d['source_id']])[0]??null):($this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$key.'=?',[$d['source_id']])[0]??null);
        if(!$row)throw new DomainException('native_ticket_catalog_denied');$out=$this->project($kind,$row,$owned);$this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('native_ticket_catalog_denied');return$out;
    }
    /** Actual catalog counts; historical owned text is retained, never equated to an arbitrary native status. */
    public function statusCounts(int $issuer,int $actor):array
    {
        $acl=$this->access($issuer,$actor,true);$primary=$acl->primaryId();$counts=[];$unclassified=[];
        foreach($this->list($issuer,$actor,'status')as$status)$counts[$status['native_id']]=0;
        $deleted=[];if(NativeCompanyTicketVisibility::enabled($this->db,$this->p))foreach($this->rows('SELECT ticket_id FROM '.$this->p.NativeCompanyTicketLifecycleSchema::TICKETS.' WHERE issuer_id=?',[$issuer])as$r)$deleted[(int)$r['ticket_id']]=true;
        $owned=$this->rows('SELECT id,status FROM '.$this->p.'company_tickets WHERE issuer_id=?',[$issuer]);
        $refs=$this->rows('SELECT ticket_id,source_kind,source_id FROM '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' WHERE issuer_id=? AND kind=?',[$issuer,'status']);$byId=[];
        foreach($refs as$ref){if(isset($byId[$ref['ticket_id']]))throw new DomainException('native_ticket_catalog_reference_invalid');$id=NativeCompanyResourceId::encode($ref['source_kind'],$ref['source_id']);$this->get($issuer,$actor,'status',$id);$byId[$ref['ticket_id']]=$id;}
        foreach($owned as$ticket){if(isset($deleted[(int)$ticket['id']])){unset($byId[$ticket['id']]);continue;}if(isset($byId[$ticket['id']])){$counts[$byId[$ticket['id']]]++;unset($byId[$ticket['id']]);}else{if(!in_array($ticket['status'],['open','resolved','closed'],true))throw new DomainException('native_ticket_catalog_reference_invalid');$unclassified[$ticket['status']]=($unclassified[$ticket['status']]??0)+1;}}
        if($byId)throw new DomainException('native_ticket_catalog_reference_invalid');
        if($primary===$issuer){
            require_once __DIR__.'/CustomerRelationBoundary.php';$boundary=\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'tickets','t');
            foreach($this->rows('SELECT t.ticketid,t.status FROM '.$this->p.'tickets t WHERE '.$boundary)as$ticket){
                $ref=$this->rows('SELECT source_kind,source_id FROM '.$this->p.NativeCompanyTicketCatalogSchema::LEGACY_REFERENCES.' WHERE issuer_id=? AND ticket_id=? AND kind=?',[$issuer,$ticket['ticketid'],'status']);if(count($ref)>1)throw new DomainException('native_ticket_catalog_reference_invalid');
                $id=$ref?NativeCompanyResourceId::encode($ref[0]['source_kind'],$ref[0]['source_id']):NativeCompanyResourceId::encode('ticket_status',$ticket['status']);$this->get($issuer,$actor,'status',$id);if(!array_key_exists($id,$counts))throw new DomainException('native_ticket_catalog_reference_invalid');$counts[$id]++;
            }
        }
        $this->access($issuer,$actor,true);if($acl->primaryId()!==$primary)throw new DomainException('native_ticket_catalog_denied');return['counts'=>$counts,'historical_owned'=>$unclassified];
    }
    /** Product-public defaults only. Called by migration/creation inside their declared transaction, never GET. */
    public static function defaults():array
    {
        return [
            ['status','open','Open','#ff2d42',1],['status','in_progress','In progress','#22c55e',2],['status','answered','Answered','#2563eb',3],['status','on_hold','On Hold','#64748b',4],['status','closed','Closed','#03a9f4',5],
            ['priority','low','Low','',1],['priority','medium','Medium','',2],['priority','high','High','',3],
        ];
    }
    public static function initializeInOwnedTransaction($db,string $p,int $issuer,?int $actor):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||$issuer<1||($actor!==null&&$actor<1))throw new DomainException('native_ticket_catalog_input_invalid');
        IssuerTransaction::assertOwned($db,$p,['issuers_registry','issuers_installation_identity',NativeCompanyTicketCatalogSchema::CATALOG]);
        $row=IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_registry WHERE id=? FOR UPDATE',[$issuer])->row_array();if(!$row)throw new DomainException('native_ticket_catalog_denied');
        if($actor!==null&&!(new IssuerResourceAccess($db,$p))->isMaster($actor)&&!(new IssuerResourceAccess($db,$p))->member($issuer,$actor))throw new DomainException('native_ticket_catalog_denied');
        if((new IssuerResourceAccess($db,$p))->primaryId()===$issuer)return; // Proven principal keeps its original catalog, not a duplicate public seed.
        foreach(self::defaults()as[$kind,$code,$name,$color,$order]){
            $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND code=?',[$issuer,$kind,$code])->result_array();
            if($rows){if(count($rows)!==1||(int)$rows[0]['is_default']!==($kind==='status'?1:0))throw new DomainException('native_ticket_catalog_defaults_conflict');continue;}
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($db,'INSERT INTO '.$p.NativeCompanyTicketCatalogSchema::CATALOG.' (issuer_id,kind,code,name,message,color,sort_order,is_default,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?,?)',[$issuer,$kind,$code,$name,'',$color,$order,$kind==='status'?1:0,$actor,$now,$now]);
            $saved=IssuerStorage::query($db,'SELECT name,color,sort_order,is_default FROM '.$p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND code=?',[$issuer,$kind,$code])->row_array();if(!$saved||$saved['name']!==$name||$saved['color']!==$color||(int)$saved['sort_order']!==$order||(int)$saved['is_default']!==($kind==='status'?1:0))throw new RuntimeException('native_ticket_catalog_write_unconfirmed');
        }
    }
    private static function form(string $kind,array $input):array
    {
        self::type($kind);$allowed=$kind==='status'?['name','statuscolor','statusorder']:($kind==='predefined_reply'?['name','message']:['name']);if(array_diff(array_keys($input),$allowed))throw new DomainException('native_ticket_catalog_input_invalid');$name=$input['name']??null;
        if(!is_string($name)||trim($name)===''||strlen($name)>($kind==='predefined_reply'?191:50)||!preg_match('//u',$name)||preg_match('/[\x00-\x1f\x7f]/',$name))throw new DomainException('native_ticket_catalog_input_invalid');$out=['name'=>$name];
        if($kind==='status'){$color=$input['statuscolor']??null;$order=$input['statusorder']??null;if(!is_string($color)||!preg_match('/^#[a-fA-F0-9]{6}$/D',$color)||(!is_string($order)&&!is_int($order))||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)$order)||(int)$order>2147483647)throw new DomainException('native_ticket_catalog_input_invalid');$out+=['statuscolor'=>$color,'statusorder'=>(int)$order];}
        if($kind==='predefined_reply'){$body=$input['message']??null;if(!is_string($body)||strlen($body)>60000||trim($body)===''||!preg_match('//u',$body))throw new DomainException('native_ticket_catalog_input_invalid');$out['message']=NativeCompanyContentHtml::purify($body);}
        return$out;
    }
    private function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','staff','issuers_installation_identity','issuers_resource_permissions','issuers_audit'],array_keys(NativeCompanyTicketCatalogSchema::definitions($this->p)));}
    public function save(int $issuer,int $actor,string $kind,array $input,$id=null,?string $revision=null):array
    {
        $acl=$this->access($issuer,$actor,true);$data=self::form($kind,$input);[$type,$legacy,$key]=self::type($kind);$tables=$this->tables();if($acl->primaryId()===$issuer)$tables[]=$legacy;
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$kind,$data,$id,$revision,$type,$legacy,$key){
            $acl=$this->access($issuer,$actor,true);$acl->lockFinancialIdentity($issuer,$actor);$old=$id===null?null:$this->get($issuer,$actor,$kind,$id);if($old!==null&&(!is_string($revision)||!hash_equals($old['revision_token'],$revision)))throw new DomainException('native_ticket_catalog_stale');
            $owned=$old===null||$old['source_kind']==='company_'.$type;$now=gmdate('Y-m-d H:i:s');$source=$old['source_id']??null;
            if($owned){$write=['name'=>$data['name'],'message'=>$data['message']??'','color'=>$data['statuscolor']??'','sort_order'=>$data['statusorder']??0,'actor_id'=>$actor,'updated_at'=>$now];
                if($old===null){$write+=['issuer_id'=>$issuer,'kind'=>$kind,'code'=>'custom:'.bin2hex(random_bytes(16)),'is_default'=>0,'revision'=>1,'created_at'=>$now];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' ('.implode(',',array_keys($write)).') VALUES('.implode(',',array_fill(0,count($write),'?')).')',array_values($write));$source=(int)$this->db->insert_id();$id=NativeCompanyResourceId::encode('company_'.$type,$source);}
                else IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' SET '.implode(',',array_map(static fn($k)=>$k.'=?',array_keys($write))).',revision=revision+1 WHERE issuer_id=? AND kind=? AND id=?',array_merge(array_values($write),[$issuer,$kind,$source]));
            }else IssuerStorage::query($this->db,'UPDATE '.$this->p.$legacy.' SET '.implode(',',array_map(static fn($k)=>$k.'=?',array_keys($data))).' WHERE '.$key.'=?',array_merge(array_values($data),[$source]));
            $record=$this->get($issuer,$actor,$kind,$id);if($owned&&$record['source_revision']!==($old===null?1:$old['source_revision']+1))throw new RuntimeException('native_ticket_catalog_write_unconfirmed');foreach($data as$k=>$v)if((string)$record[$k]!== (string)$v)throw new RuntimeException('native_ticket_catalog_write_unconfirmed');
            $this->audit($issuer,$actor,'native_ticket_catalog_'.$kind.'_saved',$source);$this->access($issuer,$actor,true);return$record;
        });
    }
    private function audit(int $issuer,int $actor,string $event,int $source):void
    {
        $before=(int)($this->rows('SELECT MAX(id) AS last_id FROM '.$this->p.'issuers_audit WHERE issuer_id=?',[$issuer])[0]['last_id']??0);
        $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$source,$now]);$id=(int)$this->db->insert_id();
        if($id<=$before||count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id=? AND created_at=?',[$id,$actor,$event,$issuer,$source,$now]))!==1)throw new RuntimeException('native_ticket_catalog_write_unconfirmed');
    }
    public function delete(int $issuer,int $actor,string $kind,$id,string $revision):void
    {
        $acl=$this->access($issuer,$actor,true);[$type,$legacy,$key]=self::type($kind);$tables=$this->tables();if($acl->primaryId()===$issuer)$tables=array_merge($tables,[$legacy,'tickets','options']);
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$kind,$id,$revision,$type,$legacy,$key){
            $acl=$this->access($issuer,$actor,true);$acl->lockFinancialIdentity($issuer,$actor);NativeCompanyTicketCatalogSchema::assertValid($this->db,$this->p);$row=$this->get($issuer,$actor,$kind,$id);
            if(!hash_equals($row['revision_token'],$revision))throw new DomainException('native_ticket_catalog_stale');
            if($kind==='status'&&$row['isdefault']===1)throw new DomainException('native_ticket_catalog_default');
            require_once __DIR__.'/NativeCompanyTicketPreferences.php';
            (new NativeCompanyTicketPreferences($this->db,$this->p))->assertCatalogNotSelected($issuer,$kind,$row);
            foreach([NativeCompanyTicketCatalogSchema::REFERENCES,NativeCompanyTicketCatalogSchema::LEGACY_REFERENCES]as$table)if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND kind=? AND source_kind=? AND source_id=? LIMIT 1',[$issuer,$kind,$row['source_kind'],$row['source_id']]))throw new DomainException('native_ticket_catalog_referenced');
            $owned=$row['source_kind']==='company_'.$type;
            if(!$owned&&$kind!=='predefined_reply'&&$this->rows('SELECT ticketid FROM '.$this->p.'tickets WHERE '.($kind==='service'?'service':$kind).'=? LIMIT 1',[$row['source_id']]))throw new DomainException('native_ticket_catalog_referenced');
            $table=$owned?NativeCompanyTicketCatalogSchema::CATALOG:$legacy;$where=$owned?'issuer_id=? AND kind=? AND id=?':$key.'=?';$args=$owned?[$issuer,$kind,$row['source_id']]:[$row['source_id']];
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$args);if($this->rows('SELECT '.($owned?'id':$key).' FROM '.$this->p.$table.' WHERE '.$where,$args))throw new RuntimeException('native_ticket_catalog_write_unconfirmed');
            $this->audit($issuer,$actor,'native_ticket_catalog_'.$kind.'_deleted',$row['source_id']);$this->access($issuer,$actor,true);
        });
    }

}
