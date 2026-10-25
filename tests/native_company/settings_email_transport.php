<?php
require __DIR__.'/settings_email.php';
$current=$mail->page(41,7);$selection=$current['values'];$selection['email_protocol']='google';
$selected=$mail->save(41,7,$current['revision'],$selection,$seal439,$open439);
essentialCheck($selected['transport']['mode']==='google'&&$selected['portal']['mode']==='google','same native protocol selection controls both bounded delivery policies');
$policy=new CompanyMailTransportPolicy($db,$p);$portal=new CompanyPortalMailConfig($db,$p);
settingThrows(fn()=>IssuerTransaction::run($db,$p,$mail->tables(),fn()=>$policy->stateInOwnedTransaction(41)),'company_google_mail_connection_required','configured account selection does not grant document dispatch before OAuth');
settingThrows(fn()=>IssuerTransaction::run($db,$p,$mail->tables(),fn()=>$portal->stateInOwnedTransaction(41)),'company_google_mail_connection_required','configured account selection does not grant portal dispatch before OAuth');
essentialCheck($mail->page(82,7)['transport']['mode']==='legacy'&&$mail->page(82,7)['portal']['mode']==='native','other issuer transport unchanged');
$next=$selected['values'];$next['email_protocol']='microsoft';$next['smtp_host']='changed-late.example.invalid';
$db->query("CREATE TRIGGER native_mail_selection_fail BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_portal_mail_transport_selected' BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$mail->save(41,7,$selected['revision'],$next,$seal439,$open439),'issuer_mail_transport_write_unconfirmed','ignored final transport audit aborts entire native form');$db->query('DROP TRIGGER native_mail_selection_fail');
essentialCheck($mail->page(41,7)===$selected,'late portal selection failure rolls back SMTP, account and document selection together');
$restored=$mail->save(41,7,$selected['revision'],array_replace($selected['values'],['email_protocol'=>'smtp']),$seal439,$open439);
essentialCheck($restored['transport']['mode']==='native'&&$restored['portal']['mode']==='native','explicit original SMTP selection restores native transport without provider fallback');
echo "Native email protocol selection: actual atomic policy composition, unconsented dispatch denial and late audit rollback passed.\n";
