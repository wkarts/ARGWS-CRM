<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyKnowledgeSchema.php';

/** Data adapter for the existing knowledge-base controllers and views. */
final class NativeCompanyKnowledge
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int $issuer,int $actor,string $cap='view'):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'knowledge_base',$cap))throw new DomainException('issuer_knowledge_denied');
        return $acl;
    }
    private static function revision(array $row):string {ksort($row);return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private static function slug(string $slug):string
    {
        if($slug===''||strlen($slug)>191||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new DomainException('issuer_knowledge_slug_invalid');return $slug;
    }
    private function groupRow(array $row,bool $owned):array
    {
        $raw=$row;$source=$owned?'company_kb_group':'kb_group';$id=$row[$owned?'id':'groupid'];
        $row['groupid']=NativeCompanyResourceId::encode($source,$id);$row['source_kind']=$source;$row['source_id']=(int)$id;
        $row['revision_token']=self::revision($raw);$row['description']=$row['description']??'';
        return $row;
    }
    public function groups(int $issuer,int $actor):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();$out=[];
        foreach($this->rows('SELECT * FROM '.$this->p.'company_kb_groups WHERE issuer_id=? ORDER BY group_order,id',[$issuer]) as $r)$out[]=$this->groupRow($r,true);
        if($primary===$issuer)foreach($this->rows('SELECT * FROM '.$this->p.'knowledge_base_groups ORDER BY group_order,groupid') as $r)$out[]=$this->groupRow($r,false);
        usort($out,static fn($a,$b)=>[(int)$a['group_order'],$a['groupid']]<=>[(int)$b['group_order'],$b['groupid']]);
        $this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('issuer_knowledge_denied');return $out;
    }
    public function group(int $issuer,int $actor,$id):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();$d=NativeCompanyResourceId::decode('kb_group',$id);
        if($d['source_kind']==='company_kb_group')$row=$this->rows('SELECT * FROM '.$this->p.'company_kb_groups WHERE issuer_id=? AND id=?',[$issuer,$d['source_id']])[0]??null;
        else {if($acl->primaryId()!==$issuer)throw new DomainException('issuer_knowledge_denied');$row=$this->rows('SELECT * FROM '.$this->p.'knowledge_base_groups WHERE groupid=?',[$d['source_id']])[0]??null;}
        if(!$row)throw new DomainException('issuer_knowledge_denied');$this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('issuer_knowledge_denied');return $this->groupRow($row,$d['source_kind']==='company_kb_group');
    }
    private function articleRow(int $issuer,int $actor,array $row,bool $owned):array
    {
        $raw=$row;$source=$owned?'company_kb_article':'kb_article';$id=$row[$owned?'id':'articleid'];
        $groupId=$owned?NativeCompanyResourceId::encode($row['group_source_kind'],$row['group_source_id']):(string)$row['articlegroup'];
        if(!$owned){
            $link=$this->rows('SELECT * FROM '.$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$id])[0]??null;
            if($link){$groupId=NativeCompanyResourceId::encode($link['group_source_kind'],$link['group_source_id']);$raw['owned_group_link']=$link;}
        }
        $group=$this->group($issuer,$actor,$groupId);
        $row['articleid']=NativeCompanyResourceId::encode($source,$id);$row['articlegroup']=$groupId;
        $row['source_kind']=$source;$row['source_id']=(int)$id;$row['revision_token']=self::revision($raw);
        $row['active_article']=$row['active'];$row['active_group']=$group['active'];$row['group_name']=$group['name'];
        $row['datecreated']=$row[$owned?'created_at':'datecreated'];
        // The product explicitly disables new view telemetry in add_views_tracking().
        $row['total_views']=$owned?0:(int)($this->rows('SELECT COUNT(*) AS n FROM '.$this->p.'views_tracking WHERE rel_type=? AND rel_id=?',['kb_article',$id])[0]['n']??0);
        $row['views_provenance']=$owned?'new_telemetry_disabled':'historical_only';return $row;
    }
    public function articles(int $issuer,int $actor,?string $groupId=null,?string $query=null):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();if($groupId!==null)$this->group($issuer,$actor,$groupId);
        if($query!==null&&(!preg_match('//u',$query)||mb_strlen($query)>191||str_contains($query,"\0")))throw new DomainException('issuer_knowledge_query_invalid');
        $out=[];$streams=[true];if($primary===$issuer)$streams[]=false;
        foreach($streams as $owned){
            $sql=$owned?'SELECT * FROM '.$this->p.'company_kb_articles WHERE issuer_id=? ORDER BY article_order,id':'SELECT * FROM '.$this->p.'knowledge_base ORDER BY article_order,articleid';
            foreach($this->rows($sql,$owned?[$issuer]:[]) as $r){
                $row=$this->articleRow($issuer,$actor,$r,$owned);
                if($groupId!==null&&(string)$row['articlegroup']!==$groupId)continue;
                if($query!==null&&mb_stripos($row['subject'].' '.strip_tags($row['description']),$query,0,'UTF-8')===false)continue;
                $out[]=$row;
            }
        }
        usort($out,static fn($a,$b)=>[(int)$a['article_order'],$a['articleid']]<=>[(int)$b['article_order'],$b['articleid']]);
        $this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('issuer_knowledge_denied');return $out;
    }
    public function article(int $issuer,int $actor,$id):array
    {
        $acl=$this->access($issuer,$actor);$primary=$acl->primaryId();$d=NativeCompanyResourceId::decode('kb_article',$id);$owned=$d['source_kind']==='company_kb_article';
        if(!$owned&&$acl->primaryId()!==$issuer)throw new DomainException('issuer_knowledge_denied');
        $sql=$owned?'SELECT * FROM '.$this->p.'company_kb_articles WHERE issuer_id=? AND id=?':'SELECT * FROM '.$this->p.'knowledge_base WHERE articleid=?';
        $row=$this->rows($sql,$owned?[$issuer,$d['source_id']]:[$d['source_id']])[0]??null;
        if(!$row)throw new DomainException('issuer_knowledge_denied');$out=$this->articleRow($issuer,$actor,$row,$owned);$this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('issuer_knowledge_denied');return $out;
    }
    public function bySlug(int $issuer,int $actor,string $slug):array
    {
        self::slug($slug);$matches=[];foreach($this->articles($issuer,$actor) as $r)if($r['slug']===$slug)$matches[]=$r;
        // A historical collision must never choose whichever storage happens to be queried first.
        if(count($matches)!==1)throw new DomainException('issuer_knowledge_slug_unavailable');return $matches[0];
    }
    public function related(int $issuer,int $actor,$id):array
    {
        $article=$this->article($issuer,$actor,$id);$out=[];
        foreach($this->articles($issuer,$actor,(string)$article['articlegroup']) as $r)if($r['articleid']!==$article['articleid']&&(int)$r['active_article']===1&&(int)$r['active_group']===1&&(int)$r['staff_article']===(int)$article['staff_article']){$out[]=$r;if(count($out)===5)break;}
        return $out;
    }
    private static function text($v,int $max,bool $empty=false):string
    {
        if(!is_string($v)||strlen($v)>$max||(!$empty&&trim($v)==='')||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('issuer_knowledge_input_invalid');return $v;
    }
    private static function integer($v):int
    {
        if((!is_string($v)&&!is_int($v))||!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D',(string)$v))throw new DomainException('issuer_knowledge_input_invalid');return(int)$v;
    }
    public static function form(array $input,bool $article):array
    {
        $allowed=$article?['subject','slug','description','articlegroup','disabled','staff_article','article_order']:['name','group_slug','description','color','disabled','group_order'];
        if(array_diff(array_keys($input),$allowed))throw new DomainException('issuer_knowledge_input_invalid');
        $name=$article?'subject':'name';$slug=$article?'slug':'group_slug';$order=$article?'article_order':'group_order';
        $out=[$name=>self::text($input[$name]??null,191),$slug=>self::slug($input[$slug]??''),'active'=>isset($input['disabled'])?0:1,$order=>self::integer($input[$order]??0)];
        foreach(['disabled','staff_article']as$flag)if(isset($input[$flag])&&!in_array($input[$flag],[1,'1','on'],true))throw new DomainException('issuer_knowledge_input_invalid');
        if($article){
            require_once __DIR__.'/NativeCompanyContentHtml.php';$out['description']=NativeCompanyContentHtml::purify(self::text($input['description']??'',65535,true));
            $out['staff_article']=isset($input['staff_article'])?1:0;$group=NativeCompanyResourceId::decode('kb_group',$input['articlegroup']??null);$out['group_source_kind']=$group['source_kind'];$out['group_source_id']=$group['source_id'];
        }else{$out['description']=self::text($input['description']??'',65535,true);$out['color']=$input['color']??'#28B8DA';if(!is_string($out['color'])||!preg_match('/^#[a-fA-F0-9]{6}$/D',$out['color']))throw new DomainException('issuer_knowledge_input_invalid');}
        return $out;
    }
    private function tables(bool $legacy):array
    {
        $tables=['issuers_lock','issuers_registry','staff','issuers_staff','issuers_installation_identity','issuers_resource_permissions','issuers_audit',NativeCompanyKnowledgeSchema::GROUPS,NativeCompanyKnowledgeSchema::ARTICLES,NativeCompanyKnowledgeSchema::FEEDBACK,NativeCompanyKnowledgeSchema::LEGACY_GROUPS];
        if($legacy)$tables=array_merge($tables,['knowledge_base','knowledge_base_groups']);return $tables;
    }
    /** Authenticated native feedback: one vote per actor/article in each rolling 24h window. */
    public function feedback(int $issuer,int $actor,$id,string $answer,?int $now=null):bool
    {
        $acl=$this->access($issuer,$actor);if(!in_array($answer,['0','1'],true))throw new DomainException('issuer_knowledge_answer_invalid');
        $now=$now??time();if($now<1)throw new DomainException('issuer_knowledge_time_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables($acl->primaryId()===$issuer),function()use($issuer,$actor,$id,$answer,$now){
            $acl=$this->access($issuer,$actor);$acl->lockFinancialIdentity($issuer,$actor);$article=$this->article($issuer,$actor,$id);
            $respondent=hash('sha256','staff:'.$issuer.':'.$actor);$args=[$issuer,$article['source_kind'],$article['source_id'],$respondent];
            $row=$this->rows('SELECT created_at FROM '.$this->p.NativeCompanyKnowledgeSchema::FEEDBACK.' WHERE issuer_id=? AND article_source_kind=? AND article_source_id=? AND respondent_hash=? ORDER BY created_at DESC,id DESC LIMIT 1',$args)[0]??null;
            // UTC is explicit for new owned feedback; no reinterpretation of historical IP records.
            if($row){$last=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$row['created_at'],new DateTimeZone('UTC'));if(!$last||$last->format('Y-m-d H:i:s')!==$row['created_at'])throw new RuntimeException('issuer_knowledge_feedback_corrupt');if($last->getTimestamp()>=$now-86400)return false;}
            $at=gmdate('Y-m-d H:i:s',$now);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyKnowledgeSchema::FEEDBACK.' (issuer_id,article_source_kind,article_source_id,respondent_hash,answer,created_at) VALUES(?,?,?,?,?,?)',array_merge($args,[(int)$answer,$at]));
            $insert=(int)$this->db->insert_id();$stored=$this->rows('SELECT * FROM '.$this->p.NativeCompanyKnowledgeSchema::FEEDBACK.' WHERE issuer_id=? AND id=?',[$issuer,$insert])[0]??null;
            if(!$stored||$stored['respondent_hash']!==$respondent||(int)$stored['answer']!==(int)$answer||$stored['created_at']!==$at)throw new RuntimeException('issuer_knowledge_write_unconfirmed');
            $this->access($issuer,$actor);return true;
        });
    }
    /** Controllers supply explicit native slug generation and central CSRF/context before this call. */
    public function save(int $issuer,int $actor,bool $article,array $input,$id=null,?string $revision=null):array
    {
        $this->access($issuer,$actor,$id===null?'create':'edit');$data=self::form($input,$article);
        if($id!==null&&($revision===null||!preg_match('/^[a-f0-9]{64}$/D',$revision)))throw new DomainException('issuer_knowledge_revision_invalid');
        $acl=new IssuerResourceAccess($this->db,$this->p);$primary=$acl->primaryId()===$issuer;
        return IssuerTransaction::run($this->db,$this->p,$this->tables($primary),function()use($issuer,$actor,$article,$data,$id,$revision){
            $acl=$this->access($issuer,$actor,$id===null?'create':'edit');$acl->lockFinancialIdentity($issuer,$actor);
            $kind=$article?'kb_article':'kb_group';$owned=true;$old=null;
            if($id!==null){$d=NativeCompanyResourceId::decode($kind,$id);$owned=$d['source_kind']==='company_'.$kind;$old=$article?$this->article($issuer,$actor,$id):$this->group($issuer,$actor,$id);if(!hash_equals($old['revision_token'],$revision))throw new DomainException('issuer_knowledge_stale');}
            if($article)$this->group($issuer,$actor,NativeCompanyResourceId::encode($data['group_source_kind'],$data['group_source_id']));
            $slugKey=$article?'slug':'group_slug';$existing=$article?$this->articles($issuer,$actor):$this->groups($issuer,$actor);
            foreach($existing as$r)if($r[$slugKey]===$data[$slugKey]&&($id===null||$r[$article?'articleid':'groupid']!==(string)$id))throw new DomainException('issuer_knowledge_slug_conflict');
            $table=$owned?($article?NativeCompanyKnowledgeSchema::ARTICLES:NativeCompanyKnowledgeSchema::GROUPS):($article?'knowledge_base':'knowledge_base_groups');$key=$owned?'id':($article?'articleid':'groupid');$write=$data;$now=date('Y-m-d H:i:s');
            if(!$owned&&$article){
                if($data['group_source_kind']==='kb_group')$write['articlegroup']=$data['group_source_id'];
                unset($write['group_source_kind'],$write['group_source_id']);
            }
            if($owned){$write['revision']=$old===null?1:(int)$old['revision']+1;if($write['revision']>2147483647)throw new DomainException('issuer_knowledge_revision_overflow');$write['actor_id']=$actor;$write['updated_at']=$now;}
            if($id===null){$write['issuer_id']=$issuer;$write['created_at']=$now;$columns=array_keys($write);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' ('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($columns),'?')).')',array_values($write));$source=(int)$this->db->insert_id();if($source<1)throw new RuntimeException('issuer_knowledge_write_unconfirmed');$id=NativeCompanyResourceId::encode('company_'.$kind,$source);}
            else{$source=NativeCompanyResourceId::decode($kind,$id)['source_id'];$sets=[];foreach(array_keys($write)as$c)$sets[]=$c.'=?';$params=array_values($write);$params[]=$source;$where=$key.'=?';if($owned){$where.=' AND issuer_id=?';$params[]=$issuer;}IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',$sets).' WHERE '.$where,$params);}
            if(!$owned&&$article){
                $linkTable=$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS;
                if($data['group_source_kind']==='kb_group')IssuerStorage::query($this->db,'DELETE FROM '.$linkTable.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$source]);
                else {
                    $link=$this->rows('SELECT * FROM '.$linkTable.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$source])[0]??null;
                    if($link)IssuerStorage::query($this->db,'UPDATE '.$linkTable.' SET group_source_kind=?,group_source_id=?,revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND legacy_article_id=?',[$data['group_source_kind'],$data['group_source_id'],$actor,$now,$issuer,$source]);
                    else IssuerStorage::query($this->db,'INSERT INTO '.$linkTable.' (issuer_id,legacy_article_id,group_source_kind,group_source_id,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,1,?,?,?)',[$issuer,$source,$data['group_source_kind'],$data['group_source_id'],$actor,$now,$now]);
                }
            }
            $record=$article?$this->article($issuer,$actor,$id):$this->group($issuer,$actor,$id);
            foreach($data as$k=>$v){$actual=(!$owned&&in_array($k,['group_source_kind','group_source_id'],true))?NativeCompanyResourceId::decode('kb_group',$record['articlegroup'])[$k==='group_source_kind'?'source_kind':'source_id']:($record[$k]??null);if((string)$actual!==(string)$v)throw new RuntimeException('issuer_knowledge_write_unconfirmed');}
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$article?'native_kb_article_saved':'native_kb_group_saved',$issuer,$source,$now]);
            $this->access($issuer,$actor,$old===null?'create':'edit');return $record;
        });
    }

    /** Native status/color/order mutations share the same row revision contract. */
    public function change(int $issuer,int $actor,bool $article,$id,string $revision,array $changes,bool $inOwnedTransaction=false):array
    {
        $allowed=$article?['active','article_order','articlegroup']:['active','group_order','color'];
        if(!$changes||array_diff(array_keys($changes),$allowed))throw new DomainException('issuer_knowledge_input_invalid');
        foreach($changes as $k=>$v){if($k==='articlegroup'){NativeCompanyResourceId::decode('kb_group',$v);$changes[$k]=(string)$v;}elseif($k==='color'){if(!is_string($v)||!preg_match('/^#[a-fA-F0-9]{6}$/D',$v))throw new DomainException('issuer_knowledge_input_invalid');}elseif($k==='active'){if(!in_array($v,[0,1,'0','1'],true))throw new DomainException('issuer_knowledge_input_invalid');$changes[$k]=(int)$v;}else $changes[$k]=self::integer($v);}
        $this->access($issuer,$actor,'edit');$acl=new IssuerResourceAccess($this->db,$this->p);
        $tables=$this->tables($acl->primaryId()===$issuer);$operation=function()use($issuer,$actor,$article,$id,$revision,$changes){
            $acl=$this->access($issuer,$actor,'edit');$acl->lockFinancialIdentity($issuer,$actor);
            $row=$article?$this->article($issuer,$actor,$id):$this->group($issuer,$actor,$id);
            if(!hash_equals($row['revision_token'],$revision))throw new DomainException('issuer_knowledge_stale');
            $d=NativeCompanyResourceId::decode($article?'kb_article':'kb_group',$id);$owned=str_starts_with($d['source_kind'],'company_');
            $table=$owned?($article?NativeCompanyKnowledgeSchema::ARTICLES:NativeCompanyKnowledgeSchema::GROUPS):($article?'knowledge_base':'knowledge_base_groups');
            $write=$changes;$now=date('Y-m-d H:i:s');
            if(isset($changes['articlegroup'])){
                $group=$this->group($issuer,$actor,$changes['articlegroup']);unset($write['articlegroup']);
                if($owned){$write['group_source_kind']=$group['source_kind'];$write['group_source_id']=$group['source_id'];}
                elseif($group['source_kind']==='kb_group'){$write['articlegroup']=$group['source_id'];IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$d['source_id']]);}
                else{
                    $tableLink=$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS;$link=$this->rows('SELECT * FROM '.$tableLink.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$d['source_id']])[0]??null;
                    if($link)IssuerStorage::query($this->db,'UPDATE '.$tableLink.' SET group_source_kind=?,group_source_id=?,revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND legacy_article_id=?',[$group['source_kind'],$group['source_id'],$actor,$now,$issuer,$d['source_id']]);
                    else IssuerStorage::query($this->db,'INSERT INTO '.$tableLink.' (issuer_id,legacy_article_id,group_source_kind,group_source_id,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,1,?,?,?)',[$issuer,$d['source_id'],$group['source_kind'],$group['source_id'],$actor,$now,$now]);
                }
            }
            if($owned){$write['revision']=(int)$row['revision']+1;if($write['revision']>2147483647)throw new DomainException('issuer_knowledge_revision_overflow');$write['updated_at']=$now;$write['actor_id']=$actor;}
            $set=[];foreach(array_keys($write)as$k)$set[]=$k.'=?';$args=array_values($write);$args[]=$d['source_id'];$where=($owned?'id':($article?'articleid':'groupid')).'=?';if($owned){$where.=' AND issuer_id=?';$args[]=$issuer;}
            if($set)IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',$set).' WHERE '.$where,$args);
            $after=$article?$this->article($issuer,$actor,$id):$this->group($issuer,$actor,$id);foreach($changes as$k=>$v)if((string)$after[$k]!==(string)$v)throw new RuntimeException('issuer_knowledge_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$article?'native_kb_article_changed':'native_kb_group_changed',$issuer,$d['source_id'],$now]);$this->access($issuer,$actor,'edit');return$after;
        };
        if($inOwnedTransaction){IssuerTransaction::assertOwned($this->db,$this->p,$tables);return$operation();}
        return IssuerTransaction::run($this->db,$this->p,$tables,$operation);
    }
    public function delete(int $issuer,int $actor,bool $article,$id,string $revision):void
    {
        $this->access($issuer,$actor,'delete');$acl=new IssuerResourceAccess($this->db,$this->p);$primary=$acl->primaryId()===$issuer;
        $tables=$this->tables($primary);if($primary)$tables[]='knowedge_base_article_feedback';
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$article,$id,$revision,$primary){
            $acl=$this->access($issuer,$actor,'delete');$acl->lockFinancialIdentity($issuer,$actor);
            $row=$article?$this->article($issuer,$actor,$id):$this->group($issuer,$actor,$id);if(!hash_equals($row['revision_token'],$revision))throw new DomainException('issuer_knowledge_stale');
            $d=NativeCompanyResourceId::decode($article?'kb_article':'kb_group',$id);$owned=str_starts_with($d['source_kind'],'company_');
            if(!$article){
                if($this->rows('SELECT id FROM '.$this->p.NativeCompanyKnowledgeSchema::ARTICLES.' WHERE issuer_id=? AND group_source_kind=? AND group_source_id=? LIMIT 1',[$issuer,$d['source_kind'],$d['source_id']])||$this->rows('SELECT id FROM '.$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS.' WHERE issuer_id=? AND group_source_kind=? AND group_source_id=? LIMIT 1',[$issuer,$d['source_kind'],$d['source_id']]))throw new DomainException('issuer_knowledge_group_referenced');
                if(!$owned&&$this->rows('SELECT articleid FROM '.$this->p.'knowledge_base WHERE articlegroup=? LIMIT 1',[$d['source_id']]))throw new DomainException('issuer_knowledge_group_referenced');
            }else{
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanyKnowledgeSchema::FEEDBACK.' WHERE issuer_id=? AND article_source_kind=? AND article_source_id=?',[$issuer,$d['source_kind'],$d['source_id']]);
                if(!$owned){IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'knowedge_base_article_feedback WHERE articleid=?',[$d['source_id']]);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS.' WHERE issuer_id=? AND legacy_article_id=?',[$issuer,$d['source_id']]);}
            }
            $table=$owned?($article?NativeCompanyKnowledgeSchema::ARTICLES:NativeCompanyKnowledgeSchema::GROUPS):($article?'knowledge_base':'knowledge_base_groups');$key=$owned?'id':($article?'articleid':'groupid');$args=[$d['source_id']];$where=$key.'=?';if($owned){$where.=' AND issuer_id=?';$args[]=$issuer;}
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$args);if($this->rows('SELECT '.$key.' FROM '.$this->p.$table.' WHERE '.$where,$args))throw new RuntimeException('issuer_knowledge_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$article?'native_kb_article_deleted':'native_kb_group_deleted',$issuer,$d['source_id'],date('Y-m-d H:i:s')]);$this->access($issuer,$actor,'delete');
        });
    }

    public function reorder(int $issuer,int $actor,bool $articles,array $rows):array
    {
        if(!$rows||count($rows)>500)throw new DomainException('issuer_knowledge_order_invalid');
        $acl=$this->access($issuer,$actor,'edit');$tables=$this->tables($acl->primaryId()===$issuer);
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$articles,$rows){
            $seen=[];$prepared=[];
            foreach($rows as$r){
                if(!is_array($r)||array_diff(array_keys($r),$articles?['id','revision','order','group']:['id','revision','order'])||!isset($r['id'],$r['revision'],$r['order'])||!is_string($r['revision']))throw new DomainException('issuer_knowledge_order_invalid');
                $d=NativeCompanyResourceId::decode($articles?'kb_article':'kb_group',$r['id']);if(isset($seen[$d['canonical_id']]))throw new DomainException('issuer_knowledge_order_invalid');$seen[$d['canonical_id']]=true;
                $row=$articles?$this->article($issuer,$actor,$r['id']):$this->group($issuer,$actor,$r['id']);if(!hash_equals($row['revision_token'],$r['revision']))throw new DomainException('issuer_knowledge_stale');
                $changes=[$articles?'article_order':'group_order'=>self::integer($r['order'])];if(isset($r['group'])){$this->group($issuer,$actor,$r['group']);$changes['articlegroup']=(string)$r['group'];}$prepared[]=[$r['id'],$r['revision'],$changes];
            }
            $out=[];foreach($prepared as[$id,$revision,$changes])$out[(string)$id]=$this->change($issuer,$actor,$articles,$id,$revision,$changes,true)['revision_token'];return$out;
        });
    }

}
