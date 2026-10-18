<?php
declare(strict_types=1);
namespace BancoBrasil;
final class Transaction
{
    public static function run($db, string $prefix, array $tables, callable $work)
    {
        return self::quiet($db,fn()=>self::owned($db,$prefix,$tables,$work));
    }
    /** Covers reads as well as writes; always restore caller diagnostics, including failures. */
    public static function quiet($db,callable $work)
    {
        // CI must return query failures to this boundary, not render SQL/payload in the response.
        $restore=[];
        foreach(['db_debug','save_queries'] as $property){
            if(property_exists($db,$property)){$restore[$property]=$db->$property;$db->$property=false;}
        }
        try{return $work();}
        finally{foreach($restore as $property=>$value)$db->$property=$value;}
    }
    private static function owned($db, string $prefix, array $tables, callable $work)
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/D',$prefix)) throw new \InvalidArgumentException('bb_prefix_invalid');
        if (!property_exists($db,'_trans_depth') || (new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0
            || (($db->conn_id??null) instanceof \PDO && $db->conn_id->inTransaction())) throw new \RuntimeException('bb_outer_transaction_not_allowed');
        $result=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state=$result?$result->row_array():[];
        if ((string)($state['autocommit']??'')!=='1' || !in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true)) throw new \RuntimeException('bb_transaction_configuration_invalid');
        foreach($tables as $table){
            $r=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.$table]);
            if (!$r || strtoupper((string)($r->row_array()['ENGINE']??''))!=='INNODB') throw new \RuntimeException('bb_innodb_required');
        }
        if (!$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || !$db->trans_begin()) throw new \RuntimeException('bb_transaction_unavailable');
        $owned=true;
        try {
            $result=$work();
            if (!$db->trans_status() || !$db->trans_commit()) throw new \RuntimeException('bb_commit_unconfirmed');
            $owned=false;
            return $result;
        } catch (\Throwable $error) {
            if($owned)$db->trans_rollback();
            throw $error;
        }
    }
}
