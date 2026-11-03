<?php
/** Real GD/TCPDF regression. All diagnostics remain fatal, including PHP8.5 deprecations. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$root=dirname(__DIR__);require $root.'/application/vendor/tecnickcom/tcpdf/tcpdf.php';require $root.'/application/vendor/tecnickcom/tcpdf/tcpdf_barcodes_1d.php';require $root.'/application/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';require $root.'/application/services/NativeCompanyFileContent.php';
function gdCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$im=imagecreatetruecolor(12,8);imagealphablending($im,false);imagesavealpha($im,true);$color=imagecolorallocatealpha($im,23,67,123,64);imagefill($im,0,0,$color);ob_start();imagepng($im);$png=ob_get_clean();
$doc=new TCPDF();$doc->setPrintHeader(false);$doc->setPrintFooter(false);$doc->AddPage();$doc->Image('@'.$png,10,10,12,8,'PNG');$pdf=$doc->Output('','S');gdCheck(str_starts_with($pdf,'%PDF-')&&str_contains($pdf,'/SMask'),'actual transparent PNG remains embedded with alpha mask');
foreach([new TCPDFBarcode('SYNTHETIC123','C128'),new TCPDF2DBarcode('SYNTHETIC123','QRCODE,L')]as$barcode){$bytes=$barcode->getBarcodePngData();$size=getimagesizefromstring($bytes);gdCheck(is_array($size)&&$size[0]>0&&$size[1]>0&&$size['mime']==='image/png','actual barcode renders PNG');}
$dir=sys_get_temp_dir().'/native-gd-'.bin2hex(random_bytes(8));mkdir($dir,0700);
try{$opaque=imagecreatetruecolor(12,8);$a=TCPDF_IMAGES::_toPNG($opaque,$dir.'/image.png');gdCheck(is_array($a)&&$a['w']===12&&$a['h']===8&&!file_exists($dir.'/image.png'),'PNG helper preserves dimensions and cleans temporary file');$b=TCPDF_IMAGES::_toJPEG($opaque,90,$dir.'/image.jpg');gdCheck(is_array($b)&&$b['w']===12&&$b['h']===8&&!file_exists($dir.'/image.jpg'),'JPEG helper preserves dimensions and cleans temporary file');}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
[$name,$mime,$safe]=NativeCompanyFileContent::normalize('synthetic.png',$png,'.png',1048576);$decoded=imagecreatefromstring($safe);$pixel=imagecolorsforindex($decoded,imagecolorat($decoded,0,0));gdCheck($name==='synthetic.png'&&$mime==='image/png'&&imagesx($decoded)===12&&imagesy($decoded)===8&&$pixel['red']===23&&$pixel['green']===67&&$pixel['blue']===123,'native bounded image normalization retains pixels');
echo "TCPDF/GD compatibility: real PDF alpha mask, 1D/2D barcodes, PNG/JPEG conversion and native bounded image content passed with fatal diagnostics.\n";
