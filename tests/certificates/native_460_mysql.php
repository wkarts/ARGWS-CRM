<?php
/** Actual immutable 457–459 and new 460 upgrade on a disposable MySQL DB. */
declare(strict_types=1);
require __DIR__.'/native_upgrade_support.php';
if (!is_file(APPPATH.'migrations/460_version_460.php')) {
    fwrite(STDERR, "The actual 460 migration is required; native upgrade was NOT executed.\n");
    exit(2);
}
a1check(str_contains(file_get_contents(APPPATH.'migrations/460_version_460.php'), 'EmailTemplateReconciler::upgrade'), '460 composes the actual email reconciliation helper');
require_once APPPATH.'services/EmailTemplateReconciler.php';

function a1nativeEmailSeed(): array
{
    [$db, $app, $source] = a1nativeSeed(460);
    // Operational choices and third-language content must survive localization.
    $db->query('UPDATE a1_emailtemplates SET active=0,plaintext=1,`order`=17,fromname=?,fromemail=?', ['SYNTHETIC SENDER', 'fixture@example.invalid']);
    $thirdLanguage = ['issuer_id'=>20] + array_replace($source, [
        'language'=>'spanish', 'name'=>'CUSTOM THIRD LANGUAGE', 'subject'=>'CUSTOM THIRD SUBJECT',
        'message'=>'CUSTOM THIRD BODY {contact_firstname}', 'active'=>0,
    ]);
    $db->query('INSERT INTO a1_emailtemplates (`'.implode('`,`',array_keys($thirdLanguage)).'`) VALUES('.implode(',',array_fill(0,count($thirdLanguage),'?')).')', array_values($thirdLanguage));
    $db->emailWrites = 0;
    return [$db, $app, $source, a1nativeMails($db)];
}

function a1nativeEmailRow(array $rows, ?int $issuer, string $language): array
{
    $found = array_values(array_filter($rows, static fn($row) =>
        ($row['issuer_id']===null ? null : (int)$row['issuer_id'])===$issuer && $row['language']===$language
    ));
    if (count($found)!==1) throw new RuntimeException('Expected exactly one company language variant');
    return $found[0];
}

function a1nativeEmailReady(A1NativeDb $db, array $before, array $source): void
{
    $after = a1nativeMails($db);
    a1check(count($after)===5, '460 adds only the missing primary PT-BR variant');
    foreach ([[null,'english'],[10,'portuguese_br'],[20,'spanish']] as [$issuer,$language]) {
        a1check(a1nativeEmailRow($after,$issuer,$language)===a1nativeEmailRow($before,$issuer,$language),
            '460 preserves original/custom/third-language content and disabled state: '.($issuer??'primary').'/'.$language);
    }
    $definition = EmailTemplatesPtBr::sourceDefinitions()[$source['slug']][0];
    foreach ([null,20] as $issuer) {
        $row = a1nativeEmailRow($after,$issuer,'portuguese_br');
        $original = a1nativeEmailRow($before,$issuer,$issuer===null?'english':'portuguese_br');
        a1check($row['name']===$definition['name'] && $row['subject']===$definition['subject']
            && $row['message']===EmailTemplatesPtBr::translateMessage($source['message'])
            && $row['message']!==$source['message'], '460 translates exact factory content for '.($issuer??'primary'));
        foreach (['active','plaintext','order','fromname','fromemail','issuer_id','slug','type'] as $field) {
            a1check($row[$field]===$original[$field], '460 preserves '.$field.' for '.($issuer??'primary'));
        }
        preg_match_all('/<[^>]*>|\{[^{}]+\}/u', $source['message'], $oldTokens);
        preg_match_all('/<[^>]*>|\{[^{}]+\}/u', $row['message'], $newTokens);
        a1check($oldTokens[0]===$newTokens[0], '460 preserves HTML and merge fields for '.($issuer??'primary'));
    }
}

