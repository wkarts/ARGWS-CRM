<?php
/** Cold PDF entry point: no admin services or earlier suites may supply dependencies. */
define('FCPATH',dirname(__DIR__,2).'/');define('APPPATH',FCPATH.'application/');define('BASEPATH',FCPATH.'system/');
require APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
require APPPATH.'services/CompanySalesPdf.php';
$document=['kind'=>'estimates','number'=>1,'state'=>'accepted','total'=>'100.00',
    'payload'=>['date'=>'2026-11-06','due_date'=>'2026-12-06','note'=>'Synthetic portal PDF',
        'items'=>[['description'=>'Synthetic service','qty'=>'1.00','rate'=>'100.00','taxes'=>[]]],
        'native_fields'=>['terms'=>'Reviewed native terms','billing_street'=>'Synthetic street','include_shipping'=>0,'show_shipping_on_invoice'=>0]],
    'context'=>['identity'=>['legal_name'=>'Synthetic issuer','tax_id'=>'11222333000181'],
        'recipient'=>['name'=>'Synthetic customer','tax_id'=>'11144477735'],'currency'=>['name'=>'BRL']]];
foreach(['estimates','proposals']as$kind){$document['kind']=$kind;$pdf=CompanySalesPdf::prepare($document);$bytes=$pdf->Output('','S');if(!str_starts_with($bytes,'%PDF-')||strlen($bytes)<1000)throw new RuntimeException('Cold sales renderer did not produce a real PDF');}
$document['payload']['native_fields']['unknown_field']='denied';try{CompanySalesPdf::prepare($document);throw new RuntimeException('Unknown native fields accepted');}catch(DomainException$expected){}
echo "Cold estimate/proposal PDF bootstrap and native field rejection: 3 checks passed.\n";
