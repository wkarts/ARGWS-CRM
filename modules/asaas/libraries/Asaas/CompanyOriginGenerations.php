<?php
declare(strict_types=1);
namespace Asaas;
/** Current selection is separate from the immutable remote identity and its history. */
final class CompanyOriginGenerations
{
    public static function predicate($db,string $p,string $alias=''):string
    {
        if($alias!==''&&!preg_match('/^[a-z_]+\.$/D',$alias))throw new \InvalidArgumentException('asaas_origin_alias_invalid');
        return $db->field_exists('active_slot',$p.'asaas_payment_origins')?' AND '.$alias.'active_slot=1':'';
    }
    public static function assertActive(array $row):void
    {
        if(array_key_exists('active_slot',$row)&&$row['active_slot']!==1&&$row['active_slot']!=='1')throw new \DomainException('asaas_company_origin_retired');
    }
    /** Validate every retired predecessor before a post-refund writer accepts the active origin.
     * The caller owns the native financial transaction and keeps all ordinary refund guards.
     */
    public static function postRefundOriginInOwned($db,string $p,int $issuer,int $invoice,int $origin):array
    {
        $rows=static fn(string $sql,array $args=[]):array=>\IssuerStorage::query($db,$sql,$args)->result_array();
        $tables=['asaas_payment_origins','asaas_company_origin_meta','asaas_company_proofs','asaas_company_events','asaas_company_receipts','asaas_company_jobs','asaas_company_refund_requests'];
        if($db->table_exists($p.'asaas_company_recreations'))$tables[]='asaas_company_recreations';
        \IssuerTransaction::assertOwned($db,$p,$tables);
        $all=$rows('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$invoice]);
        $byId=[];foreach($all as $row)$byId[(int)$row['id']]=$row;
        $active=$byId[$origin]??null;
        if(!$active||(int)$active['issuer_id']!==$issuer)throw new \DomainException('company_post_refund_adverse_history');
        self::assertActive($active);
        if(count($all)===1)return $active;
        $version=$rows('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.6.7','<'))throw new \DomainException('company_post_refund_generation_upgrade_required');
        if(!array_key_exists('active_slot',$active)||!$db->table_exists($p.'asaas_company_recreations'))throw new \DomainException('company_post_refund_adverse_history');
        $cursor=$origin;$seen=[$origin];
        while($links=$rows('SELECT * FROM '.$p.'asaas_company_recreations WHERE successor_origin_id=? FOR UPDATE',[$cursor])){
            if(count($links)!==1)throw new \DomainException('company_post_refund_adverse_history');
            $link=$links[0];$previous=(int)$link['origin_id'];$old=$byId[$previous]??null;
            if(!$old||in_array($previous,$seen,true)||$link['state']!=='reopened'||(int)$link['issuer_id']!==$issuer||(int)$link['invoice_id']!==$invoice||$old['active_slot']!==null||$old['state']!=='remote_removed')throw new \DomainException('company_post_refund_adverse_history');
            foreach(['issuer_id','invoice_id','provider','account_id','environment','channel','invoice_hash'] as $field)
                if((string)$old[$field]!== (string)$active[$field])throw new \DomainException('company_post_refund_adverse_history');
            $meta=$rows('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$previous])[0]??null;
            $nextMeta=$rows('SELECT account_row,request_key FROM '.$p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$cursor])[0]??null;
            if(!$meta||!$nextMeta||$meta['account_row']!==$nextMeta['account_row']||$nextMeta['request_key']!==$link['request_key'])throw new \DomainException('company_post_refund_adverse_history');
            require_once __DIR__.'/CompanyInvoiceEditJournal.php';
            [$effectiveOld]=CompanyInvoiceEditJournal::effective($db,$p,$old,$meta);
            foreach(['invoice_total','invoice_due_date'] as $field)
                if((string)$effectiveOld[$field]!== (string)$byId[$cursor][$field])throw new \DomainException('company_post_refund_adverse_history');
            $proof=$rows('SELECT * FROM '.$p.'asaas_company_proofs WHERE id=? AND origin_id=? FOR UPDATE',[$link['proof_id'],$previous])[0]??null;
            if(!$proof||$proof['operation']!=='query'||$proof['account_revision']!==$link['account_revision'])throw new \DomainException('company_post_refund_adverse_history');
            $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
            if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))||($safe['id']??null)!==$old['remote_id']||($safe['deleted']??false)!==true||($safe['adverse']??true)||!in_array($safe['status']??'', ['PENDING','OVERDUE'],true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!=='')throw new \DomainException('company_post_refund_adverse_history');
            foreach($rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$meta['account_row'],$old['remote_id']]) as $event)
                if($event['created_at']>$proof['created_at']||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'],true)||in_array($event['state'],['conflict','review_required'],true))throw new \DomainException('company_post_refund_adverse_history');
            if($rows('SELECT request_key FROM '.$p.'asaas_company_refund_requests WHERE origin_id=? FOR UPDATE',[$previous])||$rows('SELECT payment_id FROM '.$p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$previous])||$rows("SELECT event_id FROM {$p}asaas_company_jobs WHERE origin_id=? AND state IN ('pending','processing') FOR UPDATE",[$previous]))throw new \DomainException('company_post_refund_adverse_history');
            $seen[]=$previous;$cursor=$previous;
        }
        if(count($seen)!==count($all))throw new \DomainException('company_post_refund_adverse_history');
        return $active;
    }
    /** MySQL native upgrade. Add replacement uniqueness before removing the old constraint. */
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $connection=$db->pdo??$db->conn_id??null;
        if($create&&(($connection instanceof \PDO&&$connection->inTransaction())||(property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)>0)))throw new \RuntimeException('asaas_generation_outer_transaction_denied');
        $t=$p.'asaas_payment_origins';
        if($create&&!$db->field_exists('active_slot',$t)){\IssuerStorage::query($db,'ALTER TABLE `'.$t.'` ADD COLUMN active_slot TINYINT UNSIGNED NULL DEFAULT 1');if(isset($db->data_cache['field_names'][$t]))unset($db->data_cache['field_names'][$t]);}
        $col=\IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$t,'active_slot'])->row_array();
        if(preg_replace('/tinyint\([0-9]+\)/','tinyint',strtolower($col['COLUMN_TYPE']??''))!=='tinyint unsigned'||($col['IS_NULLABLE']??'')!=='YES'||(string)($col['COLUMN_DEFAULT']??'')!=='1')throw new \RuntimeException('asaas_generation_schema_invalid');
        $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $row)$indexes[$row['INDEX_NAME']][]=$row;
        $name='asaas_origin_active_uq';
        if($create&&!isset($indexes[$name])){\IssuerStorage::query($db,'ALTER TABLE `'.$t.'` ADD UNIQUE KEY `'.$name.'` (invoice_id,active_slot)');self::ensure($db,$p,true);return;}
        $active=$indexes[$name]??[];
        if(array_column($active,'COLUMN_NAME')!==['invoice_id','active_slot']||array_filter($active,fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new \RuntimeException('asaas_generation_schema_invalid');
        foreach($indexes as $index=>$rows)if(array_column($rows,'COLUMN_NAME')===['invoice_id']&&(int)$rows[0]['NON_UNIQUE']===0){
            if(!$create||!preg_match('/^[A-Za-z0-9_]+$/D',$index))throw new \RuntimeException('asaas_generation_schema_invalid');
            \IssuerStorage::query($db,'ALTER TABLE `'.$t.'` DROP INDEX `'.$index.'`');
        }
        if(\IssuerStorage::query($db,'SELECT id FROM `'.$t.'` WHERE active_slot IS NOT NULL AND active_slot<>1 LIMIT 1')->row_array())throw new \RuntimeException('asaas_generation_schema_invalid');
    }
}
