<?php
declare(strict_types=1);

/** Bounded, snapshot-owned visual options from the original PDF settings. */
final class CompanyPdfLayout
{
    public const DEFAULTS=[
        'swap_pdf_info'=>'0','pdf_table_heading_color'=>'#e8edf2','pdf_table_heading_text_color'=>'#030712',
        'pdf_logo_width'=>'110','show_status_on_pdf_ei'=>'1','show_page_number_on_pdf'=>'0',
        'show_transactions_on_invoice_pdf'=>'0','show_pay_link_to_invoice_pdf'=>'0',
        'show_pdf_signature_invoice'=>'1','show_pdf_signature_estimate'=>'1','show_pdf_signature_proposal'=>'1',
        'show_pdf_signature_credit_note'=>'1','show_pdf_signature_contract'=>'1',
    ];
    public static function validateSettings(array $values):void
    {
        if(array_diff(array_keys($values),array_keys(self::DEFAULTS)))throw new DomainException('company_document_layout_invalid');
        foreach($values as$key=>$value){
            if(!is_string($value))throw new DomainException('company_document_layout_invalid');
            $valid=match($key){
                'pdf_table_heading_color','pdf_table_heading_text_color'=>preg_match('/^#[a-fA-F0-9]{6}$/D',$value)===1,
                'pdf_logo_width'=>preg_match('/^[1-9][0-9]{1,2}$/D',$value)===1&&(int)$value>=20&&(int)$value<=300,
                default=>in_array($value,['0','1'],true),
            };
            if(!$valid)throw new DomainException('company_document_layout_invalid');
        }
    }
    public static function capture(CompanySettingsStorage $store,int $issuer,string $kind):?array
    {
        $values=[];$configured=false;
        foreach(self::DEFAULTS as$key=>$default){$exists=$store->exists($issuer,$key);$configured=$configured||$exists;$values[$key]=$exists?$store->get($issuer,$key):$default;}
        if(!$configured)return null;self::validateSettings($values);
        $signature='show_pdf_signature_'.match($kind){'invoices'=>'invoice','creditnotes'=>'credit_note','contracts'=>'contract','estimates'=>'estimate','proposals'=>'proposal',default=>throw new DomainException('company_document_layout_invalid')};
        return ['version'=>1,'swap_info'=>$values['swap_pdf_info']==='1','heading_color'=>strtolower($values['pdf_table_heading_color']),'heading_text_color'=>strtolower($values['pdf_table_heading_text_color']),'logo_width'=>(int)$values['pdf_logo_width'],'show_status'=>!in_array($kind,['invoices','estimates'],true)||$values['show_status_on_pdf_ei']==='1','show_page_number'=>$values['show_page_number_on_pdf']==='1','show_signature'=>$values[$signature]==='1','show_transactions'=>$kind==='invoices'&&$values['show_transactions_on_invoice_pdf']==='1','show_pay_link'=>$kind==='invoices'&&$values['show_pay_link_to_invoice_pdf']==='1'];
    }
    public static function historical(string $kind):array
    {
        return ['version'=>1,'swap_info'=>false,'heading_color'=>'#e8edf2','heading_text_color'=>'#030712','logo_width'=>110,'show_status'=>true,'show_page_number'=>$kind==='invoices','show_signature'=>true,'show_transactions'=>false,'show_pay_link'=>false];
    }
    public static function parties(string $issuerHtml,string $recipientHtml,array $layout):string
    {
        self::validate($layout);$left=$layout['swap_info']?$recipientHtml:$issuerHtml;$right=$layout['swap_info']?$issuerHtml:$recipientHtml;
        return '<table width="100%" cellpadding="4"><tr><td width="50%">'.$left.'</td><td width="50%" align="right">'.$right.'</td></tr></table>';
    }
    public static function validate($value):array
    {
        if(!is_array($value)||array_keys($value)!==array_keys(self::historical('invoices'))||$value['version']!==1||!is_int($value['logo_width']))throw new DomainException('company_document_layout_invalid');
        foreach(['swap_info','show_status','show_page_number','show_signature','show_transactions','show_pay_link']as$key)if(!is_bool($value[$key]))throw new DomainException('company_document_layout_invalid');
        self::validateSettings(['pdf_table_heading_color'=>$value['heading_color'],'pdf_table_heading_text_color'=>$value['heading_text_color'],'pdf_logo_width'=>(string)$value['logo_width']]);return$value;
    }
    /** Real page counts added after content, without querying present-day settings. */
    public static function pageNumbers(TCPDF $pdf,array $layout,string $font):void
    {
        self::validate($layout);if(!$layout['show_page_number'])return;
        $pages=$pdf->getNumPages();$pdf->setAutoPageBreak(false);
        for($page=1;$page<=$pages;$page++){$pdf->setPage($page);$pdf->SetY(-12);$pdf->SetFont($font,'',8);$pdf->SetTextColor(110,110,110);$pdf->Cell(0,5,'Página '.$page.' / '.$pages,0,0,'C');}
    }
}
