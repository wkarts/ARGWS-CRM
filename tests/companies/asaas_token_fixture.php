<?php
/** Prepare synthetic receiver tokens without advancing historical financial migration checkpoints. */
function asaasFixtureRotateWebhook(\Asaas\CompanyService $service,$issuer,$actor,$account,string $revision):array
{
    $db=$GLOBALS['db'];$p=$GLOBALS['p'];
    $prior=$db->query('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row()->installed_version;
    if(version_compare($prior,'1.5.4','>='))return $service->rotateWebhook($issuer,$actor,$account,$revision);
    $db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.4','asaas']);
    try{return $service->rotateWebhook($issuer,$actor,$account,$revision);}
    finally{$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',[$prior,'asaas']);}
}
