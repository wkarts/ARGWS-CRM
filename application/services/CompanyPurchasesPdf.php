<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPurchasesValues.php';

/** Plain text, snapshotted identity and prices. No legacy hooks, remote images or HTML. */
final class CompanyPurchasesPdf
{
    public static function render(array $order,array $received): string
    {
        if (!hash_equals($order['snapshot_hash'],hash('sha256',$order['snapshot_json']))) { throw new RuntimeException('issuer_purchase_integrity_invalid'); }
        $s=CompanyPurchasesValues::decode($order['snapshot_json']);
        if (!class_exists('TCPDF')) { require_once __DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php'; }
        $pdf=new class extends TCPDF { public function __construct() { parent::__construct('P','mm','A4',true,'UTF-8',false,false); $this->tcpdflink=false; } };
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->setMargins(18,18,18); $pdf->setAutoPageBreak(true,18); $pdf->setFont('dejavusans','',10); $pdf->setCreator('CRM'); $pdf->setTitle('Pedido '.$order['reference']); $pdf->AddPage();
        $text='Pedido de compra '.$order['reference']."\nEstado: ".$order['state']."\nEmpresa: ".$s['issuer']['legal_name'].' / '.($s['issuer']['tax_id']??'')."\nFornecedor: ".$s['supplier']['name'].' / '.$s['supplier']['tax_id']."\nEmissão: ".$order['order_date'].' · Entrega prevista: '.$order['expected_date']."\nMoeda: ".$order['currency']."\n\n";
        foreach ($received['lines'] as $line) { $text.=$line['line'].'. '.$line['description'].' — '.CompanyPurchasesValues::display($line['quantity_milli'],3).' '.$line['unit'].' × '.CompanyPurchasesValues::display($line['unit_cents']).' = '.CompanyPurchasesValues::display($line['base_cents'])."\nRecebido: ".CompanyPurchasesValues::display($received['quantities'][$line['line']],3)."\n\n"; }
        $text.='Base: '.CompanyPurchasesValues::display((int)$order['base_cents'])."\nTributo estimado (".CompanyPurchasesValues::display((int)$order['tax_rate']).'%): '.CompanyPurchasesValues::display((int)$order['tax_cents'])."\nTotal estimado: ".CompanyPurchasesValues::display((int)$order['total_cents'])."\n\nO valor final a pagar vem de cada documento fechado e seus recebimentos exclusivos. O arredondamento do tributo ocorre uma vez por documento. Este pedido não é documento fiscal, ordem bancária, comprovante de pagamento nem aceite eletrônico do fornecedor.";
        $pdf->MultiCell(0,6,$text,0,'L'); return $pdf->Output('','S');
    }
}
