<?php
require __DIR__.'/asaas_legacy_binding.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.1','asaas']);
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','installment-review'));
$hash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->hash;
$group=['id'=>'grp_review','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'105.53','deleted'=>false];
$first=array_replace($originalResponse,['id'=>'pay_group_one','externalReference'=>$hash,'installment'=>$group['id'],'installmentNumber'=>1,'value'=>'40.00','netValue'=>'39.00','status'=>'PENDING','paymentDate'=>'','creditDate'=>'']);
$second=array_replace($first,['id'=>'pay_group_two','installmentNumber'=>2,'value'=>'65.53','netValue'=>'64.53','dueDate'=>'2026-12-25']);
$members=[$second,$first];$hasMore=false;$during=null;$calls=[];
$transport=function($env,$method,$path,$payload,$secret)use(&$group,&$first,&$members,&$hasMore,&$during,&$calls,$mock){
 $calls[]=[$method,$path];essentialCheck($method==='GET'&&$payload===null,'group review is bodyless read-only GET');
 if($path==='payments/pay_group_one')return $first;
 if($path==='installments/grp_review')return $group;
 if($path==='installments/grp_review/payments'){if($during){$callback=$during;$during=null;$callback();}return ['data'=>$members,'hasMore'=>$hasMore,'totalCount'=>count($members)];}
 return $mock($env,$method,$path,$payload,$secret);
};
$review=fn()=>$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport);
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array();
$result=$review();essentialCheck($result['read_only']&&!$result['bound']&&array_column($result['members'],'number')===[1,2]&&$result['amount']==='105.53','group ordered and exact total reviewed');
essentialCheck($review()['review_hash']===$result['review_hash'],'stable canonical review digest');
$n=count($calls);essentialThrows(fn()=>$asaas->reviewLegacyInstallments($a,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport),'asaas_company_access_denied','foreign company rejected');essentialCheck(count($calls)===$n,'foreign account blocked before HTTP');
$members=[$first,$first];essentialThrows($review,'asaas_installment_review_invalid','duplicate member rejected');$members=[$second,$first];
$hasMore=true;essentialThrows($review,'asaas_installment_review_incomplete','truncated group rejected');$hasMore=false;
$members[0]['value']='65.52';essentialThrows($review,'asaas_installment_review_incomplete','cent mismatch rejected');$members=[$second,$first];
$members[0]['customer']='cus_foreign';essentialThrows($review,'asaas_installment_review_invalid','foreign customer member rejected');$members=[$second,$first];
$members[0]['externalReference']='foreign_hash';essentialThrows($review,'asaas_company_response_conflict','foreign invoice member rejected');$members=[$second,$first];
$members[1]['status']='OVERDUE';essentialThrows($review,'asaas_legacy_review_changed','detail and list divergence rejected');$members=[$second,$first];
$during=fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');essentialThrows($review,'asaas_company_access_denied','revocation during HTTP rejected at final validation');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$group['value']='106.00';essentialThrows($review,'asaas_legacy_amount_review','group above native balance rejected');$group['value']='105.53';
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.0','asaas']);$n=count($calls);essentialThrows($review,'asaas_module_181_required','group review requires native tracking');essentialCheck(count($calls)===$n,'old tracking rejected before network');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_payment_origins ORDER BY id')->result_array()===$before,'all group reviews preserve origins byte for byte');
$savedAccounts=$accounts;$legacy_review=$result;$legacy_review['group_id']='<script>forbidden</script>';$accounts=[];$action_url='/synthetic/settings';$preferences_csrf='synthetic';
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;$hidden=function(array $values)use($e){foreach($values as $name=>$value)echo '<input type="hidden" name="'.$e($name).'" value="'.$e($value).'">';};
ob_start();include APPPATH.'views/admin/settings/native_asaas_legacy_binding.php';$html=ob_get_clean();
essentialCheck(str_contains($html,'pay_group_one')&&str_contains($html,'pay_group_two')&&!str_contains($html,'confirm_legacy_binding'),'group view lists individual identities without binding confirmation');
essentialCheck(!str_contains($html,'<script>forbidden')&&str_contains($html,'&lt;script&gt;forbidden'),'group view escapes display content');
$accounts=$savedAccounts;
echo 'Installment group review: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated, no financial writes.'.PHP_EOL;
