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

function coldPdfText(TCPDF $pdf):string{
 $process=proc_open(['pdftotext','-','-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($process))throw new RuntimeException('pdftotext unavailable');
 fwrite($pipes[0],$pdf->Output('','S'));fclose($pipes[0]);$text=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('PDF extraction failed: '.$error);return$text;
}
unset($document['payload']['native_fields']['unknown_field']);
$document['payload']['native_proposal']=['subject'=>'Snapshot proposal subject','proposal_to'=>'Synthetic customer','content'=>'<p>Before item marker</p>{proposal_items}<p>After item marker</p>'];
$text=coldPdfText(CompanySalesPdf::prepare($document));
foreach(['Snapshot proposal subject','Before item marker','After item marker','Synthetic service']as$required)if(!str_contains($text,$required))throw new RuntimeException('Proposal PDF omitted snapshot text: '.$required);
if(!(strpos($text,'Before item marker')<strpos($text,'Synthetic service')&&strpos($text,'Synthetic service')<strpos($text,'After item marker')))throw new RuntimeException('Proposal item placeholder order lost');
$document['payload']['native_proposal']['content']='<p>No marker content</p><img src="https://example.invalid/private.png"><a href="file:///etc/passwd">Literal link</a><script>SECRET_SCRIPT</script><tcpdf method="AddPage" />';
$text=coldPdfText(CompanySalesPdf::prepare($document));
if(!str_contains($text,'No marker content')||!str_contains($text,'Synthetic service')||str_contains($text,'SECRET_SCRIPT'))throw new RuntimeException('Proposal fallback or safe text failed');
$clean=NativeCompanyContentHtml::forPdf($document['payload']['native_proposal']['content']);
if(preg_match('/<(?:img|script|tcpdf)\b|file:/i',$clean))throw new RuntimeException('Unsafe PDF resources survived');
$document['kind']='estimates';$text=coldPdfText(CompanySalesPdf::prepare($document));if(str_contains($text,'Snapshot proposal subject')||str_contains($text,'No marker content'))throw new RuntimeException('Proposal metadata leaked into estimate rendering');
echo "Cold proposal snapshot subject/content, item order/fallback, safe resources and estimate separation passed.\n";
