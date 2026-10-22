<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyEmailTemplateSchema.php';
require_once __DIR__.'/EmailTemplatesPtBr.php';
require_once __DIR__.'/EmailTemplateLocale.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Factory defaults only; never copies primary-company customizations or settings. */
final class CompanyEmailTemplates
{
    public static function currentIssuer(): ?int
    {
        if (class_exists('CompanySettingsRuntime',false) && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary()) return CompanySettingsRuntime::issuerId();
        return null;
    }
    public static function scopeSql($db,string $prefix,string $alias,?int $issuer=null): string
    {
        foreach([$prefix,$alias] as $identifier)if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$identifier))throw new InvalidArgumentException('company_email_template_identifier');
        if (!IssuerStorage::fieldExists($db,'issuer_id',$prefix.'emailtemplates')) {
            if ($issuer!==null) throw new DomainException('company_email_template_schema_required');
            return '1=1';
        }
        if ($issuer===null) return $alias.'.issuer_id IS NULL';
        $issuer=IssuerPolicy::id($issuer);
        if(!IssuerStorage::query($db,'SELECT id FROM '.$prefix.'issuers_registry WHERE id=? AND active=1',[$issuer])->row())throw new DomainException('company_settings_issuer_unavailable');
        if ((new IssuerResourceAccess($db,$prefix))->primaryId()===$issuer) return $alias.'.issuer_id IS NULL';
        return $alias.'.issuer_id='.$issuer;
    }

    /** Origin-bound template lookup for an already authorized request or persisted job.
     * Does not grant access, infer company from an email address, or switch staff context.
     * Every language fallback stays inside the supplied immutable origin.
     */
    public static function findForIssuer($db,string $prefix,int $issuer,string $slug,string $language): ?object
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D',$slug) || !preg_match('/^[a-zA-Z0-9_-]+$/D',$language)) throw new InvalidArgumentException('company_email_template_selector_invalid');
        $scope=self::scopeSql($db,$prefix,'t',$issuer);
        // Read the persisted origin's configured language, never the interactive company's options.
        $activeLanguage = (new CompanySettingsStorage($db, $prefix))->get($issuer, 'active_language');
        return EmailTemplateLocale::select(static function ($candidate) use ($db, $prefix, $slug, $scope) {
            $rows=IssuerStorage::query($db,'SELECT t.* FROM '.$prefix.'emailtemplates t WHERE t.slug=? AND t.language=? AND '.$scope,[$slug,$candidate])->result_array();
            if(count($rows)>1)throw new DomainException('company_email_template_ambiguous');
            return $rows ? (object) $rows[0] : null;
        }, $language, $activeLanguage);
    }

    public static function defaults(): array
    {
        $rows=json_decode(file_get_contents(__DIR__.'/email_templates_ptbr/company_defaults.json'),true,512,JSON_THROW_ON_ERROR);
        $result=[];
        foreach ($rows as $row) {
            $result[]=$row;
            $localized=$row; $localized['language']='portuguese_br';
            $labels=EmailTemplatesPtBr::get($row['slug']);
            if (!$labels) throw new RuntimeException('company_email_template_factory_invalid');
            $localized['name']=$labels['name'];$localized['subject']=$labels['subject'];
            $localized['message']=EmailTemplatesPtBr::translateMessage($row['message']);
            $result[]=$localized;
        }
        return $result;
    }
    public static function seedOwned($db,string $prefix,int $issuer,array $defaults): void
    {
        IssuerTransaction::assertOwned($db,$prefix,['emailtemplates','issuers_registry','issuers_installation_identity']);
        $primary=(new IssuerResourceAccess($db,$prefix))->primaryId();
        if ($primary===null) throw new DomainException('company_settings_primary_unresolved');
        if ($issuer===$primary) return; // Existing primary customizations and language variants are untouched.
        if (!IssuerStorage::query($db,'SELECT id FROM '.$prefix.'issuers_registry WHERE id=?',[$issuer])->row()) throw new DomainException('company_settings_issuer_unavailable');
        $existing=[];
        foreach(IssuerStorage::query($db,'SELECT slug,language FROM '.$prefix.'emailtemplates WHERE issuer_id=?',[$issuer])->result_array() as $stored) {
            $key=$stored['slug']."\0".$stored['language'];$existing[$key]=($existing[$key]??0)+1;
        }
        foreach ($defaults as $row) {
            $count=$existing[$row['slug']."\0".$row['language']]??0;
            if ($count>1) throw new DomainException('company_email_template_ambiguous');
            if ($count===1) continue;
            $row['issuer_id']=$issuer;
            $columns=array_keys($row);
            foreach($columns as $name)if(!preg_match('/^[a-z_]+$/D',$name))throw new RuntimeException('company_email_template_factory_invalid');
            IssuerStorage::query($db,'INSERT INTO '.$prefix.'emailtemplates (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($row));
        }
    }
    public static function reconcile($db,string $prefix): void
    {
        if (!$db->table_exists($prefix.'emailtemplates')) return;
        $defaults=self::defaults();
        IssuerTransaction::run($db,$prefix,['issuers_lock','issuers_registry','issuers_installation_identity','emailtemplates'],static function()use($db,$prefix,$defaults){
            foreach(IssuerStorage::query($db,'SELECT id FROM '.$prefix.'issuers_registry')->result_array() as $row)self::seedOwned($db,$prefix,(int)$row['id'],$defaults);
        });
    }
}
