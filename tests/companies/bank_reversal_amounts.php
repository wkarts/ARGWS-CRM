<?php
require dirname(__DIR__,2).'/application/services/CompanyBankReversalData.php';
$checks=0;function check428(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException($label);}function denied428(callable $work,string $error):void{try{$work();throw new RuntimeException('accepted unexpectedly');}catch(DomainException $e){check428($e->getMessage()===$error,'wrong denial '.$e->getMessage());}}
$event=['channel'=>'cnab240','currency'=>'BRL','occurrence'=>'06','observation'=>'settlement_reported','nominal_amount'=>'100.00','paid_amount'=>'95.00','net_amount'=>'95.00','interest'=>'5.00','discount'=>'10.00','abatement'=>'0.00','fees'=>'0.00','iof'=>'0.00','other_expenses'=>'0.00','other_credits'=>'0.00'];$original=CompanyBankComponentsData::fromCnab240($event,'100.00','100.00',str_repeat('a',64));$input=['external_reversal_confirmed'=>'1','full_reinstatement_confirmed'=>'1','returned_paid'=>'95.00','reinstated_principal'=>'100.00','date'=>'2026-10-19','reference'=>'SYNTHETIC INDEPENDENT FULL REVERSAL 123','reason'=>'Synthetic operator proof; no bank execution/authentication'];$declare=fn($data)=>CompanyBankReversalData::declare($original,str_repeat('b',64),str_repeat('c',64),'2026-10-18','2026-10-19',$data);$dto=$declare($input);$data=$dto->data();
check428($dto->cashPrincipal()==='90.00'&&$dto->principalReinstated()==='100.00'&&$data['returned_paid']==='95.00'&&$data['charges_aggregate_reversed']==='5.00'&&$data['discount_reversed']==='10.00','full reversal separates original principalcash90, noncash10 and gross95 includingcharges5');
check428($original->data()['cash_principal']==='90.00'&&$original->data()['balance_after']==='0.00','preparing reversal never mutates original receipt DTO');
check428(CompanyBankReversalData::fromCaptured(array_reverse($data,true),$original)->fingerprint()===$dto->fingerprint(),'captured DTO keys canonicalized independently of input ordering');
check428($declare(array_replace($input,['returned_paid'=>'95','reinstated_principal'=>'100']))->fingerprint()===$dto->fingerprint(),'exact monetary canonicalization gives stable proof');
foreach(['external_reversal_confirmed','full_reinstatement_confirmed'] as $field)denied428(fn()=>$declare(array_replace($input,[$field=>'0'])),'company_bank_reversal_independent_confirmation_required');
denied428(fn()=>$declare(array_diff_key($input,['reference'=>true])),'company_bank_reversal_independent_confirmation_required');
denied428(fn()=>$declare($input+['bank_event_code'=>'44']),'company_bank_reversal_independent_confirmation_required');
denied428(fn()=>$declare(array_replace($input,['returned_paid'=>'90.00'])),'company_bank_reversal_full_amount_required');
denied428(fn()=>$declare(array_replace($input,['reinstated_principal'=>'90.00'])),'company_bank_reversal_full_amount_required');
denied428(fn()=>$declare(array_replace($input,['date'=>'2026-10-17'])),'company_bank_reversal_date_requires_review');
denied428(fn()=>$declare(array_replace($input,['date'=>'2026-10-20'])),'company_bank_reversal_date_requires_review');
denied428(fn()=>$declare(array_replace($input,['reference'=>''])),'company_bank_reversal_invalid');
denied428(fn()=>$declare(array_replace($input,['returned_paid'=>'9.5e1'])),'company_bank_reversal_invalid');
$changed=$data;$changed['cash_principal_reversed']='100.00';denied428(fn()=>CompanyBankReversalData::fromCaptured($changed,$original),'company_bank_reversal_invalid');
$changed=$data;$changed['original_components_hash']=str_repeat('0',64);denied428(fn()=>CompanyBankReversalData::fromCaptured($changed,$original),'company_bank_reversal_invalid');
check428($declare(array_replace($input,['reason'=>'Different reason']))->fingerprint()!==$dto->fingerprint(),'immutable reversal proof includes operator reason');
check428($declare(array_replace($input,['reference'=>'Different independent statement']))->externalReferenceKey()!==$dto->externalReferenceKey(),'full reversal evidence carries scoped independent external reference identity');
echo 'Prepared428 declared full reversal: '.$checks.' exact-money checks; no live bank or active canonical integration.'.PHP_EOL;
