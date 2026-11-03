<?php
/** Wizard composition contract: actual PDO rollback and the production identity adapter. */
require __DIR__.'/native_settings.php';
$tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'];
$input=['legal_name'=>'Unified wizard identity','profile'=>['invoice_company_city'=>'Synthetic city']];
settingThrows(fn()=>$identity->saveInOwnedTransaction(41,12,$input),'issuer_owned_transaction_required','identity writer rejects unowned transaction');
$db->trans_begin();
settingThrows(fn()=>$identity->saveInOwnedTransaction(41,12,$input),'issuer_owned_transaction_required','a manually opened transaction is not an owned capability');
essentialCheck($db->pdo->inTransaction(),'manual caller transaction retained');$db->trans_rollback();
settingThrows(fn()=>IssuerTransaction::run($db,$p,['issuers_lock'],fn()=>$identity->saveInOwnedTransaction(41,12,$input)),'issuer_owned_transaction_required','transaction must validate all identity tables');
$beforeIdentity=$identity->read(41);
$beforeAudit=$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n;
settingThrows(fn()=>IssuerTransaction::run($db,$p,$tables,function()use($identity,$input){$identity->saveInOwnedTransaction(41,12,$input);throw new DomainException('synthetic_wizard_failure');}),'synthetic_wizard_failure','later wizard error rolls back identity and audit');
essentialCheck($identity->read(41)===$beforeIdentity,'failed wizard has no half-saved company');
essentialCheck($db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n===$beforeAudit,'failed wizard has no committed identity audit');
IssuerTransaction::run($db,$p,$tables,fn()=>$identity->saveInOwnedTransaction(41,12,$input));
essentialCheck($identity->get(41,'companyname')==='Unified wizard identity' && $identity->get(41,'invoice_company_name')==='Unified wizard identity','wizard and Settings aliases read the same row');
essentialCheck($identity->get(41,'invoice_company_city')==='Synthetic city','wizard profile shares canonical company storage');
settingThrows(fn()=>$identity->saveInOwnedTransaction(41,12,$input),'issuer_owned_transaction_required','capability expires after commit');
settingThrows(fn()=>IssuerTransaction::run($db,$p,$tables,fn()=>$identity->saveInOwnedTransaction(41,13,$input)),'company_settings_access_denied','ordinary administrator cannot write foreign company through composition');
essentialCheck(!$db->pdo->inTransaction(),'all owned transaction exits are closed');
echo 'Canonical wizard transaction: '.$GLOBALS['essentialChecks']." total checks passed.\n";
