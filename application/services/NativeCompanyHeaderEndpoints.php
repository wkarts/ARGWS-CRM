<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyAdminPresentation.php';
require_once __DIR__.'/NativeCompanyNotifications.php';
require_once __DIR__.'/NativeCompanySearch.php';
require_once __DIR__.'/NativeCompanySearchHistory.php';
require_once __DIR__.'/NativeCompanyLegacyHeader.php';

/** Existing Misc endpoint adapters; no route dispatch or user-selected service method. */
final class NativeCompanyHeaderEndpoints
{
    private static function context($ci,bool $mutation):array
    {
        if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_company_header_denied');
        if($mutation)CompanyRequestScope::assertNativeMutation($ci);
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
        if(!is_int($issuer)||$issuer<1)throw new DomainException('native_company_header_denied');
        return [$issuer,$actor];
    }
    public static function search($ci):array
    {
        [$issuer,$actor]=self::context($ci,true);$q=$ci->input->post('q',false);
        if(!is_string($q))throw new DomainException('issuer_search_invalid');
        $result=(new NativeCompanySearch($ci->db,db_prefix()))->search($issuer,$actor,$q);
        $history=(new NativeCompanySearchHistory($ci->db,db_prefix()))->remember($issuer,$actor,$q);
        return ['results'=>$ci->load->view('admin/search',['result'=>$result],true),'history'=>self::history($ci,$issuer,$actor)];
    }
    public static function removeSearch($ci,$id):array
    {
        [$issuer,$actor]=self::context($ci,true);
        $reference=NativeCompanyResourceId::decode('search_history',$id);
        if($reference['source_kind']==='search_history'){
            $revision=$ci->input->post('revision',false);if(!is_string($revision))throw new DomainException('issuer_search_stale');
            (new NativeCompanyLegacyHeader($ci->db,db_prefix()))->removeHistory($issuer,$actor,(int)$reference['source_id'],$revision);
            return ['history'=>self::history($ci,$issuer,$actor)];
        }
        if($reference['source_kind']!=='company_search_history')throw new DomainException('issuer_search_invalid');
        $revision=IssuerPolicy::id($ci->input->post('revision',false));
        (new NativeCompanySearchHistory($ci->db,db_prefix()))->remove($issuer,$actor,(int)$reference['source_id'],$revision);
        return ['history'=>self::history($ci,$issuer,$actor)];
    }
    public static function notifications($ci):array
    {
        self::context($ci,false);
        $data=NativeCompanyAdminPresentation::refreshNotifications($ci);
        // Desktop permission is a browser/user decision, never inherited from global provider settings.
        return ['html'=>$ci->load->view('admin/includes/notifications',[],true),'notificationsIds'=>array_values(array_map(static fn($row)=>(string)$row['id'],array_filter($data['items'],static fn($row)=>$row['read_at']===null)))];
    }
    public static function history($ci,int $issuer,int $actor):array
    {
        if(self::context($ci,false)!==[$issuer,$actor])throw new DomainException('native_company_header_denied');$rows=self::historyIds((new NativeCompanySearchHistory($ci->db,db_prefix()))->read($issuer,$actor));
        $legacy=new NativeCompanyLegacyHeader($ci->db,db_prefix());if($legacy->available($issuer,$actor))$rows=array_merge($rows,$legacy->history($issuer,$actor));
        $seen=[];$out=[];foreach($rows as $row){if(isset($seen[$row['query_text']]))continue;$seen[$row['query_text']]=true;$out[]=$row;if(count($out)===5)break;}return $out;
    }
    private static function historyIds(array $rows):array
    {
        foreach($rows as &$row){$row['id']=NativeCompanyResourceId::encode('company_search_history',(string)$row['id']);}unset($row);
        return $rows;
    }
    public static function markNotifications($ci,$id=null,string $mode='inline'):array
    {
        [$issuer,$actor]=self::context($ci,true);
        if($id!==null){
            $reference=NativeCompanyResourceId::decode('notification',$id);
            if($reference['source_kind']==='notification'){
                (new NativeCompanyLegacyHeader($ci->db,db_prefix()))->markNotification($issuer,$actor,(int)$reference['source_id'],$mode);
                return self::notifications($ci)+['success'=>true];
            }
            if($reference['source_kind']!=='company_notification')throw new DomainException('issuer_notification_invalid');
            $id=(int)$reference['source_id'];
        }
        (new NativeCompanyNotifications($ci->db,db_prefix()))->markRead($issuer,$actor,$id,$mode);
        if($id===null){$legacy=new NativeCompanyLegacyHeader($ci->db,db_prefix());if($legacy->available($issuer,$actor))$legacy->markNotification($issuer,$actor,null,$mode);}
        return self::notifications($ci)+['success'=>true];
    }
}
