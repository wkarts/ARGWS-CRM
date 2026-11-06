<?php
/** Actual discovery controller and real issuer ACL; framework, DNS and HTTPS transport are explicit seams. */
require __DIR__.'/pige_bridge_connections_mysql.php';
require_once __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeDiscovery.php';
$start=$GLOBALS['essentialChecks'];
$db->query('INSERT INTO '.$p.'modules(module_name,installed_version,active) VALUES(?,?,1)',['pige_bridge','1.0.9']);
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,'settings','edit']);
$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$uiActor=12;$uiAdmin=true;$discoveryCalls=0;$duringDiscovery=null;
$schoolId='12345678-1234-1234-1234-123456789abc';
class DiscoverySettingsFixture extends Settings
{
    public $config;
    protected function discoveryService(callable $authorize):PigeBridgeDiscovery
    {
        return new PigeBridgeDiscovery($authorize,function($url,$credential){
            $GLOBALS['discoveryCalls']++;
            if($GLOBALS['duringDiscovery']!==null)($GLOBALS['duringDiscovery'])();
            return ['status'=>200,'body'=>json_encode(['configuration'=>['version'=>3,'enabled'=>true,'base_url'=>'https://crm.example','issuer_id'=>(string)$GLOBALS['a'],'account_id'=>'owned-account','environment'=>'sandbox','secret_configured'=>true,'secret'=>'remote secret must not return']])];
        },fn()=>['8.8.8.8']);
    }
}
$controller=new DiscoverySettingsFixture();$controller->config=new class{function item($name){return 'https://crm.example/';}};
$controller->output=new class{public $status=200,$body='';public array $headers=[];function set_header($value){$this->headers[]=$value;return $this;}function set_status_header($value){$this->status=$value;return $this;}function set_content_type($value){return $this;}function set_output($value){$this->body=$value;return $this;}};
$payload=['pige_csrf'=>str_repeat('a',64),'issuer_id'=>(string)$a,'discovery_action'=>'configuration','pige_url'=>'https://pige.example','admin_credential'=>'synthetic.header.signature','school_id'=>$schoolId];
$controller->input->values=$payload;$before=$db->query('SELECT * FROM '.$p.'pige_bridge_connections ORDER BY connection_key')->result_array();$invoices=$db->query('SELECT * FROM '.$p.'invoices')->result_array();$audit=$db->query('SELECT * FROM '.$p.'issuers_audit')->result_array();
$controller->discover();$reply=json_decode($controller->output->body,true,32,JSON_THROW_ON_ERROR);
essentialCheck($controller->output->status===200&&$reply['success']&&$reply['configuration']['account_id']==='owned-account'&&$discoveryCalls===1,'real company ACL permits only compatible discovery');
essentialCheck(!str_contains($controller->output->body,'synthetic.header.signature')&&!str_contains($controller->output->body,'remote secret')&&!isset($reply['configuration']['enabled']),'actual controller returns minimal public scope without credentials/consent');
$_SERVER['HTTPS']='off';$_SERVER['SERVER_PORT']='80';$_SERVER['HTTP_X_FORWARDED_PROTO']='https';$controller->discover();essentialCheck($controller->output->status===422&&$discoveryCalls===1,'forged forwarded HTTPS header does not authorize administrative credential transport');
$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='443';
$controller->input->values['issuer_id']=(string)$b;$controller->discover();essentialCheck($controller->output->status===422&&$discoveryCalls===1,'another company without settings permission denied before transport');
$controller->input->values=$payload;$duringDiscovery=function()use($db,$p,$a){$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=?',[$a,'settings']);};
$controller->discover();essentialCheck($controller->output->status===422&&$discoveryCalls===2&&!str_contains($controller->output->body,'owned-account'),'SQL revocation during remote request discards configuration');
$duringDiscovery=null;$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,'settings','edit']);
$db->query('UPDATE '.$p.'modules SET active=0 WHERE module_name=?',['pige_bridge']);$controller->discover();essentialCheck($controller->output->status===422&&$discoveryCalls===2,'disabled module cannot make remote discovery call');
$db->query('UPDATE '.$p.'modules SET active=1,installed_version=? WHERE module_name=?',['1.0.8','pige_bridge']);$controller->discover();essentialCheck($controller->output->status===422&&$discoveryCalls===2,'unmigrated module cannot make remote discovery call');
essentialCheck($db->query('SELECT * FROM '.$p.'pige_bridge_connections ORDER BY connection_key')->result_array()===$before&&$db->query('SELECT * FROM '.$p.'invoices')->result_array()===$invoices&&$db->query('SELECT * FROM '.$p.'issuers_audit')->result_array()===$audit,'discovery and denied requests preserve connections, invoices and financial audit');
essentialCheck(!str_contains(json_encode($GLOBALS['essentialLogs']),'synthetic.header.signature'),'admin credential absent from application logs');
echo 'PIGE discovery controller/MySQL: '.($GLOBALS['essentialChecks']-$start).' added checks passed; HTTP/DNS are synthetic, no external request.'.PHP_EOL;
