<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** The original author/capability and first-hour setting, resolved for the active issuer. */
final class NativeCompanyTaskCommentPolicy
{
    public static function allowed($db,string $p,int $issuer,int $actor,array $comment,string $action,?int $now=null):bool
    {
        if(!in_array($action,['comment_edit','comment_remove'],true))throw new DomainException('native_task_comment_invalid');
        $acl=new IssuerResourceAccess($db,$p);if(!$acl->can($issuer,$actor,'tasks','view'))return false;
        $admin=$acl->administrative($issuer,$actor);$cap=$action==='comment_remove'?'delete':'edit';
        if((int)$comment['actor_id']!==$actor&&!$admin&&!$acl->can($issuer,$actor,'tasks',$cap))return false;
        $store=new CompanySettingsStorage($db,$p);$key='client_staff_add_edit_delete_task_comments_first_hour';$policy=$store->exists($issuer,$key)?$store->get($issuer,$key):'0';
        if(!in_array($policy,['0','1'],true))throw new DomainException('native_task_comment_policy_invalid');
        if($admin||$policy==='0')return true;
        $created=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$comment['created_at']);
        if(!$created||$created->format('Y-m-d H:i:s')!==$comment['created_at'])throw new DomainException('native_task_comment_invalid');
        $now??=time();return $created->getTimestamp()<=$now&&$now-$created->getTimestamp()<=3600;
    }
    public static function assertAllowed($db,string $p,int $issuer,int $actor,array $comment,string $action):void
    {if(!self::allowed($db,$p,$issuer,$actor,$comment,$action))throw new DomainException('native_task_comment_denied');}
}
