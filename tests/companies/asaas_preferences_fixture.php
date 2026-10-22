<?php
/** Explicit155 setup for synthetic new-origin fixtures, not a migration or readiness proof. */
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPreferences.php';

/** HTTP fixtures call only this helper after the actual native runner and wallet verification. */
function asaasFixtureSelectEnvironment($db,string $prefix,int $issuer,int $actor,int $account):void
{
    if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('invalid_fixture_prefix');
    $row=$db->query('SELECT environment,revision FROM '.$prefix.'asaas_company_accounts WHERE id=? AND issuer_id=?',[$account,$issuer])->row_array();
    if(!$row)throw new RuntimeException('verified_fixture_account_required');
    $preferences=new \Asaas\CompanyPreferences($db,$prefix);
    $page=$preferences->page($issuer,$actor);
    $preferences->selectEnvironment($issuer,$actor,$row['environment'],$page['revision'],$row['revision']);
}

/** Preserve historical149-153 assertions while making each new issuance satisfy current155. */
function asaasFixtureWithPreferences(int $issuer,int $actor,int $account,callable $operation):mixed
{
    $db=$GLOBALS['db'];$prefix=$GLOBALS['p'];
    // Never synthesize native tracking in the real HTTP fixture database.
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))throw new RuntimeException('synthetic_fixture_database_required');
    $prior=$db->query('SELECT installed_version FROM '.$prefix.'modules WHERE module_name=?',['asaas'])->row()->installed_version;
    \Asaas\CompanyPreferencesSchema::ensure($db,$prefix);
    unset($db->data_cache['table_names']);
    if(version_compare($prior,'1.5.5','<'))$db->query('UPDATE '.$prefix.'modules SET installed_version=? WHERE module_name=?',['1.5.5','asaas']);
    try{
        asaasFixtureSelectEnvironment($db,$prefix,$issuer,$actor,$account);
        return $operation();
    }finally{
        if(version_compare($prior,'1.5.5','<'))$db->query('UPDATE '.$prefix.'modules SET installed_version=? WHERE module_name=?',[$prior,'asaas']);
    }
}

function asaasFixtureIssue(\Asaas\CompanyService $service,...$arguments):int
{
    return asaasFixtureWithPreferences($arguments[0],$arguments[1],$arguments[3],fn()=>$service->issue(...$arguments));
}

function asaasFixtureHosted(\Asaas\CompanyService $service,...$arguments):int
{
    return asaasFixtureWithPreferences($arguments[0],$arguments[1],$arguments[3],fn()=>$service->hostedCheckout(...$arguments));
}
