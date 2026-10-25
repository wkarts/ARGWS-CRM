<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/** Explicit primary-only historical lineage. Never adopts rows into a named-company store. */
final class NativeCompanyLegacyHeader
{
    private $db;private string $p;
    public function __construct($db,string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');$this->db=$db;$this->p=$prefix;}
    public function available(int $issuer,int $actor):bool
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);return $acl->member($issuer,$actor)&&$acl->primaryId()===$issuer;
    }
    private function authorize(int $issuer,int $actor):void{if(!$this->available($issuer,$actor))throw new DomainException('native_company_legacy_header_denied');}
    private function historyRow(int $actor):array
    {
        $rows=IssuerStorage::query($this->db,'SELECT umeta_id,meta_value FROM '.$this->p."user_meta WHERE staff_id=? AND client_id=0 AND contact_id=0 AND meta_key='recent_searches'",[$actor])->result_array();
        if(count($rows)>1)throw new DomainException('native_company_legacy_history_ambiguous');
        if(!$rows)return ['umeta_id'=>null,'meta_value'=>'[]','values'=>[]];
        try{$values=$rows[0]['meta_value']===null||$rows[0]['meta_value']===''?[]:json_decode((string)$rows[0]['meta_value'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('native_company_legacy_history_invalid');}
        if(!is_array($values)||!array_is_list($values)||count($values)>1000)throw new DomainException('native_company_legacy_history_invalid');
        foreach($values as $value)if(!is_string($value)||!preg_match('//u',$value)||strlen($value)>4096)throw new DomainException('native_company_legacy_history_invalid');
        return $rows[0]+['values'=>$values];
    }
    public function history(int $issuer,int $actor):array
    {
        $this->authorize($issuer,$actor);$row=$this->historyRow($actor);$version=hash('sha256',(string)$row['meta_value']);$items=[];
        foreach($row['values'] as $index=>$text)$items[]=['id'=>NativeCompanyResourceId::encode('search_history',$index+1),'revision'=>$version,'query_text'=>$text];
        $this->authorize($issuer,$actor);return $items;
    }
    public function removeHistory(int $issuer,int $actor,int $id,string $revision):array
    {
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','staff','user_meta'];
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$id,$revision){
            $this->authorize($issuer,$actor);$row=$this->historyRow($actor);
            if($id<1||!isset($row['values'][$id-1])||!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals(hash('sha256',(string)$row['meta_value']),$revision))throw new DomainException('issuer_search_stale');
            unset($row['values'][$id-1]);$encoded=json_encode(array_values($row['values']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'user_meta SET meta_value=? WHERE umeta_id=? AND staff_id=? AND meta_value=?',[$encoded,$row['umeta_id'],$actor,$row['meta_value']]);
            if($this->historyRow($actor)['meta_value']!==$encoded)throw new RuntimeException('issuer_search_write_unconfirmed');
            return $this->history($issuer,$actor);
        });
    }
    public function notifications(int $issuer,int $actor):array
    {
        $this->authorize($issuer,$actor);
        // Read only routing metadata until ownership is proven; do not hydrate B's event payload.
        $candidates=IssuerStorage::query($this->db,'SELECT id,isread,link FROM '.$this->p.'notifications WHERE touserid=? ORDER BY date DESC,id DESC',[$actor])->result_array();
        $links=[];$unread=0;
        foreach($candidates as $candidate){
            $url=$this->legacyLink($issuer,$actor,(string)($candidate['link']??''));
            if($url===null)continue;
            if((int)$candidate['isread']===0)$unread++;
            if(count($links)<30)$links[(int)$candidate['id']]=$url;
        }
        $rows=[];
        if($links){
            $ids=array_keys($links);$marks=implode(',',array_fill(0,count($ids),'?'));
            $rows=IssuerStorage::query($this->db,'SELECT id,isread,isread_inline,date,description,additional_data,link FROM '.$this->p.'notifications WHERE touserid=? AND id IN ('.$marks.') ORDER BY date DESC,id DESC',array_merge([$actor],$ids))->result_array();
        }
        $items=[];
        foreach($rows as $row){
            $url=$this->legacyLink($issuer,$actor,(string)($row['link']??''));
            if($url===null)continue; // Resource revocation between projection and payload read stays fail closed.
            $args=[];$raw=$row['additional_data'];
            if($raw!==null&&$raw!==''){
                if(!is_string($raw)||strlen($raw)>65536)throw new DomainException('native_company_legacy_notification_invalid');
                set_error_handler(static function(){throw new DomainException('native_company_legacy_notification_invalid');});
                try{$args=unserialize($raw,['allowed_classes'=>false,'max_depth'=>8]);}finally{restore_error_handler();}
                if(!is_array($args)||count($args)>32)throw new DomainException('native_company_legacy_notification_invalid');
                foreach($args as $arg)if(!is_scalar($arg)&&$arg!==null)throw new DomainException('native_company_legacy_notification_invalid');
            }
            $items[]=['id'=>NativeCompanyResourceId::encode('notification',(string)$row['id']),'date'=>$row['date'],'description'=>(string)$row['description'],'arguments'=>array_values($args),'url'=>$url,'read_at'=>(int)$row['isread']===1?$row['date']:null,'inline_read_at'=>(int)$row['isread_inline']===1?$row['date']:null];
        }
        $this->authorize($issuer,$actor);return ['items'=>$items,'unread'=>$unread];
    }
    private function legacyLink(int $issuer,int $actor,string $link):?string
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        if($link==='')return ''; // Historical personal notification without a resource target.
        $paths=['clients/client'=>'client','invoices/list_invoices'=>'invoice','projects/view'=>'project','tasks/view'=>'task','tickets/ticket'=>'ticket','contracts/contract'=>'contract','leads/index'=>'lead','expenses/list_expenses'=>'expense','estimates/list_estimates'=>'estimate','proposals/list_proposals'=>'proposal','credit_notes/list_credit_notes'=>'credit_note','payments/payment'=>'payment'];
        if(preg_match('~^([a-z_]+/[a-z_]+)/([1-9][0-9]*)$~D',$link,$m)&&isset($paths[$m[1]])){
            try{return NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$paths[$m[1]],$m[2],'view')['url'];}catch(DomainException $denied){return null;}
        }
        // No arbitrary stored URL is promoted into a trusted navigation destination.
        return null;
    }
    public function markNotification(int $issuer,int $actor,?int $id,string $mode):void
    {
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','staff','notifications'];
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$id,$mode){
            $this->authorize($issuer,$actor);
            if(!in_array($mode,['seen','inline','all'],true))throw new DomainException('issuer_notification_invalid');
            if($id!==null&&$id<1)throw new DomainException('issuer_notification_source_denied');
            $rows=IssuerStorage::query($this->db,'SELECT id,link FROM '.$this->p.'notifications WHERE touserid=?'.($id===null?'':' AND id=?'),$id===null?[$actor]:[$actor,$id])->result_array();
            $eligible=[];
            foreach($rows as $row)if($this->legacyLink($issuer,$actor,(string)($row['link']??''))!==null)$eligible[]=(int)$row['id'];
            if($id!==null&&!in_array($id,$eligible,true))throw new DomainException('issuer_notification_source_denied');
            foreach($eligible as $ownedId)foreach($mode==='all'?['isread','isread_inline']:[$mode==='seen'?'isread':'isread_inline'] as $column){
                IssuerStorage::query($this->db,'UPDATE '.$this->p.'notifications SET '.$column.'=1 WHERE touserid=? AND id=?',[$actor,$ownedId]);
                if(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'notifications WHERE touserid=? AND id=? AND '.$column.'<>1',[$actor,$ownedId])->result_array())throw new RuntimeException('issuer_notification_write_unconfirmed');
            }
            $this->authorize($issuer,$actor);
        });
    }
}
