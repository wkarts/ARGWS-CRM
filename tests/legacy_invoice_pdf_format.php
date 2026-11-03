<?php
// Exercise the actual legacy format lookup without the invoice's unrelated hydration.
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
class TCPDF {}
$options=['pdf_format_invoice'=>'LETTER-PORTRAIT'];
function get_option($name){return $GLOBALS['options'][$name]??'';}
function hooks(){return new class {function apply_filters($name,$value,...$args){return $value;}};}
require APPPATH.'helpers/pdf_helper.php';
require APPPATH.'libraries/pdf/Invoice_pdf.php';
$pdf=(new ReflectionClass(Invoice_pdf::class))->newInstanceWithoutConstructor();
require APPPATH.'services/CompanyInvoicePresentation.php';
$snapshot=['pdf_preferences'=>['format'=>1,'kind'=>'invoices','choice'=>'LETTER-PORTRAIT']];
$checks=0;
foreach(['LETTER-PORTRAIT'=>['P','LETTER'],'A4-PORTRAIT'=>['P','A4'],'A4-LANDSCAPE'=>['L','A4'],'LETTER-LANDSCAPE'=>['L','LETTER']] as $choice=>$expected){
 $options['pdf_format_invoice']=$choice;
 if($pdf->get_format_array()!==['orientation'=>$expected[0],'format'=>$expected[1]])throw new RuntimeException('Legacy renderer must use the CURRENT option: '.$choice);
 if(CompanyInvoicePresentation::specification($snapshot,'invoices')!==['orientation'=>'P','format'=>'LETTER'])throw new RuntimeException('Native snapshot must stay immutable while the global option changes');
 ++$checks;
}
echo "Actual legacy current-option and native immutable-snapshot contrast: $checks PASS\n";
