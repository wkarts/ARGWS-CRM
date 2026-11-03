<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyKnowledge.php';
/** Data for the existing knowledge-base templates. Not a second layout or authorization source. */
final class NativeCompanyKnowledgePresentation
{
    public function __construct(private $db,private string $p){}
    public function catalog(int $issuer,int $actor):array
    {
        $kb=new NativeCompanyKnowledge($this->db,$this->p);$groups=$kb->groups($issuer,$actor);$articles=$kb->articles($issuer,$actor);
        $acl=new IssuerResourceAccess($this->db,$this->p);$canEdit=$acl->can($issuer,$actor,'knowledge_base','edit');$canCreate=$acl->can($issuer,$actor,'knowledge_base','create');
        $byGroup=[];$counts=[];foreach($groups as $g){$byGroup[$g['groupid']]=[];$counts[$g['groupid']]=0;}
        foreach($articles as $a){if(!array_key_exists($a['articlegroup'],$counts))throw new DomainException('issuer_knowledge_group_missing');$counts[$a['articlegroup']]++;if($canEdit||$canCreate||(int)$a['active']===1)$byGroup[$a['articlegroup']][]=$a;}
        if(!$acl->can($issuer,$actor,'knowledge_base','view'))throw new DomainException('issuer_knowledge_denied');
        require_once __DIR__.'/NativeCompanyTableOrder.php';
        $tableOrder=(new NativeCompanyTableOrder($this->db,$this->p,$issuer,$actor))->read('kb-articles');
        if(!$acl->can($issuer,$actor,'knowledge_base','view'))throw new DomainException('issuer_knowledge_denied');
        return ['native_knowledge_table_order'=>$tableOrder,'native_knowledge'=>true,'native_knowledge_issuer'=>$issuer,'groups'=>$groups,'native_knowledge_articles'=>$byGroup,'native_knowledge_counts'=>$counts,'native_knowledge_group_count'=>count($groups)];
    }
    public static function publicUrl(string $slug,int $issuer):string
    {
        if($issuer<1||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new DomainException('issuer_knowledge_slug_invalid');
        return site_url('knowledge-base/article/'.$slug).'?issuer_id='.$issuer;
    }
}
