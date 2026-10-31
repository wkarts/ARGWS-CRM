<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerSchema.php';
require_once __DIR__.'/IssuerCoreSchema.php';
require_once __DIR__.'/IssuerAccessSchema.php';
require_once __DIR__.'/IssuerFinancialSchema.php';
require_once __DIR__.'/IssuerLegacyAccess.php';
require_once __DIR__.'/IssuerRegistry.php';
require_once __DIR__.'/IssuerTransaction.php';
require_once __DIR__.'/IssuerInstallationIdentity.php';
require_once dirname(__DIR__).'/CompanyMailQueueSchema.php';
require_once dirname(__DIR__).'/CompanyEmailTemplates.php';

/** Native company foundation. Only controlled core install/upgrade may call reconcile. */
final class IssuerCore
{
    private static ?WeakMap $metadata=null;
    private static function rows($db,string $sql,array $params=[]): array { return IssuerStorage::query($db,$sql,$params)->result_array(); }
    public static function invalidate($db): void
    {
        if(self::$metadata!==null&&isset(self::$metadata[$db]))unset(self::$metadata[$db]);
        IssuerCoreSchema::invalidateSchemaCache($db);
    }
    private static function columns($db,string $table): array
    {
        $columns=[];
        foreach(self::rows($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]) as$row){$columns[$row['COLUMN_NAME']]=$row;}
        return $columns;
    }
    private static function assertMetadata($db,string $prefix): void
    {
        self::$metadata??=new WeakMap();$valid=self::$metadata[$db]??[];
        if(isset($valid[$prefix]))return;
        IssuerCoreSchema::assertSchema($db,$prefix);IssuerAccessSchema::assertValid($db,$prefix);IssuerFinancialSchema::assertValid($db,$prefix);
        foreach(['registry'=>['tax_id'=>['varchar(14)','YES'],'identity_status'=>['varchar(16)','NO']],'installation_identity'=>['legacy_identity_json'=>['longtext','YES'],'legacy_access_migrated_at'=>['datetime','YES'],'legacy_staff_cutoff'=>['int','YES'],'legacy_catalog_hash'=>['varchar(64)','YES'],'legacy_resource_permissions_migrated_at'=>['datetime','YES']],'audit'=>['metadata_json'=>['longtext','YES']]] as$suffix=>$fields){
            $columns=self::columns($db,$prefix.'issuers_'.$suffix);
            foreach($fields as$name=>[$type,$nullable]){if(preg_replace('/^int\([0-9]+\)/','int',strtolower((string)($columns[$name]['COLUMN_TYPE']??'')))!==$type||($columns[$name]['IS_NULLABLE']??null)!==$nullable)throw new RuntimeException('issuer_core_schema_invalid');}
        }
        $valid[$prefix]=true;self::$metadata[$db]=$valid;
    }
    /** Read-only; no module activation, DDL, session mutation or inferred master. */
    public static function ready($db,string $prefix): bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))return false;
        $flags=[];foreach(['db_debug','save_queries'] as$flag){if(property_exists($db,$flag)){$flags[$flag]=$db->$flag;$db->$flag=false;}}
        try {
            self::assertMetadata($db,$prefix);
            if(!self::rows($db,'SELECT id FROM '.$prefix.'issuers_lock WHERE id=1'))return false;
            return count(self::rows($db,"SELECT r.id FROM {$prefix}issuers_installation_identity i JOIN {$prefix}issuers_registry r ON r.id=i.primary_issuer_id WHERE i.id=1 AND i.legacy_access_migrated_at IS NOT NULL AND r.active=1 AND r.identity_status IN ('pending','complete')"))===1;
        }catch(Throwable $error){return false;}
        finally{foreach($flags as$flag=>$value){$db->$flag=$value;}}
    }
    public static function reconcile($db,string $prefix): void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $flags=[];foreach(['db_debug','save_queries'] as$flag){if(property_exists($db,$flag)){$flags[$flag]=$db->$flag;$db->$flag=false;}}
        try {
            IssuerTransaction::assertIndependent($db);self::invalidate($db);
            foreach(IssuerSchema::statements($prefix) as$sql){IssuerStorage::query($db,$sql);}
            IssuerSchema::ensureIntegrationPermissions($db,$prefix);
            IssuerAccessSchema::ensure($db,$prefix);
            IssuerFinancialSchema::ensure($db,$prefix);
            CompanyMailQueueSchema::ensure($db,$prefix);
            CompanyEmailTemplateSchema::ensure($db,$prefix);
            $registry=$prefix.'issuers_registry';$columns=self::columns($db,$registry);
            if(strtolower((string)($columns['tax_id']['COLUMN_TYPE']??''))!=='varchar(14)')throw new RuntimeException('issuer_core_schema_invalid');
            if(($columns['tax_id']['IS_NULLABLE']??null)==='NO'){IssuerStorage::query($db,"ALTER TABLE `{$registry}` MODIFY `tax_id` varchar(14) DEFAULT NULL");}
            elseif(($columns['tax_id']['IS_NULLABLE']??null)!=='YES')throw new RuntimeException('issuer_core_schema_invalid');
            if(!isset($columns['identity_status'])){IssuerStorage::query($db,"ALTER TABLE `{$registry}` ADD `identity_status` varchar(16) NOT NULL DEFAULT 'complete'");}
            $identity=$prefix.'issuers_installation_identity';$columns=self::columns($db,$identity);
            if(!isset($columns['legacy_identity_json'])){IssuerStorage::query($db,"ALTER TABLE `{$identity}` ADD `legacy_identity_json` longtext DEFAULT NULL");}
            foreach(['legacy_access_migrated_at'=>'datetime','legacy_staff_cutoff'=>'int','legacy_catalog_hash'=>'varchar(64)','legacy_resource_permissions_migrated_at'=>'datetime'] as $name=>$type){
                if(!isset($columns[$name]))IssuerStorage::query($db,"ALTER TABLE `{$identity}` ADD `{$name}` {$type} DEFAULT NULL");
            }
            self::invalidate($db);self::assertMetadata($db,$prefix);
            self::ensurePrincipal($db,$prefix);
            CompanyEmailTemplates::reconcile($db,$prefix);
            IssuerLegacyAccess::apply($db,$prefix);
            IssuerLegacyAccess::preserveLegacyResourcePermissions($db,$prefix);
            // Keep historical version/data for compatibility; the loader always excludes this retired module.
            if($db->table_exists($prefix.'modules')){IssuerStorage::query($db,"UPDATE {$prefix}modules SET active=0 WHERE module_name='issuers'");}
            if(!self::ready($db,$prefix))throw new RuntimeException('issuer_core_reconcile_unconfirmed');
        }catch(Throwable $error){if($error instanceof DomainException)throw$error;if(preg_match('/^issuer_[a-z_]+$/D',$error->getMessage()))throw new RuntimeException($error->getMessage());throw new RuntimeException('issuer_core_reconcile_failed');}
        finally{foreach($flags as$flag=>$value){$db->$flag=$value;}}
    }
    private static function ensurePrincipal($db,string $prefix): void
    {
        $p=$prefix.'issuers_';
        IssuerTransaction::run($db,$prefix,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_audit','options'],static function()use($db,$prefix,$p):void{
            $identity=self::rows($db,"SELECT * FROM {$p}installation_identity WHERE id=1 FOR UPDATE")[0]??null;
            if($identity&&$identity['primary_issuer_id']!==null){
                if(!self::rows($db,"SELECT id FROM {$p}registry WHERE id=? AND active=1",[$identity['primary_issuer_id']]))throw new DomainException('issuer_primary_identity_unavailable');
                return;
            }
            $raw=['invoice_company_name'=>'','companyname'=>'','company_vat'=>''];
            foreach(self::rows($db,"SELECT name,value FROM {$prefix}options WHERE name IN (?,?,?) FOR UPDATE",array_keys($raw)) as$row){$raw[$row['name']]=(string)$row['value'];}
            $name=trim($raw['invoice_company_name'])!==''?trim($raw['invoice_company_name']):trim($raw['companyname']);
            $validName=$name!==''&&strlen($name)<=191&&!preg_match('/[\x00-\x1f]/',$name);
            try{$tax=IssuerPolicy::taxId($raw['company_vat']);}catch(DomainException $error){$tax=null;}
            $existing=$tax===null?null:(self::rows($db,"SELECT id FROM {$p}registry WHERE tax_id=? FOR UPDATE",[$tax])[0]??null);
            $now=date('Y-m-d H:i:s');
            if($existing){$issuer=(int)$existing['id'];}
            else{
                IssuerStorage::query($db,"INSERT INTO {$p}registry(legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(?,?,1,'{}',0,?,?)",[$validName?$name:'Empresa principal',$tax,$now,$validName&&$tax!==null?'complete':'pending']);
                $issuer=(int)$db->insert_id();if($issuer<1)throw new RuntimeException('issuer_primary_write_unconfirmed');
            }
            $snapshot=json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if(!$identity){IssuerStorage::query($db,"INSERT INTO {$p}installation_identity(id,master_staff_id,primary_issuer_id,source,resolved_at,legacy_identity_json) VALUES(1,NULL,?,'legacy_principal',NULL,?)",[$issuer,$snapshot]);}
            else{IssuerStorage::query($db,"UPDATE {$p}installation_identity SET primary_issuer_id=?,legacy_identity_json=? WHERE id=1 AND primary_issuer_id IS NULL",[$issuer,$snapshot]);}
            $confirmed=self::rows($db,"SELECT primary_issuer_id FROM {$p}installation_identity WHERE id=1")[0]??[];
            if((int)($confirmed['primary_issuer_id']??0)!==$issuer)throw new RuntimeException('issuer_primary_write_unconfirmed');
            IssuerStorage::query($db,"INSERT INTO {$p}audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(0,'legacy_principal_bound',?,NULL,?)",[$issuer,$now]);
        });
    }
    public static function bindInstallationMaster($db,string $prefix,$staffId): void
    {
        if(!self::ready($db,$prefix))throw new RuntimeException('issuer_core_not_ready');
        IssuerInstallationIdentity::bindInstallationMaster($db,$prefix,$staffId);
    }
}
