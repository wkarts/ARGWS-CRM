<?php

declare(strict_types=1);
require_once __DIR__.'/StaffEmailIdentity.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Transaction boundary for native/API/HR identity writers without company assignments. */
final class StaffIdentityTransaction
{
    public const NATIVE_TABLES = ['staff','staff_permissions','staff_departments','customfields','customfieldsvalues','announcements','dismissed_announcements','departments','roles'];
    private static array $owners = [];

    /** Public errors use a bounded language key; never expose driver or extension text. */
    public static function publicMessage(Throwable $error): string
    {
        $key = match ($error->getMessage()) {
            'issuer_staff_email_conflict', 'issuer_staff_email_invalid',
            'issuer_staff_extension_unreviewed', 'issuer_master_protected',
            'issuer_staff_native_rejected', 'issuer_staff_profile_field_denied' => $error->getMessage(),
            default => 'issuer_staff_write_unconfirmed',
        };
        $fallback = 'The profile could not be saved. Reopen the record and try again.';
        if (!function_exists('_l')) { return $fallback; }
        try {
            $message = _l($key);
            return is_string($message) && trim($message) !== '' && $message !== $key ? $message : $fallback;
        } catch (Throwable $ignored) {
            return $fallback;
        }
    }

    public static function afterCommit($db, callable $effect): void
    {
        if (class_exists('CompanyNativeStaffTransaction', false) && CompanyNativeStaffTransaction::active($db)) {
            CompanyNativeStaffTransaction::afterCommit($db, $effect);
        } elseif (isset(self::$owners[spl_object_id($db)])) {
            self::$owners[spl_object_id($db)]['effects'][] = $effect;
        } else {
            throw new RuntimeException('issuer_owned_transaction_required');
        }
    }

    /** Inspect the error immediately, before another query can replace the driver error. */
    public static function write($db, callable $operation)
    {
        $flags=[];
        foreach (['db_debug','save_queries'] as $flag) {
            if (property_exists($db,$flag)) { $flags[$flag]=$db->$flag; $db->$flag=false; }
        }
        try {
            try { $result=$operation(); }
            catch (Throwable $error) {
                $details=method_exists($db,'error')?$db->error():[];
                self::failure($details, $error);
            }
            if ($result===false) { self::failure(method_exists($db,'error')?$db->error():[]); }
            return $result;
        } finally { foreach($flags as $flag=>$value) { $db->$flag=$value; } }
    }

    private static function failure(array $details, ?Throwable $error=null): void
    {
        $code=(string)($details['code']??'');
        $message=(string)($details['message']??'');
        if ($error instanceof PDOException) {
            $code=(string)($error->errorInfo[1]??$code);
            $message=(string)($error->errorInfo[2]??$message);
        }
        if ($code==='1062' && preg_match('/for key [\x27`](?:[^\x27`]+\.)?'.preg_quote(StaffEmailIdentity::INDEX,'/').'[\x27`]\s*$/i', $message)) {
            throw new DomainException('issuer_staff_email_conflict');
        }
        throw new RuntimeException('issuer_storage_failed');
    }

    public static function run($db, string $prefix, array $tables, callable $operation)
    {
        if (class_exists('CompanyNativeStaffTransaction',false) && CompanyNativeStaffTransaction::active($db)) {
            IssuerTransaction::assertOwned($db,$prefix,$tables);
            return $operation();
        }
        if (isset(self::$owners[spl_object_id($db)])) { throw new RuntimeException('issuer_outer_transaction_not_allowed'); }
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        // Arbitrary extensions can touch non-transactional systems. They require a reviewed participant contract.
        if (function_exists('hooks')) {
            $hooks=hooks();
            foreach (['all','before_create_staff_member','before_update_staff_member','before_staff_update_profile','get_hr_profile_option'] as $tag) {
                if (method_exists($hooks,'has_filter') && $hooks->has_filter($tag)) { throw new DomainException('issuer_staff_extension_unreviewed'); }
            }
        }
        $owned=false;$effects=[];$flags=[];
        foreach(['db_debug','save_queries'] as $flag) { if(property_exists($db,$flag)) { $flags[$flag]=$db->$flag;$db->$flag=false; } }
        try {
            IssuerTransaction::assertIndependent($db);
            $connection=$db->conn_id??$db->pdo??null;
            $driver=$connection instanceof PDO?$connection->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($db->dbdriver??$db->driver??'');
            if ($driver!=='sqlite') {
                $names=array_map(static function($table)use($prefix){if(!preg_match('/^[a-z_]+$/D',$table))throw new InvalidArgumentException('Invalid table');return $prefix.$table;},$tables);
                $rows=IssuerStorage::query($db,'SELECT TABLE_NAME AS table_name,ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($names),'?')).')',$names)->result_array();
                $engines=[];foreach($rows as $row){$engines[$row['table_name']]=strtoupper((string)$row['engine']);}
                foreach($names as $name){if(($engines[$name]??null)!=='INNODB')throw new RuntimeException('issuer_transactional_storage_required');}
            }
            if (!$db->trans_begin()) { throw new RuntimeException('issuer_storage_failed'); }
            $owned=true;self::$owners[spl_object_id($db)]=['effects'=>[]];
            $result=$operation();
            if ($db->trans_status()!==true || !$db->trans_commit()) { throw new RuntimeException('issuer_commit_unconfirmed'); }
            $owned=false;$effects=self::$owners[spl_object_id($db)]['effects'];
        } catch (Throwable $error) {
            if ($owned) { try {$db->trans_rollback();} catch(Throwable $ignored) {} }
            if ($error instanceof DomainException) { throw $error; }
            if (preg_match('/^issuer_[a-z_]+$/D',$error->getMessage())) { throw new RuntimeException($error->getMessage()); }
            throw new RuntimeException('issuer_storage_failed');
        } finally {
            unset(self::$owners[spl_object_id($db)]);
            foreach($flags as $flag=>$value){$db->$flag=$value;}
        }
        foreach($effects as $effect) {
            try { if ($effect() === false) { throw new RuntimeException('issuer_post_commit_failed'); } } catch(Throwable $error) {
                if(function_exists('log_message')) { log_message('error','Staff identity saved; post-commit notification failed.'); }
            }
        }
        return $result;
    }
}
