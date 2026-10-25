<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyHeaderSchema.php';

/** Only the selected issuer/actor's search terms. Never reads staff.recent_search. */
final class NativeCompanySearchHistory
{
    private $db; private string $p;
    public function __construct($db,string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $this->db=$db;$this->p=$prefix;
    }
    public static function tables():array
    {
        return ['issuers_lock','issuers_registry','staff','issuers_staff','issuers_installation_identity',NativeCompanyHeaderSchema::HISTORY];
    }
    private function authorize(int $issuer,int $actor):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->p))->member($issuer,$actor))throw new DomainException('issuer_access_denied');
    }
    private function rows(int $issuer,int $actor):array
    {
        return IssuerStorage::query($this->db,'SELECT id,query_text,revision FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? ORDER BY updated_at DESC,id DESC LIMIT 5',[$issuer,$actor])->result_array();
    }
    public function read(int $issuer,int $actor):array
    {
        $this->authorize($issuer,$actor);$rows=$this->rows($issuer,$actor);$this->authorize($issuer,$actor);return $rows;
    }
    public function remember(int $issuer,int $actor,string $query):array
    {
        $query=trim($query);
        if(!preg_match('//u',$query)||preg_match('/[\x00-\x1f\x7f]/u',$query)||mb_strlen($query,'UTF-8')<2||mb_strlen($query,'UTF-8')>191)throw new DomainException('issuer_search_invalid');
        return IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($issuer,$actor,$query){
            $this->authorize($issuer,$actor);
            // Stable identifiers, revisioned updates; never mutate history by a displayed array index.
            $found=IssuerStorage::query($this->db,'SELECT id,query_text,revision FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND query_text=?',[$issuer,$actor,$query])->row_array();
            if(!$found){
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::HISTORY.' (issuer_id,staff_id,query_text,revision,created_at,updated_at) VALUES(?,?,?,1,?,?)',[$issuer,$actor,$query,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
                $found=IssuerStorage::query($this->db,'SELECT id,query_text,revision FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND query_text=?',[$issuer,$actor,$query])->row_array();
                if(!$found||$found['query_text']!==$query)throw new RuntimeException('issuer_search_write_unconfirmed');
            }
            else { $previous=(int)$found['revision']; IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyHeaderSchema::HISTORY.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND staff_id=? AND id=?',[gmdate('Y-m-d H:i:s'),$issuer,$actor,$found['id']]); $confirmed=IssuerStorage::query($this->db,'SELECT revision FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND id=?',[$issuer,$actor,$found['id']])->row_array(); if((int)($confirmed['revision']??0)!==$previous+1)throw new RuntimeException('issuer_search_write_unconfirmed'); }
            $rows=$this->rows($issuer,$actor);$keep=array_column($rows,'id');
            if($keep){
                $outside=' WHERE issuer_id=? AND staff_id=? AND id NOT IN ('.implode(',',array_fill(0,count($keep),'?')).')';$bindings=array_merge([$issuer,$actor],$keep);
                IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.$outside,$bindings);
                if(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.$outside,$bindings)->result_array())throw new RuntimeException('issuer_search_write_unconfirmed');
            }
            $this->authorize($issuer,$actor);return $rows;
        });
    }
    public function remove(int $issuer,int $actor,int $id,int $revision):array
    {
        if($id<1||$revision<1)throw new DomainException('issuer_search_invalid');
        return IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($issuer,$actor,$id,$revision){
            $this->authorize($issuer,$actor);
            $row=IssuerStorage::query($this->db,'SELECT id,revision FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND id=?',[$issuer,$actor,$id])->row_array();
            if(!$row||(int)$row['revision']!==$revision)throw new DomainException('issuer_search_stale');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND id=? AND revision=?',[$issuer,$actor,$id,$revision]);
            if(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.NativeCompanyHeaderSchema::HISTORY.' WHERE issuer_id=? AND staff_id=? AND id=?',[$issuer,$actor,$id])->result_array())throw new RuntimeException('issuer_search_write_unconfirmed');
            $this->authorize($issuer,$actor);return $this->rows($issuer,$actor);
        });
    }
}
