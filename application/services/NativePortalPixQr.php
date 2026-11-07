<?php
declare(strict_types=1);
/** In-memory QR from the canonical private payload; no URL, file, cache, image bytes or SVG from upstream. */
final class NativePortalPixQr
{
 public static function svg(string $payload):string
 {
  if(strlen($payload)<20||strlen($payload)>8192||preg_match('/[\x00-\x1f\x7f]/',$payload))throw new DomainException('asaas_pix_qr_unavailable');
  require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
  $matrix=(new TCPDF2DBarcode($payload,'QRCODE,M'))->getBarcodeArray();$n=$matrix['num_rows']??0;
  if(!is_int($n)||$n<21||$n>177||($matrix['num_cols']??null)!==$n||count($matrix['bcode']??[])!==$n)throw new DomainException('asaas_pix_qr_unavailable');
  $size=$n+8;$path='';foreach($matrix['bcode'] as $y=>$row){if(count($row)!==$n)throw new DomainException('asaas_pix_qr_unavailable');foreach($row as $x=>$cell)if((int)$cell===1)$path.='M'.($x+4).' '.($y+4).'h1v1h-1z';}
  return '<svg role="img" aria-label="Código Pix" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" width="280" height="280" style="max-width:100%;height:auto" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="white"/><path fill="black" d="'.$path.'"/></svg>';
 }
}
