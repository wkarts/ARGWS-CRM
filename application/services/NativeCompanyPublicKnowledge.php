<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyKnowledgeSchema.php';
/** Public knowledge is an explicit company publication, never a projection of the staff session. */
final class NativeCompanyPublicKnowledge
{
    public function __construct(private $db,private string $p,private ?NativePortalIdentity $portal=null,private $session=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function gate(int $issuer):bool
    {
        if($issuer<1)throw new DomainException('native_public_knowledge_denied');$settings=new CompanySettingsStorage($this->db,$this->p);
        if($settings->get($issuer,'use_knowledge_base')!=='1')throw new DomainException('native_public_knowledge_denied');
        if($settings->get($issuer,'knowledge_base_without_registration')!=='1'&&($this->portal===null||$this->session===null||$this->portal->current($this->session,$issuer)===null))throw new DomainException('native_public_knowledge_login_required');
        return $settings->isPrimary($issuer);
    }
    private static function slug(string $slug):void{if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||strlen($slug)>191)throw new DomainException('native_public_knowledge_denied');}
    public function grouped(int $issuer,?string $query=null,?string $category=null):array
    {
        $primary=$this->gate($issuer);if($query!==null&&(preg_match('//u',$query)!==1||mb_strlen($query)>191||str_contains($query,"\0")))throw new DomainException('native_public_knowledge_query_invalid');if($category!==null)self::slug($category);
        $groups=[];foreach($this->rows('SELECT id,name,description,group_slug,color,group_order FROM '.$this->p.'company_kb_groups WHERE issuer_id=? AND active=1 ORDER BY group_order,id',[$issuer])as$row){$row['source_kind']='company_kb_group';$row['source_id']=$row['id'];$row['groupid']=NativeCompanyResourceId::encode('company_kb_group',$row['id']);$groups[]=$row;}
        if($primary)foreach($this->rows('SELECT groupid,name,description,group_slug,color,group_order FROM '.$this->p.'knowledge_base_groups WHERE active=1 ORDER BY group_order,groupid')as$row){$row['source_kind']='kb_group';$row['source_id']=$row['groupid'];$row['groupid']=(string)$row['groupid'];$groups[]=$row;}
        $out=[];$slugs=[];foreach($groups as$group){
            if(isset($slugs[$group['group_slug']]))throw new DomainException('native_public_knowledge_slug_conflict');$slugs[$group['group_slug']]=true;if($category!==null&&$category!==$group['group_slug'])continue;
            $articles=[];$rows=$this->rows('SELECT id,subject,slug,description,article_order,created_at FROM '.$this->p.'company_kb_articles WHERE issuer_id=? AND group_source_kind=? AND group_source_id=? AND active=1 AND staff_article=0 ORDER BY article_order,id',[$issuer,$group['source_kind'],$group['source_id']]);
            foreach($rows as$row){$row['articleid']=NativeCompanyResourceId::encode('company_kb_article',$row['id']);$row['datecreated']=$row['created_at'];$articles[]=$row;}
            if($primary){$sql='SELECT a.articleid,a.subject,a.slug,a.description,a.article_order,a.datecreated FROM '.$this->p.'knowledge_base a LEFT JOIN '.$this->p.NativeCompanyKnowledgeSchema::LEGACY_GROUPS.' l ON l.issuer_id=? AND l.legacy_article_id=a.articleid WHERE a.active=1 AND a.staff_article=0 AND ((l.id IS NULL AND ?=\'kb_group\' AND a.articlegroup=?) OR (l.group_source_kind=? AND l.group_source_id=?)) ORDER BY a.article_order,a.articleid';foreach($this->rows($sql,[$issuer,$group['source_kind'],$group['source_id'],$group['source_kind'],$group['source_id']])as$row){$row['articleid']=(string)$row['articleid'];$articles[]=$row;}}
            usort($articles,static fn($a,$b)=>[(int)$a['article_order'],$a['articleid']]<=>[(int)$b['article_order'],$b['articleid']]);$visible=[];
            foreach($articles as$row){if($query!==null&&mb_stripos($row['subject'].' '.strip_tags($row['description']),$query,0,'UTF-8')===false)continue;$visible[]=array_intersect_key($row,array_flip(['articleid','subject','slug','description','datecreated']))+['active_article'=>1,'staff_article'=>0,'articlegroup'=>$group['groupid']];}
            if($visible)$out[]=array_intersect_key($group,array_flip(['groupid','name','description','group_slug','color','group_order']))+['articles'=>$visible];
        }
        usort($out,static fn($a,$b)=>[(int)$a['group_order'],$a['groupid']]<=>[(int)$b['group_order'],$b['groupid']]);if($this->gate($issuer)!==$primary)throw new DomainException('native_public_knowledge_denied');return$out;
    }
    public function article(int $issuer,string $slug):array
    {
        self::slug($slug);$found=[];foreach($this->grouped($issuer)as$group)foreach($group['articles']as$row)if($row['slug']===$slug)$found[]=$row;
        if(count($found)!==1)throw new DomainException('native_public_knowledge_denied');return$found[0];
    }
}
