<?php
require __DIR__.'/native_contacts.php';
$start=$GLOBALS['essentialChecks'];
$contactInput=$values;$contactInput['email']='attested-contact@example.invalid';$contactInput['is_primary']=0;$contactCapture=null;
$contactCallback=function($id,$cap,$writer)use($db,$p,$a,&$contactCapture){$writer->assertCreatedInOwned($db,$p,$a,7,'contact',$id);$contactCapture=[$writer,$id];};
$newContact=$native->save($a,7,101,$contactInput,fn($v)=>password_hash($v,PASSWORD_DEFAULT),null,null,$contactCallback);
essentialCheck($contactCapture[1]===$newContact,'contact writer attests precise new insert');
essentialThrows(fn()=>$contactCapture[0]->assertCreatedInOwned($db,$p,$a,7,'contact',$newContact),'native_creation_attestation_invalid','contact creation grant expires after callback');
$revision=$native->get($a,7,101,$newContact)['revision'];$native->save($a,7,101,$contactInput,fn($v)=>password_hash($v,PASSWORD_DEFAULT),$newContact,$revision,function($id,$cap,$writer)use($db,$p,$a){essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,7,'contact',$id),'native_creation_attestation_invalid','edit callback never gains create authority');});
$count=(int)$db->query("SELECT COUNT(*) n FROM {$p}contacts")->row()->n;$contactInput['email']='attested-rollback@example.invalid';$badCapture=null;
essentialThrows(function()use($native,$a,$contactInput,&$badCapture){return $native->save($a,7,101,$contactInput,fn($v)=>password_hash($v,PASSWORD_DEFAULT),null,null,function($id,$cap,$writer)use(&$badCapture){$badCapture=[$writer,$id];throw new DomainException('synthetic_participant_failure');});},'synthetic_participant_failure','participant exception rolls back inserted contact');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}contacts")->row()->n===$count,'failed callback leaves no contact');
essentialThrows(fn()=>$badCapture[0]->assertCreatedInOwned($db,$p,$a,7,'contact',$badCapture[1]),'native_creation_attestation_invalid','finally clears creation grant on callback exception');
echo 'Native creation attestation: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
