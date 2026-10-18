<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';

/** Owned transaction boundary, never joins/commits/rolls back a caller transaction. */
final class IssuerTransaction
{
    /** Active callback ownership, not merely the connection's transaction depth. */
    private static array $owners = [];
    public static function assertOwned($db, string $prefix, array $tables): void
    {
        $owner = self::$owners[spl_object_id($db)] ?? null;
        if ($owner === null || $owner['prefix'] !== $prefix || array_diff($tables, $owner['tables'])) {
            throw new RuntimeException('issuer_owned_transaction_required');
        }
    }

    private static function driver($db): string
    {
        $connection=$db->conn_id??$db->pdo??null;
        return $connection instanceof PDO?$connection->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($db->dbdriver??$db->driver??'');
    }
    public static function assertIndependent($db): void
    {
        $connection=$db->conn_id??$db->pdo??null;
        if(property_exists($db,'_trans_depth')) {
            if((new ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0) { throw new RuntimeException('issuer_outer_transaction_not_allowed'); }
        } elseif(!$connection instanceof PDO) { throw new RuntimeException('issuer_transaction_state_unavailable'); }
        if($connection instanceof PDO&&$connection->inTransaction()) { throw new RuntimeException('issuer_outer_transaction_not_allowed'); }
        $driver=self::driver($db);
        if($driver==='sqlite') { return; }
        if(!in_array($driver,['mysql','mysqli'],true)) { throw new RuntimeException('issuer_storage_unsupported'); }
        $mode=IssuerStorage::query($db,'SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type')->result_array()[0]??[];
        if((string)($mode['autocommit']??'')!=='1'||!in_array((string)($mode['completion_type']??''),['0','NO_CHAIN'],true)) { throw new RuntimeException('issuer_autocommit_required'); }
        IssuerStorage::query($db,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    }
    public static function run($db,string $prefix,array $suffixes,callable $operation)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $flags=[];foreach(['db_debug','save_queries'] as$flag){if(property_exists($db,$flag)){$flags[$flag]=$db->$flag;$db->$flag=false;}}
        $owned=false;
        try {
            self::assertIndependent($db);
            if(self::driver($db)!=='sqlite') {
                $tables=array_map(static function($suffix)use($prefix){if(!preg_match('/^[a-z_]+$/D',$suffix))throw new InvalidArgumentException('Invalid table');return $prefix.$suffix;},$suffixes);
                $rows=IssuerStorage::query($db,'SELECT TABLE_NAME AS table_name,ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')',$tables)->result_array();
                $engines=[];foreach($rows as$row){$engines[$row['table_name']]=strtoupper((string)$row['engine']);}
                foreach($tables as$table){if(($engines[$table]??null)!=='INNODB')throw new RuntimeException('issuer_transactional_storage_required');}
            }
            if(!$db->trans_begin())throw new RuntimeException('issuer_storage_failed');$owned=true;
            if(!IssuerStorage::query($db,'SELECT id FROM '.$prefix.'issuers_lock WHERE id=1 FOR UPDATE')->result_array())throw new RuntimeException('issuer_storage_failed');
            self::$owners[spl_object_id($db)] = ['prefix'=>$prefix,'tables'=>$suffixes];
            try { $result=$operation(); }
            finally { unset(self::$owners[spl_object_id($db)]); }

            if($db->trans_status()!==true||!$db->trans_commit())throw new RuntimeException('issuer_commit_unconfirmed');
            $owned=false;self::assertIndependent($db);return $result;
        }catch(Throwable $error){if($owned){try{$db->trans_rollback();}catch(Throwable $ignored){}}if($error instanceof DomainException)throw$error;if(preg_match('/^issuer_[a-z_]+$/D',$error->getMessage()))throw new RuntimeException($error->getMessage());throw new RuntimeException('issuer_storage_failed');}
        finally{foreach($flags as$flag=>$value){$db->$flag=$value;}}
    }
}
