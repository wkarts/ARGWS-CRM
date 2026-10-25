<?php
/** Native source/account/guard rechecks and synthetic instruments; no real provider. */
require __DIR__.'/asaas_origin_policy.php';
$invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','instrument-invoice'));$key=hash('sha256','instrument-issue');$reference=hash('sha256','instrument-reference');$denied=false;
$instrumentGuard=function()use(&$denied,$db,$p){IssuerTransaction::assertOwned($db,$p,['asaas_company_origin_policy']);if($denied)throw new DomainException('synthetic_origin_revoked');};
$origin=$asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$s->revision($b,7,$invoice),$key,$dec,$mock,null,$instrumentGuard,[],true,$reference);
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row()->remote_id;
$instrumentTransport=function($env,$method,$path,$payload,$secret)use($mock,$remote){if($path==='payments/'.$remote.'/pixQrCode'){essentialCheck($method==='GET'&&$payload===null,'instrument never posts');return ['payload'=>'000201synthetic-not-a-payable-code','expirationDate'=>'2030-12-31 23:59:59','encodedImage'=>'never-output'];}return $mock($env,$method,$path,$payload,$secret);};
$writes=count($db->writes());$out=$asaas->instruments($b,7,$origin,$dec,$instrumentTransport,$instrumentGuard);
essentialCheck($out['remote_id']===$remote&&$out['instrument']['billing_type']==='PIX'&&!isset($out['instrument']['encodedImage']),'instrument follows authenticated origin and returns bounded text only');essentialCheck(count($db->writes())===$writes,'instrument lookup does not write proof/job/payment');
$revokeTransport=function($env,$method,$path,$payload,$secret)use($instrumentTransport,&$denied){$out=$instrumentTransport($env,$method,$path,$payload,$secret);if(str_ends_with($path,'/pixQrCode'))$denied=true;return $out;};
essentialThrows(fn()=>$asaas->instruments($b,7,$origin,$dec,$revokeTransport,$instrumentGuard),'synthetic_origin_revoked','guard revoked during HTTP prevents publishing instrument');$denied=false;
$changedTransport=function($env,$method,$path,$payload,$secret)use($instrumentTransport,&$responses,$remote){$out=$instrumentTransport($env,$method,$path,$payload,$secret);if(str_ends_with($path,'/pixQrCode'))$responses[$remote]['status']='RECEIVED';return $out;};
essentialThrows(fn()=>$asaas->instruments($b,7,$origin,$dec,$changedTransport,$instrumentGuard),'asaas_instrument_changed','settlement racing instrument read invalidates publication');
essentialCheck(count($db->writes())===$writes,'rejected instruments still have zero database writes');
echo "Asaas instruments: owned source, post-HTTP guard, race rejection and zero writes.\n";
