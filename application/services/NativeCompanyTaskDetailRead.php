<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';

/** Scoped factual input for composing the existing task detail. No route or view admission. */
final class NativeCompanyTaskDetailRead
{
    public function __construct(private $db,private string $prefix){}
    public function read(int $issuer,int $actor,$canonical):array
    {
        $projection=new NativeCompanyTaskIndexProjection($this->db,$this->prefix);
        $item=$projection->readOne($issuer,$actor,$canonical);
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        $access=[];foreach(['view','create','edit','delete']as$cap)$access[$cap]=$acl->can($issuer,$actor,'tasks',$cap);
        if(!$access['view'])throw new DomainException('native_company_task_denied');
        $kind=$item['source_kind']==='company_task'?'task':'standalone';$id=$item['source_id'];
        $comments=$this->rows('company_'.$kind.'_comments',$issuer,$id,null);
        $portal=$this->authorship($issuer,$item);
        foreach($comments as$comment)if((int)$comment['actor_id']===0&&!isset($portal['comments'][(int)$comment['id']]))throw new DomainException('native_task_comment_author_denied');
        // The original detail displays other staff's time only with a broad task grant.
        $timers=$this->rows('company_'.$kind.'_timers',$issuer,$id,($access['create']||$access['edit']||$access['delete'])?null:$actor);
        if($this->authorship($issuer,$item)!==$portal||$projection->readOne($issuer,$actor,$canonical)!==$item||$this->rows('company_'.$kind.'_comments',$issuer,$id,null)!==$comments||$this->rows('company_'.$kind.'_timers',$issuer,$id,($access['create']||$access['edit']||$access['delete'])?null:$actor)!==$timers)throw new DomainException('native_task_detail_changed');
        foreach($access as$cap=>$allowed)if($acl->can($issuer,$actor,'tasks',$cap)!==$allowed)throw new DomainException('native_task_detail_changed');
        // Canonical writers hydrate comments/timers internally; expose only the
        // separately scoped collections rather than their unfiltered copies.
        unset($item['row']['comments'],$item['row']['timers']);
        return ['item'=>$item,'comments'=>$comments,'timers'=>$timers,'permissions'=>$access,'portal'=>$portal];
    }
    private function authorship(int $issuer,array $item):array
    {
        $out=['origin'=>null,'comments'=>[]];
        if($item['source_kind']!=='company_task'||!$this->db->table_exists($this->prefix.'company_portal_task_origins'))return$out;
        $args=[$issuer,$item['row']['project_id'],$item['source_id']];
        $out['origin']=IssuerStorage::query($this->db,'SELECT contact_id,author_name,created_at FROM '.$this->prefix.'company_portal_task_origins WHERE issuer_id=? AND project_id=? AND task_id=?',$args)->row_array()?:null;
        foreach(IssuerStorage::query($this->db,'SELECT comment_id,contact_id,author_name,created_at FROM '.$this->prefix.'company_portal_task_comments WHERE issuer_id=? AND project_id=? AND task_id=? ORDER BY comment_id',$args)->result_array()as$row)$out['comments'][(int)$row['comment_id']]=$row;
        return$out;
    }
    private function rows(string $table,int $issuer,int $task,?int $actor):array
    {
        $args=[$issuer,$task];$where=' WHERE issuer_id=? AND task_id=?';if(str_ends_with($table,'_comments'))$where.=" AND body<>''";if($actor!==null){$where.=' AND actor_id=?';$args[]=$actor;}
        $rows=IssuerStorage::query($this->db,'SELECT * FROM '.$this->prefix.$table.$where.' ORDER BY id LIMIT 10001',$args)->result_array();
        if(count($rows)>10000)throw new DomainException('native_task_detail_limit');return$rows;
    }
}
