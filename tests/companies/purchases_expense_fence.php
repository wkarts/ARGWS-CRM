<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php';
require_once APPPATH.'services/CompanyExpenseApi.php';

$selected=getenv('PURCHASE_FENCE_CASE')?:'all';
if (!in_array($selected,['all','wrong-issuer','late-native','late-api-reassign','late-api-insert'],true)) { throw new RuntimeException('Unknown finite fence scenario'); }
CompanyApiSchema::ensure($db,$p); CompanyFinancialApiSchema::ensure($db,$p);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'company_api','manage')",[$a]);
$tokens=new CompanyApiTokens($db,$p); $credential=$tokens->issue($a,12,'Synthetic expense purchase fence',['expenses:edit','expenses:cancel'],1,$key('expense-fence-token'));
$api=new CompanyExpenseApi($db,$p); $origins=[];
foreach ([$a,$b] as $issuer) {
    $chain=purchaseOrder($issuer,12,'fence-'.$issuer);
    $receipt=$svc->receive($issuer,12,$chain['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key('fence-receive-'.$issuer));
    $payable=purchaseDocument($issuer,12,$chain['order'],[$receipt],'fence-'.$issuer);
    $origins[$issuer]=$db->query('SELECT * FROM '.$p.'company_purchase_payables WHERE id=?',[$payable])->row_array();
}
$ordinaryInput=['category_id'=>$categories[$a],'currency'=>'BRL','date'=>$today,'description'=>'Unlinked canonical expense','amount'=>'1.00','tax_rate'=>'0.00'];
$editedInput=array_replace($ordinaryInput,['description'=>'Reviewed canonical expense','amount'=>'2.00']);

function fenceSnapshot(): array {
    global $db,$p;
    $snapshot=purchaseSnapshot();
    foreach (['company_api_tokens','company_api_token_requests','company_financial_api_requests','company_expense_categories'] as $table) {
        $snapshot[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array();
    }
    return $snapshot;
}
function fenceStatement(array $row,bool $insert=false): string {
    global $db,$p;
    if ($insert) {
        return 'INSERT INTO '.$p.'company_purchase_payables (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_map(static fn($v)=>$v===null?'NULL':$db->pdo->quote((string)$v),array_values($row))).')';
    }
    $id=$row['id']; unset($row['id']); $parts=[];
    foreach ($row as $name=>$value) { $parts[]='`'.$name.'`='.($value===null?'NULL':$db->pdo->quote((string)$value)); }
    return 'UPDATE '.$p.'company_purchase_payables SET '.implode(',',$parts).' WHERE id='.(int)$id;
}
function fenceOrigin(array $original,int $expense): array {
    global $db,$p;
    $row=$original; $row['expense_id']=$expense;
    $source=$db->query('SELECT * FROM '.$p.'company_expenses WHERE id=?',[$expense])->row_array();
    $row['expense_hash']=hash('sha256',json_encode($source,JSON_THROW_ON_ERROR));
    $row['record_hash']=CompanyPurchasesValues::hash($row); return $row;
}

foreach (['wrong-issuer','late-native','late-api-reassign','late-api-insert'] as $scenario) {
    if ($selected!=='all'&&$selected!==$scenario) { continue; }
    foreach (['update','cancel'] as $action) {
        $tag=$scenario.'-'.$action;
        $target=$expenseService->create($a,12,$ordinaryInput,$key('fence-target-'.$tag));
        $revision=$expenseService->get($a,12,$target)['revision_token'];
        $original=$origins[$scenario==='wrong-issuer'?$b:$a];
        $changed=fenceOrigin($original,$target); $insert=$scenario==='late-api-insert';
        if ($scenario==='wrong-issuer') { $db->query(fenceStatement($changed)); }
        elseif ($insert) { $db->query('DELETE FROM '.$p.'company_purchase_payables WHERE id=?',[$original['id']]); }
        $before=fenceSnapshot();
        if ($scenario!=='wrong-issuer') {
            $table=$scenario==='late-native'?'issuers_audit':'company_financial_api_requests';
            $condition=$scenario==='late-native'?"NEW.event='company_expense_{$action}'":"NEW.operation='expense_{$action}'";
            $db->query('SET @purchase_fence_fired=0');
            $db->query('CREATE TRIGGER '.$p.'purchase_expense_fence AFTER INSERT ON '.$p.$table.' FOR EACH ROW BEGIN IF '.$condition.' THEN '.fenceStatement($changed,$insert).'; SET @purchase_fence_fired=@purchase_fence_fired+1; END IF; END');
        }
        try {
            if (str_starts_with($scenario,'late-api')) {
                purchaseThrows(fn()=>$api->write($credential['secret'],$target,['action'=>$action,'revision'=>$revision,'input'=>$action==='update'?$editedInput:[]],$key('fence-command-'.$tag)),
                    'company_expense_purchase_source_immutable',$tag.' cannot mutate an expense after a late AP reference');
            } else {
                purchaseThrows(fn()=>$expenseService->change($a,12,$target,$revision,$key('fence-command-'.$tag),$action==='update'?$editedInput:null),
                    'company_expense_purchase_source_immutable',$tag.' cannot mutate a purchase-linked expense');
            }
            if ($scenario!=='wrong-issuer') { purchaseCheck((int)$db->query('SELECT @purchase_fence_fired n')->row_array()['n']===1,$tag.' reaches its real late SQL trigger before the veto'); }
            purchaseCheck(fenceSnapshot()===$before,$tag.' rolls back expense, journal, AP, native/API receipts, token usage and audits byte-for-byte');
        } finally {
            if ($scenario!=='wrong-issuer') { $db->query('DROP TRIGGER IF EXISTS '.$p.'purchase_expense_fence'); }
            if ($scenario==='wrong-issuer') { $db->query(fenceStatement($original)); }
            elseif ($insert&&!$db->query('SELECT id FROM '.$p.'company_purchase_payables WHERE id=?',[$original['id']])->row_array()) { $db->query(fenceStatement($original,true)); }
        }
    }
}

if ($selected==='all') {
    $target=$expenseService->create($a,12,$ordinaryInput,$key('fence-normal-target'));
    $revision=$expenseService->get($a,12,$target)['revision_token'];
    $expenseService->change($a,12,$target,$revision,$key('fence-normal-update'),$editedInput);
    $row=$expenseService->get($a,12,$target); purchaseCheck((int)$row['total_cents']===200,'unlinked ordinary expense remains editable with Purchase installed');
    $expenseService->change($a,12,$target,$row['revision_token'],$key('fence-normal-cancel'));
    purchaseCheck($expenseService->get($a,12,$target)['state']==='cancelled','unlinked ordinary expense remains cancellable with Purchase installed');
    $target=$expenseService->create($a,12,$ordinaryInput,$key('fence-api-normal-target'));
    $row=$expenseService->get($a,12,$target); $api->write($credential['secret'],$target,['action'=>'update','revision'=>$row['revision_token'],'input'=>$editedInput],$key('fence-api-normal-update'));
    $row=$expenseService->get($a,12,$target); purchaseCheck((int)$row['total_cents']===200,'actual expense API update remains functional for an unlinked source');
    $api->write($credential['secret'],$target,['action'=>'cancel','revision'=>$row['revision_token'],'input'=>[]],$key('fence-api-normal-cancel'));
    purchaseCheck($expenseService->get($a,12,$target)['state']==='cancelled','actual expense API cancel remains functional for an unlinked source');
    foreach (array_reverse(array_keys(CompanyPurchasesSchema::definitions($p))) as $table) { $db->query('DROP TABLE '.$p.$table); } unset($db->data_cache['table_names']);
    $target=$expenseService->create($a,12,$ordinaryInput,$key('fence-no-schema-target')); $row=$expenseService->get($a,12,$target);
    $expenseService->change($a,12,$target,$row['revision_token'],$key('fence-no-schema-update'),$editedInput);
    $row=$expenseService->get($a,12,$target); purchaseCheck((int)$row['total_cents']===200,'without the optional Purchase schema, ordinary update still works');
    $expenseService->change($a,12,$target,$row['revision_token'],$key('fence-no-schema-cancel'));
    purchaseCheck($expenseService->get($a,12,$target)['state']==='cancelled','without the optional Purchase schema, ordinary cancellation still works');
}
echo 'Purchases final expense fence: '.$GLOBALS['essentialChecks'].' checks passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
