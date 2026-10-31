<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceTags.php';
/** Original Settings tag actions. A catalogue grant never grants access to linked records. */
final class NativeCompanyTagAdministration
{
    private const TYPES=['projects'=>'project','tasks'=>'task','tickets'=>'ticket','invoices'=>'invoice','customers'=>'client','contracts'=>'contract','leads'=>'lead','estimates'=>'estimate','proposals'=>'proposal','expenses'=>'expense','credit_notes'=>'credit_note'];
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private function authorize(int $i,int $a,string $cap):IssuerResourceAccess{
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($i,$a,'settings','view')||!$acl->can($i,$a,'settings',$cap))throw new DomainException('native_tag_denied');return $acl;
    }
    private function state(int $i,string $id):array{
        $ref=NativeCompanyResourceId::decode('work_tag',$id);$owned=$ref['source_kind']==='company_work_tag';
        if(!$owned&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$i)throw new DomainException('native_tag_denied');
        $row=$this->rows('SELECT * FROM '.$this->p.($owned?'company_work_tags WHERE issuer_id=? AND id=?':'tags WHERE id=?'),$owned?[$i,$ref['source_id']]:[$ref['source_id']])[0]??null;
        if(!$row)throw new DomainException('native_tag_denied');
        $links=$this->rows('SELECT * FROM '.$this->p.($owned?'company_work_tag_links WHERE issuer_id=? AND tag_id=? ORDER BY source_kind,source_id':'taggables WHERE tag_id=? ORDER BY rel_type,rel_id'),$owned?[$i,$ref['source_id']]:[$ref['source_id']]);
        if(count($links)>10000)throw new DomainException('native_tag_limit');return ['source'=>$ref,'row'=>$row,'links'=>$links];
    }
    private function references(int $i,int $a,array $state,string $cap):array{
        $owned=$state['source']['source_kind']==='company_work_tag';$out=[];
        if($owned){$feature=$state['row']['feature'];if(!isset(self::TYPES[$feature])||!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,$feature,$cap))throw new DomainException('native_tag_denied');}
        foreach($state['links']as$link){
            $type=$owned?self::TYPES[$state['row']['feature']]:($link['rel_type']==='customer'?'client':$link['rel_type']);
            $canonical=$owned?NativeCompanyResourceId::encode($link['source_kind'],$link['source_id']):(string)$link['rel_id'];
            $out[]=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$canonical,$cap);
        }return $out;
    }
    public function listing(int $i,int $a):array{
        $acl=$this->authorize($i,$a,'view');$out=[];
        foreach((new NativeCompanyWorkTags($this->db,$this->p))->catalog($i,$a,false)['tags']as$tag){
            $state=$this->state($i,$tag['id']);$writable=true;
            try{$refs=$this->references($i,$a,$state,'edit');}catch(DomainException $e){$writable=false;$refs=[];}
            if($this->state($i,$tag['id'])!==$state)throw new DomainException('native_tag_stale');
            $out[]=$tag+['expected'=>hash('sha256',self::json($state)),'count'=>count($state['links']),'can_edit'=>$writable&&$acl->can($i,$a,'settings','edit'),'can_delete'=>$writable&&$acl->can($i,$a,'settings','delete')];
        }$this->authorize($i,$a,'view');return $out;
    }
    public function change(int $i,int $a,string $id,string $expected,string $key,?string $name):void{
        $ref=NativeCompanyResourceId::decode('work_tag',$id);
        if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_tag_invalid');
        if($name!==null){$name=trim($name);if($name===''||mb_strlen($name)>100||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f]/',$name))throw new DomainException('native_tag_invalid');}
        $cap=$name===null?'delete':'edit';$hash=hash('sha256',self::json([$id,$expected,$name]));$tables=NativeCompanyResourceTags::tableNames();
        foreach(array_values(self::TYPES)as$type){if($type==='ticket'&&!$this->db->table_exists($this->p.'company_tickets'))continue;$tables=array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,$type));}
        $tables=array_values(array_unique(array_filter($tables,fn($table)=>$this->db->table_exists($this->p.$table))));
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($i,$a,$id,$ref,$expected,$key,$name,$cap,$hash){
            $acl=$this->authorize($i,$a,$cap);$acl->lockFinancialIdentity($i,$a);NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);
            $receipts=$this->rows('SELECT actor_id,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND event=? AND metadata_json LIKE ?',[$i,'native_tag_command','%"request_key":"'.$key.'"%']);
            if($receipts){if(count($receipts)!==1)throw new DomainException('native_tag_conflict');$receipt=$receipts[0];$metadata=json_decode($receipt['metadata_json'],true,64,JSON_THROW_ON_ERROR);if((int)$receipt['actor_id']!==$a||($metadata['request_key']??null)!==$key||($metadata['input_hash']??null)!==$hash)throw new DomainException('native_tag_conflict');$this->references($i,$a,$metadata['before'],'edit');$this->authorize($i,$a,$cap);return;}
            $before=$this->state($i,$id);if(!hash_equals($expected,hash('sha256',self::json($before))))throw new DomainException('native_tag_stale');$proof=$this->references($i,$a,$before,'edit');$owned=$ref['source_kind']==='company_work_tag';$source=$ref['source_id'];
            if($name===null){
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.($owned?'company_work_tag_links WHERE issuer_id=? AND tag_id=?':'taggables WHERE tag_id=?'),$owned?[$i,$source]:[$source]);
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.($owned?'company_work_tags WHERE issuer_id=? AND id=?':'tags WHERE id=?'),$owned?[$i,$source]:[$source]);
                if($this->rows('SELECT id FROM '.$this->p.($owned?'company_work_tags WHERE issuer_id=? AND id=?':'tags WHERE id=?'),$owned?[$i,$source]:[$source])||$this->rows('SELECT tag_id FROM '.$this->p.($owned?'company_work_tag_links WHERE issuer_id=? AND tag_id=?':'taggables WHERE tag_id=?'),$owned?[$i,$source]:[$source]))throw new RuntimeException('native_tag_write_unconfirmed');
            }elseif($owned){
                if((int)$before['row']['revision']<1||(int)$before['row']['revision']>=2147483647)throw new DomainException('native_tag_revision_invalid');
                $labelHash=hash('sha256',mb_strtolower($name,'UTF-8'));
                if($this->rows('SELECT id FROM '.$this->p.'company_work_tags WHERE issuer_id=? AND feature=? AND label_hash=? AND id<>?',[$i,$before['row']['feature'],$labelHash,$source]))throw new DomainException('native_tag_conflict');
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_work_tags SET label=?,label_hash=?,revision=revision+1 WHERE issuer_id=? AND id=? AND revision=?',[$name,$labelHash,$i,$source,$before['row']['revision']]);
            }else{
                if($this->rows('SELECT id FROM '.$this->p.'tags WHERE name=? AND id<>?',[$name,$source]))throw new DomainException('native_tag_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->p.'tags SET name=? WHERE id=?',[$name,$source]);
            }
            if($name!==null){$after=$this->state($i,$id);if(($after['row'][$owned?'label':'name']??null)!==$name||$after['links']!==$before['links']||$owned&&(int)$after['row']['revision']!==(int)$before['row']['revision']+1)throw new RuntimeException('native_tag_write_unconfirmed');}
            $metadata=self::json(['request_key'=>$key,'input_hash'=>$hash,'before'=>$before,'name'=>$name]);$now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'native_tag_command',$i,$source,$now,$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND event=? AND metadata_json=?',[$audit,$i,$a,'native_tag_command',$metadata])||$this->references($i,$a,$before,'edit')!==$proof)throw new RuntimeException('native_tag_write_unconfirmed');$this->authorize($i,$a,$cap);
            if($name!==null&&$this->state($i,$id)!==$after)throw new RuntimeException('native_tag_write_unconfirmed');
            if($name===null&&($this->rows('SELECT id FROM '.$this->p.($owned?'company_work_tags WHERE issuer_id=? AND id=?':'tags WHERE id=?'),$owned?[$i,$source]:[$source])||$this->rows('SELECT tag_id FROM '.$this->p.($owned?'company_work_tag_links WHERE issuer_id=? AND tag_id=?':'taggables WHERE tag_id=?'),$owned?[$i,$source]:[$source])))throw new RuntimeException('native_tag_write_unconfirmed');
        });
    }
}
