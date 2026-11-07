<?php
/** Real MySQL contact login, native permission, publication and unchanged CNAB bytes. No bank requests. */
require __DIR__.'/banco_brasil_cnab400_boleto_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Checkout.php';
require_once APPPATH.'services/CompanyContacts.php';
require_once APPPATH.'services/CompanyContactInvoiceAccess.php';
$portalStart=$GLOBALS['essentialChecks'];
$native=file_get_contents(dirname(__DIR__).'/install/database.sql');
foreach(['contacts','contact_permissions'] as $name){
    if($db->table_exists($p.$name))continue;
    preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);
    if(!$match)throw new RuntimeException('native contact fixture missing');
    $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);
    $db->query(str_replace('`id` int NOT NULL,','`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$sql));
}
require_once APPPATH.'migrations/407_version_407.php';$migration=new Migration_Version_407();$migration->db=$db;$migration->up();
unset($db->data_cache['table_names']);
$contacts=new CompanyContacts($db,$p);$access=new CompanyContactInvoiceAccess($db,$p);
$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$checkout=new \BancoBrasil\Checkout($db,$p);
$person=['firstname'=>'Synthetic','lastname'=>'CNAB','email'=>'cnab-portal@example.invalid'];
$contact=$contacts->save($a,7,$ca,$person);$password='synthetic-cnab-password';
$db->query('UPDATE '.$p.'contacts SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$contact]);
$session=$portal->login($a,$person['email'],$password,'password_verify');
essentialCheck(is_array($session),'native contact login supplies the portal identity');
$permission=$access->page($a,7,$ca)[$contact];$access->set($a,7,$ca,$contact,$permission['revision'],'1');
$portalInvoice240=$s->create($a,7,$input,hash('sha256','cnab-portal-240-invoice'));
$portalBatch240=$bank->generate($a,7,$connection,$binding,'2026-10-22',[array_replace($title,['invoice_id'=>$portalInvoice240])],hash('sha256','cnab-portal-240-batch'));
$origin240=(int)$db->query('SELECT id FROM '.$p.'bb_origins WHERE issuer_id=? AND invoice_id=?',[$a,$portalInvoice240])->row()->id;
$origin400=(int)$reserved400['origin_id'];
$bankState=static function()use($db,$p){$out=[];foreach(['bb_batches','bb_origins','bb_number_reservations','bb_batch_actions','bb_batch_requests','bb_audit','invoicepaymentrecords','invoices'] as $table)$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return $out;};
foreach([[$portalInvoice240,$origin240],[$invoice400,$origin400]] as [$invoice,$origin]){
    $before=$bankState();
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$a,$invoice),'bb_checkout_unavailable','unpublished CNAB is not disclosed to its contact');
    $review=$checkout->staffInstrument($a,7,$origin);
    essentialThrows(fn()=>$checkout->publish($a,7,$origin,str_repeat('0',64),true),'bb_checkout_stale','changed review cannot publish');
    $db->failOn='INSERT INTO '.$p.'issuers_audit';
    essentialThrows(fn()=>$checkout->publish($a,7,$origin,$review['revision'],true),'issuer_storage_failed','publication audit failure rolls back disclosure');$db->failOn=null;
    essentialCheck(!$db->query('SELECT origin_id FROM '.$p.'bb_checkout_publications WHERE origin_id=?',[$origin])->result_array(),'failed audit leaves no publication');
    $checkout->publish($a,7,$origin,$review['revision'],true);
    $visible=$checkout->portalInstrument($portal,$session,$a,$invoice);
    essentialCheck($visible['cnab_boleto']===$review['cnab_boleto']&&!isset($visible['revision']),'portal returns the exact reviewed instrument without the administrative revision');
    essentialCheck($visible['bank_state']==='generated_registration_not_confirmed','publication does not assert registration');
    if($invoice===$portalInvoice240){
        $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
        try{
            for($worker=0;$worker<2;$worker++){
                $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/support/BbCnab400PreparationWorker.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                if(!is_resource($process))throw new RuntimeException('portal worker unavailable');
                fwrite($pipes[0],json_encode(['action'=>'portal','session'=>$session,'issuer'=>$a,'invoice'=>$invoice],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
            }
            $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
            essentialCheck($waiters>=2,'two actual portal readers wait on the financial mutex');
            $db->query('DELETE FROM '.$p.'contact_permissions WHERE userid=? AND permission_id=1',[$contact]);$db->trans_commit();
            foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err===''&&$out==='denied','revocation while portal readers wait prevents disclosure');}
        }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
        $permission=$access->page($a,7,$ca)[$contact];$access->set($a,7,$ca,$contact,$permission['revision'],'1');
        if(getenv('BB_CNAB_PORTAL_HTML')){
            $boleto=$visible['cnab_boleto'];$boleto['barcode_svg']=\BancoBrasil\CnabBoleto::svg($boleto['barcode']);$script_nonce='synthetic-print-nonce';
            ob_start();require APP_MODULES_PATH.'banco_brasil/views/cnab_boleto.php';file_put_contents(getenv('BB_CNAB_PORTAL_HTML'),ob_get_clean());
        }
    }
    essentialCheck($checkout->portalPage($portal,$session,$a,[$invoice])===[$invoice=>true],'portal invoice list offers the published instrument');
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$b,$invoice),'bb_checkout_access_denied','foreign company cannot reuse the contact session');
    $permission=$access->page($a,7,$ca)[$contact];$access->set($a,7,$ca,$contact,$permission['revision'],'0');
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$a,$invoice),'bb_checkout_access_denied','revoked native invoice permission immediately hides the boleto');
    $permission=$access->page($a,7,$ca)[$contact];$access->set($a,7,$ca,$contact,$permission['revision'],'1');
    $db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$a,$invoice),'bb_checkout_access_denied','revoked publisher cannot sustain publication');
    $db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
    $db->query('UPDATE '.$p.'invoices SET status=5 WHERE id=?',[$invoice]);
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$a,$invoice),'bb_checkout_access_denied','cancelled invoice cannot disclose a payment instrument');
    $db->query('UPDATE '.$p.'invoices SET status=1 WHERE id=?',[$invoice]);
    $checkout->publish($a,7,$origin,str_repeat('0',64),false);
    essentialThrows(fn()=>$checkout->portalInstrument($portal,$session,$a,$invoice),'bb_checkout_unavailable','explicit hiding revokes portal access without deleting history');
    essentialCheck($bankState()===$before,'publication/read/hide preserve remittance bytes, states, counters, invoices and payments');
}
echo 'CNAB portal publication: '.($GLOBALS['essentialChecks']-$portalStart).' additional SQL checks; no bank HTTP or financial effect.'.PHP_EOL;
