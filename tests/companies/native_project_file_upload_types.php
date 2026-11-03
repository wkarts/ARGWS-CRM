<?php
/** Company-specific native upload policy, receipts and byte integrity. */
require __DIR__.'/projects_extras.php';
require_once APPPATH.'services/NativeCompanyUploadPreferences.php';
$parent=$extras->get($b,7,'project',$pb);$revision=(int)$parent['revision'];$request=$key('native-project-text');$body="synthetic document\n";
$extras->uploadNative($b,7,'project',$pb,$revision,$request,'notes.txt',$body);
$loaded=$extras->file($b,7,'project',$pb,$request);
essentialCheck($loaded['content']===$body&&$loaded['mime']==='text/plain','actual allowed text bytes survive private storage and download');
$extras->uploadNative($b,7,'project',$pb,$revision,$request,'notes.txt',$body);
essentialCheck(count(array_filter($extras->files($b,7,'project',$pb)['items'],fn($r)=>$r['file_key']===$request))===1,'native upload replay retains one file');
essentialThrows(fn()=>$extras->uploadNative($a,7,'project',$pb,$revision,$key('foreign-native-file'),'notes.txt',$body),'company_project_access_denied','foreign issuer cannot upload to project');
$current=$extras->get($b,7,'project',$pb)['revision'];
if(class_exists('ZipArchive')){
    $temporary=tempnam(sys_get_temp_dir(),'native-docx-');$zip=new ZipArchive();if($zip->open($temporary,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('synthetic archive unavailable');
    $zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString('word/document.xml','<document/>');$zip->close();$document=file_get_contents($temporary);unlink($temporary);
    $docKey=$key('native-project-docx');$extras->uploadNative($b,7,'project',$pb,$current,$docKey,'sample.docx',$document);
    $doc=$extras->file($b,7,'project',$pb,$docKey);essentialCheck($doc['content']===$document&&$doc['mime']==='application/octet-stream','valid office archive remains forced-download bytes');
    $current=$extras->get($b,7,'project',$pb)['revision'];
}
essentialThrows(fn()=>$extras->uploadNative($b,7,'project',$pb,$current,$key('invalid-native-file'),'payload.php','<?php echo 1; ?>'),'native_file_type_not_allowed','executable extension is refused');
essentialThrows(fn()=>$extras->uploadNative($b,7,'project',$pb,$current,$key('bad-docx-file'),'fake.docx','not an office archive'),'native_file_content_invalid','office extension cannot disguise arbitrary bytes');
essentialThrows(fn()=>$extras->uploadNative($b,7,'project',$pb,$current,$key('path-native-file'),'../notes.txt',$body),'native_file_content_invalid','path traversal names are refused');
$settings=new CompanySettingsStorage($db,$p);$settings->mutate($b,7,'update','allowed_files','.pdf');
essentialThrows(fn()=>$extras->uploadNative($b,7,'project',$pb,$current,$key('denied-native-file'),'notes.txt',$body),'native_file_type_not_allowed','issuer policy controls allowed type');
$settings->mutate($b,7,'update','allowed_files',NativeCompanyUploadPreferences::defaultValue('allowed_files'));
$db->query('UPDATE '.$p.'company_project_files SET content=? WHERE file_key=?',['corrupted',$request]);
try{essentialThrows(fn()=>$extras->file($b,7,'project',$pb,$request),'issuer_project_file_corrupt','modified stored bytes fail integrity verification');}
finally{$db->query('UPDATE '.$p.'company_project_files SET content=? WHERE file_key=?',[$body,$request]);}
echo "Native project upload policy, replay, A/B and byte integrity passed.\n";
