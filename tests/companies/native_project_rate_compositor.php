<?php
require __DIR__.'/project_billing_rate.php';
$payload=['currency'=>1,'rate'=>'70.00','round_mode'=>'none','round_minutes'=>0];$key=$tk('native-owned-rate');
essentialThrows(fn()=>$prices->declareHourlyInOwnedTransaction($a,7,$pa,1,$payload,$key),'issuer_owned_transaction_required','Compositor cannot run outside owned transaction');
$rateId=IssuerTransaction::run($db,$p,$prices->tables(),fn()=>$prices->declareHourlyInOwnedTransaction($a,7,$pa,1,$payload,$key));
essentialCheck(IssuerTransaction::run($db,$p,$prices->tables(),fn()=>$prices->declareHourlyInOwnedTransaction($a,7,$pa,1,$payload,$key))===$rateId,'Same canonical operation receipt is idempotent in compositor');
$rate=IssuerTransaction::run($db,$p,$prices->tables(),fn()=>$prices->inOwned($a,7,$pa,$rateId,true));essentialCheck($rate['payload']['rate']==='70.00'&&$rate['payload']['round_minutes']===0,'Canonical immutable profile and provenance retained');
$before=$db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array();
essentialThrows(function()use($db,$p,$prices,$a,$pa,$payload,$tk){IssuerTransaction::run($db,$p,$prices->tables(),function()use($prices,$a,$pa,$payload,$tk){$prices->declareHourlyInOwnedTransaction($a,7,$pa,1,array_replace($payload,['rate'=>'80.00']),$tk('native-owned-rate-rollback'));throw new DomainException('native_compositor_rollback');});},'native_compositor_rollback','Outer project failure rolls back immutable price and its receipt');
essentialCheck($db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array()===$before,'No rate leaked from failed project compositor');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$prices->tables(),fn()=>$prices->declareHourlyInOwnedTransaction($b,7,$pa,1,$payload,$tk('native-foreign-rate'))),'company_project_rate_denied','A project cannot price under B issuer');
echo 'Native project canonical rate compositor: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
