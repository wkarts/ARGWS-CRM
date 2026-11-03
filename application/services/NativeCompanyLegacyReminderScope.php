<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Ownership fence before legacy cron hydrates a relation through global models. */
final class NativeCompanyLegacyReminderScope
{
    public static function permits($db,string $p,array $reminder):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $acl=new IssuerResourceAccess($db,$p);$primary=$acl->primaryId();
        if($primary===null||!$acl->member($primary,(int)($reminder['staff']??0)))return false;
        $type=(string)($reminder['rel_type']??'');$id=$reminder['rel_id']??null;
        if((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id)||(float)$id>=4294967296)return false;
        $aliases=['customers'=>'client','customer'=>'client','contacts'=>'contact','contracts'=>'contract','expenses'=>'expense','leads'=>'lead','tasks'=>'task','projects'=>'project'];$type=$aliases[$type]??$type;
        $tables=['invoice'=>['invoices','id','invoices'],'estimate'=>['estimates','id','estimates'],'proposal'=>['proposals','id','proposals'],'expense'=>['expenses','id','expenses'],'credit_note'=>['creditnotes','id','credit_notes'],'project'=>['projects','id','projects'],'task'=>['tasks','id','tasks'],'ticket'=>['tickets','ticketid','tickets'],'contract'=>['contracts','id','contracts'],'lead'=>['leads','id','leads'],'client'=>['clients','userid','customers'],'contact'=>['contacts','id','customers']];
        if(!isset($tables[$type]))return false;
        [$table,$key,$feature]=$tables[$type];if(!$acl->can($primary,(int)$reminder['staff'],$feature,'view'))return false;
        // issuer columns are authoritative: the legacy worker never handles named records.
        $where=IssuerStorage::fieldExists($db,'issuer_id',$p.$table)?' AND issuer_id IS NULL':'';
        $row=IssuerStorage::query($db,"SELECT * FROM {$p}{$table} WHERE {$key}=?".$where,[$id])->row_array();if(!$row)return false;
        if($type==='client'||$type==='contact'){
            $client=$type==='client'?$row['userid']:$row['userid'];
            $owner=IssuerStorage::query($db,'SELECT owner_issuer_id FROM '.$p.'issuers_clients WHERE client_id=?',[$client])->row_array();
            if($owner&&$owner['owner_issuer_id']!==null)return false;
        }
        return $acl->can($primary,(int)$reminder['staff'],$feature,'view');
    }
}
