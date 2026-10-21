<?php
/** Synthetic metadata resolution only. Does not send or enable secondary recovery. */
require __DIR__.'/../support/client_issuer_test_support.php';
require __DIR__.'/../../application/services/PortalClientIdentity.php';
use app\services\PortalClientIdentity;
$pdo->exec('CREATE TABLE q_issuers_installation_identity(id INT,primary_issuer_id INT); INSERT INTO q_issuers_installation_identity VALUES(1,7); CREATE TABLE q_issuers_registry(id INT,active INT); INSERT INTO q_issuers_registry VALUES(7,1),(11,1),(22,1)');
check(PortalClientIdentity::mailOrigin($model->db,'q_',10)===['contact_id'=>10,'client_id'=>1,'issuer_id'=>7,'legacy'=>true],'legacy contact resolves only verified installation primary');
check(PortalClientIdentity::mailOrigin($model->db,'q_',20)['issuer_id']===11,'A contact shared with B retains A mail origin');
check(PortalClientIdentity::mailOrigin($model->db,'q_',30)['issuer_id']===22,'B contact resolves independent origin');
$pdo->exec("UPDATE q_contacts SET email='same@example.invalid' WHERE id IN (20,30)");
check(PortalClientIdentity::mailOrigin($model->db,'q_',20)['issuer_id']===11 && PortalClientIdentity::mailOrigin($model->db,'q_',30)['issuer_id']===22,'duplicate emails never replace explicit contact identity');
function originDenied($operation,$reason){try{$operation();check(false,'expected rejection');}catch(DomainException $e){check($e->getMessage()===$reason,'explicit origin rejection');}}
$pdo->exec('UPDATE q_issuers_registry SET active=0 WHERE id=11');
originDenied(fn()=>PortalClientIdentity::mailOrigin($model->db,'q_',20),'portal_mail_origin_unavailable');
$pdo->exec('UPDATE q_issuers_registry SET active=1 WHERE id=11; INSERT INTO q_issuers_clients VALUES(2,22)');
originDenied(fn()=>PortalClientIdentity::mailOrigin($model->db,'q_',20),'portal_mail_origin_ambiguous');
$pdo->exec('DELETE FROM q_issuers_clients WHERE client_id=2 AND owner_issuer_id=22; UPDATE q_issuers_installation_identity SET primary_issuer_id=NULL');
originDenied(fn()=>PortalClientIdentity::mailOrigin($model->db,'q_',10),'portal_mail_origin_unavailable');
check(PortalClientIdentity::mailOrigin($model->db,'q_',999)===null,'missing contact cannot resolve origin');
$pdo->exec('DROP TABLE q_issuers_registry');
originDenied(fn()=>PortalClientIdentity::mailOrigin($model->db,'q_',20),'portal_mail_origin_schema_required');
echo "Portal mail origin: $n synthetic checks passed; transport integration pending\n";
