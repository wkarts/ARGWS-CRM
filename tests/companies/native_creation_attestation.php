<?php
require __DIR__.'/clients.php';
$oldClient=$a;$a=41;$b=82;
$start=$GLOBALS['essentialChecks'];$clients=new CompanyClients($db,$p);
unset($db->data_cache['table_names']);if(!$db->table_exists($p.'company_client_requests'))$db->query("CREATE TABLE {$p}company_client_requests(request_key varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,actor_id int NOT NULL,input_hash varchar(64) NOT NULL,client_id int NOT NULL,created_at datetime NOT NULL)");unset($db->data_cache['table_names']);
$called=0;$captured=null;$key=hash('sha256','native-create-attestation-test');
$callback=function($id,$writer)use($db,$p,$a,$b,&$called,&$captured,$oldClient){$called++;$writer->assertCreatedInOwned($db,$p,$a,7,'client',$id);$captured=[$writer,$id];
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$b,7,'client',$id),'native_creation_attestation_invalid','attestation cannot cross company');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,7,'client',$oldClient),'native_creation_attestation_invalid','attestation cannot authorize an old resource');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,7,'contact',$id),'native_creation_attestation_invalid','attestation cannot switch resource kind');
 try{$copy=clone $writer;throw new RuntimeException('writer cloned');}catch(Error $e){essentialCheck(true,'active attestation writer cannot be cloned');}
 try{serialize($writer);throw new RuntimeException('writer serialized');}catch(LogicException $e){essentialCheck(true,'active attestation writer cannot be serialized');}
};
$id=$clients->create($a,7,['company'=>'Attested new customer','vat'=>''],$key,$callback,[],['custom_fields'=>['synthetic'=>'value']]);essentialCheck($called===1,'new exact writer invokes callback after confirmed insertion');
essentialThrows(fn()=>$captured[0]->assertCreatedInOwned($db,$p,$a,7,'client',$id),'native_creation_attestation_invalid','captured writer loses creation grant after callback');
IssuerTransaction::run($db,$p,$clients->tables(true),fn()=>essentialThrows(fn()=>$captured[0]->assertCreatedInOwned($db,$p,$a,7,'client',$id),'native_creation_attestation_invalid','new later transaction cannot reuse prior creation grant'));
$replayed=$clients->create($a,7,['company'=>'Attested new customer','vat'=>''],$key,$callback,[],['custom_fields'=>['synthetic'=>'value']]);essentialCheck($replayed===$id&&$called===1,'request replay returns original ID without fresh creation authority');
essentialThrows(fn()=>$clients->create($a,7,['company'=>'Attested new customer','vat'=>''],$key,$callback,[],['custom_fields'=>['synthetic'=>'different']]),'company_client_request_conflict','request key binds extension input, not just customer fields');
echo 'Native customer creation attestation: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
