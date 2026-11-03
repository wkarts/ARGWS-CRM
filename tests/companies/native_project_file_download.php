<?php
/** Staff download service keeps original project identity and validates stored bytes. */
require __DIR__.'/native_project_file_upload_types.php';
require_once APPPATH.'services/NativeCompanyProjectFileDownload.php';
$download=new NativeCompanyProjectFileDownload($db,$p);$canonical=NativeCompanyResourceId::encode('company_project',$pb);
$fileData=$download->read($b,7,$canonical,$request);
essentialCheck($fileData['filename']==='notes.txt'&&$fileData['content']===$body&&$fileData['mime']==='text/plain','same bytes read through scoped project download');
essentialThrows(fn()=>$download->read($a,7,$canonical,$request),'native_resource_reference_denied','foreign issuer cannot download project file');
essentialThrows(fn()=>$download->read($b,7,(string)$pb,$request),'native_resource_reference_denied','legacy numeric project cannot alias owned project');
essentialThrows(fn()=>$download->read($b,7,$canonical,'invalid'),'company_project_file_invalid','malformed file key refused');
echo "Native project staff file download service A/B and integrity passed; HTTP remains separately tested.\n";
