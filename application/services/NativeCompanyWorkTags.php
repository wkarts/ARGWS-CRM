<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyWorkTagsSchema.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';

/** Native tag values are catalogued per issuer and projected only through a live resource grant. */
final class NativeCompanyWorkTags
{
    private const FEATURES=['projects','tasks','tickets','invoices','customers','contracts','leads','estimates','proposals','expenses','credit_notes'];
    private const TYPES=['projects'=>'project','tasks'=>'task','tickets'=>'ticket','invoices'=>'invoice','customers'=>'client','contracts'=>'contract','leads'=>'lead','estimates'=>'estimate','proposals'=>'proposal','expenses'=>'expense','credit_notes'=>'credit_note'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    private function permissions(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($issuer,$actor))throw new DomainException('native_tags_denied');$out=[];foreach(self::FEATURES as$f)$out[$f]=$acl->can($issuer,$actor,$f,'view');$out['catalog']=$acl->can($issuer,$actor,'settings','view')||$acl->administrative($issuer,$actor);$out['primary']=$acl->primaryId()===$issuer;return $out;
    }
    private function resolve(int $issuer,int $actor,string $feature,string $source,int $id,string $cap='view'):array
    {
        if(!isset(self::TYPES[$feature]))throw new DomainException('native_tags_reference_denied');$type=self::TYPES[$feature];
        if($source==='company_task'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'projects','view'))throw new DomainException('native_tags_reference_denied');
        $canonical=str_starts_with($source,'company_')?NativeCompanyResourceId::encode($source,$id):(string)$id;
        $row=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$type,$canonical,$cap);
        if($row['source_kind']!==$source)throw new DomainException('native_tags_reference_denied');return $row;
    }
    private static function label($label):string
    {
        if(!is_string($label)||preg_match('//u',$label)!==1||preg_match('/[\x00-\x1f\x7f]/',$label)||mb_strlen($label)>100||trim($label)==='')throw new DomainException('native_tags_invalid');return trim($label);
    }
    /** Header suggestion data; no global tag query is made for a secondary company. */
    public function catalog($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$permission=$this->permissions($issuer,$actor);NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);
        $sql='SELECT * FROM '.$this->p.'company_work_tags WHERE issuer_id=? ORDER BY id LIMIT 1001';$owned=$this->rows($sql,[$issuer]);if(count($owned)>1000)throw new DomainException('native_tags_limit');$out=[];$proofs=[];$linkProofs=[];
        $ownedLinksSql='SELECT tag_id,source_kind,source_id FROM '.$this->p.'company_work_tag_links WHERE issuer_id=? ORDER BY tag_id,source_kind,source_id LIMIT 10001';$ownedLinks=$this->rows($ownedLinksSql,[$issuer]);if(count($ownedLinks)>10000)throw new DomainException('native_tags_limit');$linksByTag=[];foreach($ownedLinks as$link)$linksByTag[(string)$link['tag_id']][]=$link;$linkProofs[]=[$ownedLinksSql,[$issuer],$ownedLinks];
        foreach($owned as$tag){$feature=$tag['feature'];if(!in_array($feature,self::FEATURES,true)||self::label($tag['label'])!==$tag['label']||!hash_equals(hash('sha256',mb_strtolower($tag['label'],'UTF-8')),(string)$tag['label_hash'])||(int)$tag['revision']<1||(int)$tag['id']<1)throw new DomainException('native_tags_invalid');if(!isset($permission[$feature])||!$permission[$feature])continue;$allowed=$permission['catalog']||(int)$tag['created_by']===$actor;
            if(!$allowed){$links=$linksByTag[(string)$tag['id']]??[];foreach($links as$link){try{$ref=$this->resolve($issuer,$actor,$feature,$link['source_kind'],(int)$link['source_id']);$proofs[]=[$feature,$link['source_kind'],(int)$link['source_id'],$ref];$allowed=true;break;}catch(DomainException$e){}}}
            if($allowed)$out[]=['id'=>NativeCompanyResourceId::encode('company_work_tag',$tag['id']),'name'=>self::label($tag['label']),'source_kind'=>'company_work_tag','source_id'=>(int)$tag['id'],'revision'=>(int)$tag['revision']];
        }
        $legacy=[];if($permission['primary']){
            $legacy=$this->rows('SELECT id,name FROM '.$this->p.'tags ORDER BY id LIMIT 1001');if(count($legacy)>1000)throw new DomainException('native_tags_limit');
            foreach($legacy as$tag){$links=$this->rows('SELECT rel_type,rel_id FROM '.$this->p.'taggables WHERE tag_id=? ORDER BY rel_type,rel_id',[$tag['id']]);$linkProofs[]=['SELECT rel_type,rel_id FROM '.$this->p.'taggables WHERE tag_id=? ORDER BY rel_type,rel_id',[$tag['id']],$links];$allowed=!$links&&$permission['catalog'];
                foreach($links as$link){$type=['customer'=>'client','tasks'=>'task','tickets'=>'ticket','invoices'=>'invoice','projects'=>'project'][$link['rel_type']]??$link['rel_type'];$feature=array_search($type,self::TYPES,true);if($feature===false||!$permission[$feature])continue;try{$ref=$this->resolve($issuer,$actor,$feature,$type,(int)$link['rel_id']);$proofs[]=[$feature,$type,(int)$link['rel_id'],$ref];$allowed=true;break;}catch(DomainException$e){}}
                if($allowed)$out[]=['id'=>(string)$tag['id'],'name'=>self::label($tag['name']),'source_kind'=>'work_tag','source_id'=>(int)$tag['id'],'revision'=>null];
            }
            if($this->rows('SELECT id,name FROM '.$this->p.'tags ORDER BY id LIMIT 1001')!==$legacy)throw new DomainException('native_tags_changed');
        }
        foreach($linkProofs as[$linkSql,$params,$links])if($this->rows($linkSql,$params)!==$links)throw new DomainException('native_tags_changed');
        foreach($proofs as[$feature,$source,$id,$ref])if($this->resolve($issuer,$actor,$feature,$source,$id)!==$ref)throw new DomainException('native_tags_changed');
        if($this->permissions($issuer,$actor)!==$permission||$this->rows($sql,[$issuer])!==$owned)throw new DomainException('native_tags_changed');
        // A tag's transport ID is decorative in tag-it; mutations resolve labels against the actual resource.
        $unique=[];foreach($out as$row){$key=mb_strtolower($row['name'],'UTF-8');if(!isset($unique[$key]))$unique[$key]=$row;}ksort($unique,SORT_STRING);$rows=array_values($unique);
        return ['tags'=>$rows,'available_tags'=>array_column($rows,'name'),'available_tags_ids'=>array_column($rows,'id')];
    }
}
