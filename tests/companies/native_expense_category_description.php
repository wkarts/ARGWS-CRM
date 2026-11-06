<?php
/** Actual additive DDL and category writer. CI assigns this command only to the positive MySQL phase. */
require __DIR__.'/expenses_operations.php';
require_once APPPATH.'services/NativeCompanyExpenseCategorySchema.php';
if(getenv('CI')&&$db->driver()!=='mysql')throw new RuntimeException('Expense category description CI requires MySQL');
$start=$GLOBALS['essentialChecks'];
$categoryKey=$key('description-before-upgrade');
$oldName='Original optional category';$categoryId=$svc->saveCategory($a,7,$oldName,$categoryKey);
$categoryById=static function(int$id)use($svc,$a):array{foreach($svc->categories($a,7)as$row)if((int)$row['id']===$id)return$row;throw new RuntimeException('Missing synthetic category');};
$before=$db->query('SELECT * FROM '.$p.'company_expense_categories ORDER BY id')->result_array();$oldCategory=$categoryById($categoryId);
$receipt=$db->query('SELECT * FROM '.$p.'company_expense_requests WHERE request_key=?',[$categoryKey])->row_array();
essentialCheck($receipt['input_hash']===hash('sha256',json_encode([$oldName,null,null,true],JSON_THROW_ON_ERROR)),'The pre-upgrade category command retains its original four-value hash');
$db->trans_begin();try{essentialThrows(fn()=>NativeCompanyExpenseCategorySchema::ensure($db,$p),'issuer_outer_transaction_not_allowed','Additive category DDL does not commit a caller transaction');essentialCheck($db->pdo->inTransaction(),'Rejected DDL leaves the caller transaction owned by the caller');}finally{$db->trans_rollback();}
$cached=new class($db){
    public array$data_cache=[];public PDO$pdo;public bool$db_debug=true;
    public function __construct(private$inner){$this->pdo=$inner->pdo;}
    public function field_exists($field,$table):bool{if(!isset($this->data_cache['field_names'][$table]))$this->data_cache['field_names'][$table]=$this->inner->list_fields($table);return in_array($field,$this->data_cache['field_names'][$table],true);}
    public function query($sql,$params=[]){return$this->inner->query($sql,$params);}
};
essentialCheck(!NativeCompanyExpenseCategorySchema::available($cached,$p),'The fixture reproduces CI field metadata cached before the additive migration');
$cached->data_cache['field_names'][$p.'unrelated']=['retained'];
NativeCompanyExpenseCategorySchema::ensure($cached,$p);NativeCompanyExpenseCategorySchema::ensure($cached,$p);CompanyExpenseSchema::assertValid($db,$p);
essentialCheck(NativeCompanyExpenseCategorySchema::available($cached,$p)&&$cached->data_cache['field_names'][$p.'unrelated']===['retained'],'Repeated ensure on the same cached connection observes the new column while preserving unrelated field caches');
$after=$db->query('SELECT * FROM '.$p.'company_expense_categories ORDER BY id')->result_array();
essentialCheck(array_filter($after,static fn($row)=>$row['description']!==null)===[],'Upgrade leaves the optional historical description unknown rather than inventing values');
$oldColumns=array_map(static function($row){unset($row['description']);return$row;},$after);
essentialCheck($oldColumns===$before&&$categoryById($categoryId)['revision_token']===$oldCategory['revision_token'],'Repeated upgrade preserves category bytes and a pre-upgrade optimistic revision');
essentialCheck($svc->saveCategory($a,7,$oldName,$categoryKey)===$categoryId&&$db->query('SELECT * FROM '.$p.'company_expense_requests WHERE request_key=?',[$categoryKey])->row_array()===$receipt,'A pre-upgrade request replays against the exact same stored receipt');
$description="Despesas de deslocamento\n<script>texto literal</script>";$editKey=$key('description-edit');
$svc->saveCategory($a,7,$oldName,$editKey,$categoryId,$oldCategory['revision_token'],true,$description);$described=$categoryById($categoryId);
essentialCheck($described['description']===$description&&(int)$described['revision']===(int)$oldCategory['revision']+1&&$described['revision_token']!==$oldCategory['revision_token'],'The original description persists with the category under its optimistic revision');
$frame=static fn()=>[
    $db->query('SELECT * FROM '.$p.'company_expense_categories ORDER BY id')->result_array(),
    $db->query('SELECT * FROM '.$p.'company_expense_requests ORDER BY request_key')->result_array(),
    $db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array(),
];$saved=$frame();
$svc->saveCategory($a,7,$oldName,$editKey,$categoryId,$oldCategory['revision_token'],true,$description);
essentialCheck($frame()===$saved,'Description replay does not rewrite category, revision, audit or receipt');
essentialThrows(fn()=>$svc->saveCategory($a,7,$oldName,$editKey,$categoryId,$oldCategory['revision_token'],true,'Altered replay'),'company_expense_conflict','A replay cannot substitute a different description');
essentialThrows(fn()=>$svc->saveCategory($a,7,$oldName,$key('description-stale'),$categoryId,$oldCategory['revision_token'],true,$description),'company_expense_stale','A new command cannot overwrite a newer description using the old revision');
essentialThrows(fn()=>$svc->saveCategory($b,7,'Other owner',$key('description-other'),$categoryId,$described['revision_token'],true,$description),'company_expense_denied','Category description never broadens ownership to another company');
$svc->saveCategory($a,7,'Renamed optional category',$key('description-omitted'),$categoryId,$described['revision_token'],true);$renamed=$categoryById($categoryId);
essentialCheck($renamed['description']===$description,'Older callers that omit description preserve the current value');
$beforeFault=$frame();$db->failOn='INSERT INTO '.$p.'company_expense_requests';
try{essentialThrows(fn()=>$svc->saveCategory($a,7,$renamed['name'],$key('description-receipt-fault'),$categoryId,$renamed['revision_token'],true,'Must roll back'),'issuer_storage_failed','Receipt failure aborts the description write');}finally{$db->failOn=null;}
essentialCheck($frame()===$beforeFault,'Receipt failure restores the category and its audit atomically');
$trigger=$p.'category_description_fault';
$db->query($db->driver()==='sqlite'
    ?'CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."issuers_audit WHEN NEW.event='company_expense_category_saved' BEGIN UPDATE ".$p."company_expense_categories SET description='Unexpected late overwrite' WHERE id=".$categoryId.'; END'
    :'CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p.'issuers_audit FOR EACH ROW UPDATE '.$p."company_expense_categories SET description='Unexpected late overwrite' WHERE id=".$categoryId." AND NEW.event='company_expense_category_saved'");
try{essentialThrows(fn()=>$svc->saveCategory($a,7,'Failed rename',$key('description-preservation-fault'),$categoryId,$renamed['revision_token'],true),'issuer_expense_write_unconfirmed','Final confirmation also protects the description when an old caller omits it');}finally{$db->query('DROP TRIGGER '.$trigger);}
essentialCheck($frame()===$beforeFault,'Late overwrite rolls back the entire rename rather than losing optional metadata');
$svc->saveCategory($a,7,$renamed['name'],$key('description-clear'),$categoryId,$renamed['revision_token'],true,'');$cleared=$categoryById($categoryId);
essentialCheck($cleared['description']==='','An explicit empty description clears the field without becoming an unknown NULL');
essentialThrows(fn()=>$svc->saveCategory($a,7,'Invalid category',$key('description-too-long'),null,null,true,str_repeat('x',4001)),'company_expense_invalid','Category descriptions respect the existing bounded text contract');
$badPrefix=$p.'bad_';$badTable=$badPrefix.NativeCompanyExpenseCategorySchema::TABLE;
$db->query('CREATE TABLE '.$badTable.'(id int PRIMARY KEY,description varchar(20) NULL) ENGINE=InnoDB');
try{essentialThrows(fn()=>NativeCompanyExpenseCategorySchema::ensure($db,$badPrefix),'native_expense_category_schema_invalid','A conflicting preexisting category column fails closed rather than being rewritten');}
finally{$db->query('DROP TABLE '.$badTable);}
essentialCheck($db->query('SELECT * FROM '.$p.'expenses ORDER BY id')->result_array()===$legacy,'Category upgrade and writes preserve all historical legacy expenses');
echo 'Native expense category description: '.($GLOBALS['essentialChecks']-$start).' added DDL/writer/replay/rollback checks passed ('.$db->driver().').'.PHP_EOL;
