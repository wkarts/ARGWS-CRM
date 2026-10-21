<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerTransaction.php';
require_once __DIR__.'/IssuerResourceAccess.php';

/** One-time preservation of the pre-core installation, never a request-time grant fallback. */
final class IssuerLegacyAccess
{
    private static function rows($db,string $sql,array $bindings=[]): array
    {
        return IssuerStorage::query($db,$sql,$bindings)->result_array();
    }
    public static function catalog(): array
    {
        $catalog=json_decode(file_get_contents(__DIR__.'/legacy-resource-catalog.json'),true,512,JSON_THROW_ON_ERROR);
        if(($catalog['schema']??null)!==1||!is_array($catalog['components']??null))throw new RuntimeException('issuer_legacy_catalog_invalid');
        foreach($catalog['components'] as$component=>$features){
            IssuerResourceAccess::key($component);
            foreach($features as$feature=>$caps){IssuerResourceAccess::key($feature);foreach($caps as$cap){IssuerResourceAccess::key($cap);}}
        }
        return $catalog['components'];
    }
    /** Exact capabilities exposed by this code baseline; active module state is captured in the transaction. */
    public static function effectiveCatalog(array $activeModules): array
    {
        $catalog=self::catalog();$result=$catalog['core'];
        foreach($activeModules as$module){foreach($catalog[$module]??[] as$feature=>$caps){$result[$feature]=array_values(array_unique(array_merge($result[$feature]??[],$caps)));}}
        ksort($result);foreach($result as&$caps){sort($caps);}unset($caps);return $result;
    }
    public static function apply($db,string $prefix): void
    {
        $tables=['staff','staff_permissions','modules','issuers_lock','issuers_registry','issuers_staff','issuers_staff_defaults','issuers_resource_permissions','issuers_installation_identity','issuers_audit'];
        IssuerTransaction::run($db,$prefix,$tables,static function()use($db,$prefix):void{
            $p=$prefix.'issuers_';
            $identity=self::rows($db,"SELECT primary_issuer_id,legacy_access_migrated_at FROM {$p}installation_identity WHERE id=1 FOR UPDATE")[0]??[];
            // A committed marker is authoritative. Never regrant after revocation or enroll a later employee.
            if(!empty($identity['legacy_access_migrated_at']))return;
            $primary=IssuerPolicy::id($identity['primary_issuer_id']??null);
            $staff=self::rows($db,"SELECT staffid,active,admin FROM {$prefix}staff ORDER BY staffid FOR UPDATE");
            $permissions=self::rows($db,"SELECT staff_id,feature,capability FROM {$prefix}staff_permissions ORDER BY staff_id,feature,capability FOR UPDATE");
            $activeModules=array_column(self::rows($db,"SELECT module_name FROM {$prefix}modules WHERE active=1 ORDER BY module_name FOR UPDATE"),'module_name');
            $catalog=self::effectiveCatalog($activeModules);$byStaff=[];
            foreach($permissions as$row){$feature=IssuerResourceAccess::key($row['feature']);$cap=IssuerResourceAccess::key($row['capability']);$byStaff[(int)$row['staff_id']][$feature][$cap]=true;}
            $cutoff=0;$migrated=[];
            foreach($staff as$row){
                $id=IssuerPolicy::id($row['staffid']);$cutoff=max($cutoff,$id);
                if((int)$row['active']!==1)continue;
                $existing=self::rows($db,"SELECT can_view FROM {$p}staff WHERE issuer_id=? AND staff_id=? FOR UPDATE",[$primary,$id]);
                // Preserve an explicit pre-existing denial rather than silently turning it into a grant.
                if($existing&&(int)$existing[0]['can_view']!==1)continue;
                if(!$existing)IssuerStorage::query($db,"INSERT INTO {$p}staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,?,1,0,0,0)",[$primary,$id]);
                $grants=$byStaff[$id]??[];
                if((int)$row['admin']===1){foreach($catalog as$feature=>$caps){foreach($caps as$cap){$grants[$feature][$cap]=true;}}}
                // Snapshot existing principal-only administrative compatibility; never a master grant.
                if((int)$row['admin']===1){$grants['core_legacy_administration']['access']=true;}
                foreach($grants as$feature=>$caps){foreach($caps as$cap=>$enabled){IssuerStorage::query($db,"INSERT IGNORE INTO {$p}resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)",[$primary,$id,$feature,$cap]);}}
                if(!self::rows($db,"SELECT staff_id FROM {$p}staff_defaults WHERE staff_id=? FOR UPDATE",[$id]))IssuerStorage::query($db,"INSERT INTO {$p}staff_defaults(staff_id,issuer_id) VALUES(?,?)",[$id,$primary]);
                $migrated[]=$id;
            }
            $now=date('Y-m-d H:i:s');$hash=hash('sha256',json_encode($catalog,JSON_THROW_ON_ERROR));
            IssuerStorage::query($db,"UPDATE {$p}installation_identity SET legacy_access_migrated_at=?,legacy_staff_cutoff=?,legacy_catalog_hash=? WHERE id=1 AND legacy_access_migrated_at IS NULL",[$now,$cutoff,$hash]);
            $confirmed=self::rows($db,"SELECT legacy_access_migrated_at,legacy_staff_cutoff,legacy_catalog_hash FROM {$p}installation_identity WHERE id=1")[0]??[];
            if(($confirmed['legacy_access_migrated_at']??null)!==$now||(int)($confirmed['legacy_staff_cutoff']??-1)!==$cutoff||($confirmed['legacy_catalog_hash']??null)!==$hash)throw new RuntimeException('issuer_legacy_access_unconfirmed');
            IssuerStorage::query($db,"INSERT INTO {$p}audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(0,'legacy_primary_access_preserved',?,NULL,?,?)",[$primary,$now,json_encode(['staff_ids'=>$migrated,'staff_cutoff'=>$cutoff,'catalog_sha256'=>$hash,'active_modules'=>$activeModules],JSON_THROW_ON_ERROR)]);
        });
    }
}
