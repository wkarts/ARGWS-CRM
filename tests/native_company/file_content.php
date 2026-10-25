<?php
require dirname(__DIR__,2).'/application/services/NativeCompanyFileContent.php';
function fcCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
function fcDeny(callable $fn){try{$fn();}catch(DomainException $e){return;}throw new RuntimeException('Expected rejection');}
function fcZip(array $files):string{$body='';$central='';foreach($files as$n=>$b){$offset=strlen($body);$crc=crc32($b);$body.="PK\x03\x04".pack('vvvvvVVVvv',20,0,0,0,0,$crc,strlen($b),strlen($b),strlen($n),0).$n.$b;$central.="PK\x01\x02".pack('vvvvvvVVVvvvvvVV',20,20,0,0,0,0,$crc,strlen($b),strlen($b),strlen($n),0,0,0,0,0,$offset).$n;}return$body.$central."PK\x05\x06".pack('vvvvVVv',0,0,count($files),count($files),strlen($central),strlen($body),0);}
$allowed='.pdf,.png,.jpg,.jpeg,.txt,.doc,.xls,.docx,.xlsx,.zip,.rar';
$pdf="%PDF-1.4\n".str_repeat('x',2097152);
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.pdf',$pdf,$allowed,2097152));
fcCheck(NativeCompanyFileContent::normalize('a.pdf',$pdf,$allowed,20971520)[2]===$pdf,'explicit higher configured limit supports actual >2MiB PDF');
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.pdf',$pdf,'.txt',20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.php','<?php x();','.php',20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('../a.txt','x',$allowed,20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.txt',"a\0b",$allowed,20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.zip',"PK\x03\x04junk",$allowed,20971520));
$doc=fcZip(['[Content_Types].xml'=>'<Types/>','word/document.xml'=>'<document/>']);fcCheck(NativeCompanyFileContent::normalize('a.docx',$doc,$allowed,20971520)[1]==='application/octet-stream','Office container remains untrusted download');
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.xlsx',$doc,$allowed,20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.zip',fcZip(['../outside'=>'x']),$allowed,20971520));
fcDeny(fn()=>NativeCompanyFileContent::normalize('a.docx',fcZip(['[Content_Types].xml'=>'x','word/document.xml'=>'x','word/vbaProject.bin'=>'x']),$allowed,20971520));
$picture=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAQAABQAAWX1h1kAAAAASUVORK5CYII=');$image=NativeCompanyFileContent::normalize('a.png',$picture,$allowed,20971520);fcCheck($image[1]==='image/png'&&getimagesizefromstring($image[2])[0]===3,'image actually decoded/reencoded');
fcCheck(NativeCompanyFileContent::maximum('2M','8M')===2097152,'PHP lower configured upload bound preserved');
fcCheck(NativeCompanyFileContent::maximum('0','0')===20971520,'unlimited PHP config retains hard cap');
fcDeny(fn()=>NativeCompanyFileContent::maximum('nonsense','8M'));
echo "Native configured file content: 15 real checks PASS\n";
