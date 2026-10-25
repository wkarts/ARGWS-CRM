<?php
/** Actual adapter table composition and IssuerTransaction ownership; SQL is an explicit in-memory double. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('FCPATH',dirname(__DIR__).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPayers.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyHostedLinks.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefundStorage.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';
final class CheckoutBoundaryResult
{
    public function __construct(private array $rows){}
    public function result_array():array{return $this->rows;}
    public function row_array():array{return $this->rows[0]??[];}
}
final class CheckoutBoundaryDatabase
{
    public string $dbdriver='mysql';public int $_trans_depth=0;public bool $db_debug=true;public bool $save_queries=true;
    public int $commits=0;public int $rollbacks=0;public string $version='1.6.0';public ?string $badEngine=null;
    public array $present=['asaas_company_origin_policy','efi_company_origins','bb_origins','asaas_company_remote_operations'];
    public function table_exists(string $table):bool{return in_array(substr($table,4),$this->present,true);}
    public function query(string $sql,array $args=[]):CheckoutBoundaryResult
    {
        if(str_starts_with($sql,'SELECT @@SESSION.autocommit'))return new CheckoutBoundaryResult([['autocommit'=>1,'completion_type'=>0]]);
        if(str_starts_with($sql,'SET TRANSACTION ISOLATION'))return new CheckoutBoundaryResult([]);
        if(str_contains($sql,'FROM information_schema.TABLES'))return new CheckoutBoundaryResult(array_map(fn($table)=>['table_name'=>$table,'engine'=>$table===$this->badEngine?'MyISAM':'InnoDB'],$args));
        if($sql==='SELECT id FROM tbl_issuers_lock WHERE id=1 FOR UPDATE')return new CheckoutBoundaryResult([['id'=>1]]);
        if($sql==='SELECT installed_version FROM tbl_modules WHERE module_name=?'&&$args===['asaas'])return new CheckoutBoundaryResult([['installed_version'=>$this->version]]);
        throw new LogicException('Unexpected SQL in ownership-only double');
    }
    public function trans_begin():bool{$this->_trans_depth=1;return true;}
    public function trans_status():bool{return true;}
    public function trans_commit():bool{$this->_trans_depth=0;$this->commits++;return true;}
    public function trans_rollback():bool{$this->_trans_depth=0;$this->rollbacks++;return true;}
}
$checks=0;
function boundaryCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;}
function boundaryThrows(callable $work,string $code):void{try{$work();}catch(RuntimeException $error){boundaryCheck($error->getMessage()===$code,'exact denial '.$code);return;}throw new RuntimeException('FAIL: expected '.$code);}
$db=new CheckoutBoundaryDatabase();$service=new \Asaas\CompanyService($db,'tbl_');
$adapters=['payer'=>new \Asaas\CompanyPayers($db,'tbl_'),'hosted'=>new \Asaas\CompanyHostedLinks($db,'tbl_'),'refund'=>new class($db,'tbl_') extends \Asaas\CompanyRefundStorage {}];
foreach($adapters as $name=>$adapter){
    $required=$service->tablesForRun();$tables=$adapter->tables();
    boundaryCheck(array_diff($required,$tables)===[],$name.' owns every table the service requires');
    boundaryCheck(in_array('asaas_company_origin_policy',$tables,true)&&in_array('efi_company_origins',$tables,true),$name.' includes current policy and cross-provider fence');
    $completed=false;IssuerTransaction::run($db,'tbl_',$tables,function()use($db,$service,&$completed):void{IssuerTransaction::assertOwned($db,'tbl_',$service->tablesForRun());$completed=true;});
    boundaryCheck($completed&&$db->_trans_depth===0,$name.' enters real owned boundary and commits');
    foreach(['asaas_company_origin_policy','efi_company_origins'] as $missing){
        $incomplete=array_values(array_diff($tables,[$missing]));$prior=$db->commits;
        boundaryThrows(fn()=>IssuerTransaction::run($db,'tbl_',$incomplete,fn()=>$service->payerAccountInOwnedTransaction(1,1,1)),'issuer_owned_transaction_required');
        boundaryCheck($db->commits===$prior&&$db->_trans_depth===0,$name.' incomplete boundary rolls back before account/SDK access');
    }
    $db->badEngine='tbl_efi_company_origins';$entered=false;
    boundaryThrows(fn()=>IssuerTransaction::run($db,'tbl_',$tables,function()use(&$entered):void{$entered=true;}),'issuer_transactional_storage_required');
    boundaryCheck(!$entered,$name.' unsafe fence engine rejected before callback');$db->badEngine=null;
}
$remote=new \Asaas\CompanyRemoteOperations($db,'tbl_');$remoteTables=(new ReflectionMethod($remote,'tables'))->invoke($remote);
boundaryCheck(array_diff($service->tablesForRun(),$remoteTables)===[],'remote removal already owns the complete service boundary');
$portal=$adapters['hosted']->tables(true);boundaryCheck(in_array('contacts',$portal,true)&&in_array('contact_permissions',$portal,true),'portal retains contact authority tables');
$db->present=array_values(array_diff($db->present,['asaas_company_origin_policy']));
foreach($adapters as $adapter)boundaryThrows(fn()=>$adapter->tables(),'asaas_origin_policy_schema_required');
$db->version='1.5.6';
foreach($adapters as $name=>$adapter)boundaryCheck(!in_array('asaas_company_origin_policy',$adapter->tables(),true),$name.' historical pre157 schema remains supported without invented table');
boundaryThrows(fn()=>IssuerTransaction::assertOwned($db,'tbl_',$service->tablesForRun()),'issuer_owned_transaction_required');
echo 'Asaas160 adapter ownership: '.$checks.' checks passed; actual service/table/transaction code with explicit SQL double, no database or provider execution.'.PHP_EOL;
