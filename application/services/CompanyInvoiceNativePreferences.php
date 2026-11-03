<?php
declare(strict_types=1);
/** Explicit creation preferences, separate from editable presentation columns and financial values. */
final class CompanyInvoiceNativePreferences
{
 public static function input($value):array
 {
  if(!is_array($value)||array_diff(array_keys($value),['cancel_overdue_reminders','allowed_payment_modes'])||!array_key_exists('cancel_overdue_reminders',$value)||!in_array($value['cancel_overdue_reminders'],[0,1,'0','1'],true))throw new DomainException('company_invoice_native_preferences_invalid');
  $out=['cancel_overdue_reminders'=>(int)$value['cancel_overdue_reminders']];if(array_key_exists('allowed_payment_modes',$value)){require_once __DIR__.'/NativeCompanyInvoicePaymentChoices.php';$out['allowed_payment_modes']=NativeCompanyInvoicePaymentChoices::selection($value['allowed_payment_modes']);}return$out;
 }
}
