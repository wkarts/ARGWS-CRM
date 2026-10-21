<?php

declare(strict_types=1);

trait CompanyPurchasesReports
{
    private function reportRows(int $issuer,string $from,string $asOf,int $after,int $limit): array
    {
        $ids=$this->observedRows('SELECT p.id FROM '.$this->p.'company_purchase_payables p JOIN '.$this->p.'company_purchase_documents d ON d.id=p.document_id AND d.issuer_id=p.issuer_id WHERE p.issuer_id=? AND p.id>? AND d.document_date>=? AND d.document_date<=? ORDER BY p.id LIMIT '.($limit+1),[$issuer,$after,$from,$asOf]);
        $more=count($ids)>$limit; if ($more) { array_pop($ids); } $items=[]; $totals=[];
        foreach ($ids as $id) {
            $ap=$this->object($issuer,'payable',(int)$id['id']); $position=$this->payablePosition($issuer,$ap,$asOf); $doc=$position['document']; $currency=$ap['currency'];
            $item=['id'=>(int)$ap['id'],'supplier'=>$position['supplier_name'],'order'=>$position['order_reference'],'document'=>$doc['reference'],'vendor_number'=>$doc['vendor_number'],'document_date'=>$doc['document_date'],'due_date'=>$ap['due_date'],'currency'=>$currency,'amount_cents'=>(int)$ap['amount_cents'],'declared_paid_cents'=>$position['declared_paid_cents'],'outstanding_cents'=>$position['outstanding_cents'],'overdue_cents'=>$ap['due_date']<$asOf?$position['outstanding_cents']:0,'expense_id'=>(int)$ap['expense_id'],'base_currency'=>$position['base_currency'],'fx_millionths'=>$position['fx_millionths']];
            $totals[$currency]??=['amount_cents'=>0,'declared_paid_cents'=>0,'outstanding_cents'=>0,'overdue_cents'=>0];
            foreach (array_keys($totals[$currency]) as $f) { $totals[$currency][$f]+=$item[$f]; }
            $items[]=$item;
        }
        return ['from'=>$from,'as_of'=>$asOf,'items'=>$items,'totals_for_this_page'=>$totals,'next'=>$more?(int)$items[count($items)-1]['id']:null,'basis'=>'posted purchase documents; manual external declarations; no bank confirmation; expense cost counted once in financial report'];
    }

    public function report($issuer,$actor,string $from,string $asOf,int $after=0,int $limit=100): array
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); CompanyPurchasesValues::date($from); CompanyPurchasesValues::date($asOf); CompanyPurchasesValues::integer($after); CompanyPurchasesValues::integer($limit,1,100);
        if ($from>$asOf) { throw new DomainException('company_purchase_invalid'); }
        return $this->transaction($issuer,$actor,'report','report',null,fn(): array=>$this->reportRows($issuer,$from,$asOf,$after,$limit));
    }

    public function export($issuer,$actor,string $from,string $asOf): string
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); CompanyPurchasesValues::date($from); CompanyPurchasesValues::date($asOf);
        if ($from>$asOf) { throw new DomainException('company_purchase_invalid'); }
        return $this->transaction($issuer,$actor,'report','export',null,function () use ($issuer,$from,$asOf): string {
            $report=$this->reportRows($issuer,$from,$asOf,0,5000);
            if ($report['next']!==null) { throw new DomainException('company_purchase_export_limit'); }
            $stream=fopen('php://temp','w+');
            if ($stream===false) { throw new RuntimeException('issuer_purchase_export_failed'); }
            $clean=static fn($v): string=>preg_match('/^[=+\-@\t\r]/',(string)$v)?"'".$v:(string)$v;
            fputcsv($stream,['Contas a pagar: declarações externas manuais; não são confirmação nem ordem bancária'],',','"','');
            fputcsv($stream,['Fornecedor','Pedido','Documento interno','Documento fornecedor','Data','Vencimento','Moeda','Valor','Quitação declarada','Em aberto','Vencido','Despesa canônica','Moeda base capturada','Câmbio declarado capturado'],',','"','');
            foreach ($report['items'] as $r) {
                $line=[$r['supplier'],$r['order'],$r['document'],$r['vendor_number'],$r['document_date'],$r['due_date'],$r['currency'],CompanyPurchasesValues::display($r['amount_cents']),CompanyPurchasesValues::display($r['declared_paid_cents']),CompanyPurchasesValues::display($r['outstanding_cents']),CompanyPurchasesValues::display($r['overdue_cents']),$r['expense_id'],$r['base_currency'],CompanyPurchasesValues::display($r['fx_millionths'],6)];
                fputcsv($stream,array_map($clean,$line),',','"','');
            }
            rewind($stream); $csv=stream_get_contents($stream); fclose($stream);
            if (!is_string($csv)) { throw new RuntimeException('issuer_purchase_export_failed'); }
            return $csv;
        });
    }

    public function orderPdf($issuer,$actor,$id): string
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); $id=IssuerPolicy::id($id);
        return $this->transaction($issuer,$actor,'view','order_pdf',null,function () use ($issuer,$id): string {
            $order=$this->object($issuer,'order',$id); $received=$this->received($issuer,$order);
            if ($this->portal!==null&&((int)$order['request_id']!==$this->portal['request_id']||(int)$order['supplier_id']!==$this->portal['supplier_id'])) { throw new DomainException('company_purchase_portal_denied'); }
            require_once __DIR__.'/CompanyPurchasesPdf.php'; return CompanyPurchasesPdf::render($order,$received);
        });
    }
}
