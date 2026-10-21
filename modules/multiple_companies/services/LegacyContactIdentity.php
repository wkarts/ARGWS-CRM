<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Preserve the legacy same-email feature without crossing private customer ownership. */
final class MultipleCompaniesLegacyContactIdentity
{
    /** Fresh legacy-only switch targets. Session display caches are never authority. */
    public static function switchTargets($db, string $prefix, $contact, $client): array
    {
        foreach ([$contact,$client] as $id) if ((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)) return [];
        $source=\app\services\ClientIssuerScope::legacySql($db,$prefix,'source_contact');
        $target=\app\services\ClientIssuerScope::legacySql($db,$prefix,'target_contact');
        $rows=$db->query('SELECT target_contact.id,target_contact.userid,target_client.company FROM '.$prefix.'contacts AS source_contact INNER JOIN '.$prefix.'clients AS source_client ON source_client.userid=source_contact.userid INNER JOIN '.$prefix.'contacts AS target_contact ON target_contact.email=source_contact.email INNER JOIN '.$prefix.'clients AS target_client ON target_client.userid=target_contact.userid WHERE source_contact.id=? AND source_contact.userid=? AND source_contact.email<>\'\' AND source_contact.active=1 AND source_client.active=1 AND target_contact.active=1 AND target_client.active=1 AND '.$source.' AND '.$target.' ORDER BY target_client.company,target_contact.id',[$contact,$client]);
        return $rows===false ? [] : $rows->result_array();
    }

    public static function syncPassword($db,string $prefix,$contact): bool
    {
        if ((!is_int($contact)&&!is_string($contact))||!preg_match('/^[1-9][0-9]*$/D',(string)$contact))return false;
        // Shared storage boundary disables SQL/binding logging while password hashes are handled.
        require_once APPPATH.'services/issuers/IssuerStorage.php';
        $scope=\app\services\ClientIssuerScope::legacySql($db,$prefix,$prefix.'contacts');
        $row=IssuerStorage::query($db,'SELECT userid,password,email FROM '.$prefix.'contacts WHERE id=? AND '.$scope,[$contact])->row();
        if(!$row||!is_string($row->password)||$row->password===''||!is_string($row->email)||$row->email==='')return false;
        $sourceScope=$db->table_exists($prefix.'issuers_clients')
            ? 'NOT EXISTS (SELECT 1 FROM '.$prefix.'issuers_clients source_owner WHERE source_owner.client_id=?)' : '1=1';
        $bindings=[$row->password,date('Y-m-d H:i:s'),$row->email];
        if($sourceScope!=='1=1')$bindings[]=$row->userid;
        IssuerStorage::query($db,'UPDATE '.$prefix.'contacts SET password=?,last_password_change=? WHERE email=? AND '.$scope.' AND '.$sourceScope,$bindings);
        return true;
    }
}