[$db,$app,$source,$before] = a1nativeEmailSeed();
a1check(!$db->table_exists('a1_issuer_certificate_credentials'), '456 baseline primes native table cache before all CREATEs');
$result = a1nativeRun($app);
a1check($result['success']===true && a1nativeVersion($db)===460, 'actual native upgrade 456→457→458→459→460 confirms persisted target');
a1check($db->markerWrites===[457,458,459,460], 'native runner tracks all four contiguous published/composed steps');
a1check(count(array_filter($GLOBALS['a1_native_events'],static fn($event)=>$event==='database_updated'))===1
    && IssuerCore::$reconciled===1, '460 completion fires only after confirmed tracking');
a1nativeReady($db);
a1nativeEmailReady($db,$before,$source);
a1check($db->emailWrites===2, 'actual helper performs one variant insert and one factory update');

$db->query('INSERT INTO a1_company_client_profiles VALUES(?,?,?)',[10,101,'{"custom":"preserve"}']);
$certificate = a1fixture();
(new IssuerCertificateManager($db,'a1_',a1encryption()))->replace(10,2,0,$certificate['pfx'],$certificate['password']);
$material = $db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array();
$audit = $db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array();
$mails = a1nativeMails($db);
$steps = $db->markerWrites;
$writes = $db->emailWrites;
a1check(a1nativeRun($app)['success']===true && $db->markerWrites===$steps && $db->emailWrites===$writes,
    'already-current 460 runner does not repeat migrations or email writes');
$db->query('UPDATE a1_migrations SET version=459');
a1check(a1nativeRun($app)['success']===true && a1nativeVersion($db)===460, 'actual 460 replay succeeds after losing only tracking');
a1check(EmailTemplateReconciler::upgrade($db,'a1_')===0 && $db->emailWrites===$writes && a1nativeMails($db)===$mails,
    '460 replay and direct helper replay are read-only with no variant/content drift');
a1check($db->query('SELECT profile_json FROM a1_company_client_profiles')->row()->profile_json==='{"custom":"preserve"}'
    && $db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array()===$material
    && $db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array()===$audit,
    '460 replay preserves private client profile, encrypted certificate and audit');

foreach (['email_second_write','marker_false','marker_ack_without_write','readback'] as $failure) {
    [$db,$app,$source,$before] = a1nativeEmailSeed();
    if ($failure==='email_second_write') $db->failEmailWriteAt=2;
    elseif ($failure==='marker_false') $db->failMarker=460;
    elseif ($failure==='marker_ack_without_write') $db->pretendMarker=460;
    else $db->failReadback=460;
    $result = a1nativeRun($app);
    a1check($result['success']===false, 'native 460 '.$failure.' cannot claim upgrade success');
    a1check(a1nativeVersion($db)===($failure==='readback'?460:459), 'native 460 '.$failure.' leaves truthful persisted marker');
    a1check(!in_array('database_updated',$GLOBALS['a1_native_events'],true) && IssuerCore::$reconciled===0,
        'native 460 '.$failure.' suppresses completion hook/reconciliation');
    if ($failure==='email_second_write') {
        a1check($db->emailWrites===2 && a1nativeMails($db)===$before, 'second email write failure rolls back first write and all company variants');
        a1check($db->markerWrites===[457,458,459], 'failed email helper never attempts the 460 marker');
    } else {
        a1check($db->markerWrites===[457,458,459,460], 'tracking failure occurs after all actual migration helpers');
        a1nativeEmailReady($db,$before,$source);
    }
    $db->failEmailWriteAt=null;
    $db->failMarker=null;
    $db->pretendMarker=null;
    $db->failReadback=null;
    $writes = $db->emailWrites;
    a1check(a1nativeRun($app)['success']===true && a1nativeVersion($db)===460, 'native 460 '.$failure.' resumes after repair');
    a1nativeReady($db);
    a1nativeEmailReady($db,$before,$source);
    a1check($db->emailWrites===$writes+($failure==='email_second_write'?2:0), 'retry performs only missing email work for '.$failure);
    a1check(EmailTemplateReconciler::upgrade($db,'a1_')===0, 'post-retry helper replay has zero changes for '.$failure);
}
echo 'Native460 '.(getenv('A1_NATIVE_SQLITE_SMOKE')==='1'?'SQLite smoke (NOT MySQL proof)':'MySQL')
    .": actual migrations/helpers, email rollback/replay and App tracking validated; minimal 456 fixture, unrelated reconciliation adapted.\n";
