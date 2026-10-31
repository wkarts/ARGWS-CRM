<?php
declare(strict_types=1);
/** Raster bytes captured with the document; never reads a path, URL or current settings. */
final class CompanyDocumentBranding
{
    private static function defaultLogo():string
    {
        $root=defined('FCPATH')?realpath(FCPATH):realpath(dirname(__DIR__,2));
        $file=$root===false?false:realpath($root.DIRECTORY_SEPARATOR.'assets/images/argws/logo-dark.png');
        if($root===false||$file===false||dirname($file)!==$root.DIRECTORY_SEPARATOR.'assets/images/argws'||!is_file($file))return '';
        $bytes=@file_get_contents($file);return is_string($bytes)&&$bytes!==''?'data:image/png;base64,'.base64_encode($bytes):'';
    }

    public static function image(TCPDF $pdf,array $identity,string $key,float $width=40):void
    {
        $value=$identity[$key]??'';if($value===''&&$key==='logo_inline')$value=self::defaultLogo();if($value==='')return;
        if(!is_string($value)||strlen($value)>700000||!preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#D',$value,$match))throw new DomainException('company_document_asset_invalid');
        $bytes=base64_decode($match[2],true);$size=$bytes===false?false:@getimagesizefromstring($bytes);
        if($bytes===false||strlen($bytes)>524288||!$size||($size['mime']??'')!=='image/'.$match[1]||$size[0]<1||$size[1]<1||$size[0]>4096||$size[1]>4096||$size[0]*$size[1]>16000000)throw new DomainException('company_document_asset_invalid');
        $height=$width*$size[1]/$size[0];if($height>28){$width*=28/$height;$height=28;}
        if($pdf->GetY()+$height+5>$pdf->getPageHeight()-20)$pdf->AddPage();
        $y=$pdf->GetY();$pdf->Image('@'.$bytes,$pdf->GetX(),$y,$width,$height,strtoupper($match[1]));$pdf->SetY($y+$height+5);
    }
    public static function address(array $identity):string
    {
        $parts=[];foreach(['address','city','state','postcode','country_code','phone','email']as$key){$value=$identity[$key]??'';if(!is_string($value))throw new DomainException('company_document_identity_invalid');if($value!=='')$parts[]=$value;}return implode(' · ',$parts);
    }
}
