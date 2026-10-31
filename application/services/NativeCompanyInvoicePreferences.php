<?php
declare(strict_types=1);
/** Wire values of the existing native invoice settings form; defaults are public installation constants, not principal options. */
final class NativeCompanyInvoicePreferences
{
    private const DEFAULTS=['invoice_prefix'=>'INV-','next_invoice_number'=>'1','invoice_due_after'=>'30','allow_staff_view_invoices_assigned'=>'1','view_invoice_only_logged_in'=>'0','delete_only_on_last_invoice'=>'1','invoice_number_decrement_on_delete'=>'1','exclude_invoice_from_client_area_with_draft_status'=>'1','show_sale_agent_on_invoices'=>'1','show_project_on_invoice'=>'1','show_total_paid_on_invoice'=>'1','show_credits_applied_on_invoice'=>'1','show_amount_due_on_invoice'=>'1','attach_invoice_to_payment_receipt_email'=>'0','invoice_number_format'=>'1','predefined_clientnote_invoice'=>'','predefined_terms_invoice'=>''];
    public static function fields():array{return array_keys(self::DEFAULTS);}
    public static function defaults():array{return self::DEFAULTS;}
    public static function validate(array $values,bool $counter=true):void
    {
        $fields=$counter?self::fields():array_values(array_diff(self::fields(),['next_invoice_number']));
        if(array_diff(array_keys($values),$fields)||array_diff($fields,array_keys($values)))throw new DomainException('native_settings_invalid');
        foreach($values as$key=>$value){
            if(!is_string($value)||strlen($value)>10000||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_settings_invalid');
            if(in_array($key,['predefined_clientnote_invoice','predefined_terms_invoice'],true))continue;
            if($key==='invoice_prefix'){if(strlen($value)>50||preg_match('/[\r\n]/',$value))throw new DomainException('native_settings_invalid');continue;}
            if($key==='next_invoice_number'){if(!preg_match('/^[1-9][0-9]{0,19}$/D',$value))throw new DomainException('native_settings_invalid');continue;}
            if($key==='invoice_due_after'){if(!preg_match('/^(?:0|[1-9][0-9]{0,3})$/D',$value))throw new DomainException('native_settings_invalid');continue;}
            if($key==='invoice_number_format'){if(!in_array($value,['1','2','3','4'],true))throw new DomainException('native_settings_invalid');continue;}
            if(!in_array($value,['0','1'],true))throw new DomainException('native_settings_invalid');
        }
    }
}
