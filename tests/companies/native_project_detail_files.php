<?php
/** Same project files reader is ready for the original project tab; route is still closed. */
require __DIR__.'/projects_extras.php';
require_once APPPATH.'services/NativeCompanyProjectDetail.php';
$detail=new NativeCompanyProjectDetail($db,$p);$canonical=NativeCompanyResourceId::encode('company_project',$pb);
$result=$detail->files($b,7,$canonical);
essentialCheck(count($result['items'])===1&&$result['items'][0]['file_key']===$file&&$result['items'][0]['filename']==='plain.pdf','owned file list uses actual project metadata');
essentialCheck(!array_key_exists('content',$result['items'][0]),'file list does not expose private bytes');
essentialThrows(fn()=>$detail->files($a,7,$canonical),'native_resource_reference_denied','another issuer cannot read project files');
essentialThrows(fn()=>$detail->files($b,7,(string)$pb),'native_resource_reference_denied','raw legacy ID cannot alias an owned project');
echo "Project detail owned file metadata and A/B isolation passed; file UI and route remain closed.\n";
