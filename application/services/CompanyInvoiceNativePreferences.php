<?php
declare(strict_types=1);
/** Explicit creation preferences, separate from editable presentation columns and financial values. */
final class CompanyInvoiceNativePreferences
{
 public static function input($value):array
 {
  if(!is_array($value)||array_keys($value)!==['cancel_overdue_reminders']||!in_array($value['cancel_overdue_reminders'],[0,1,'0','1'],true))throw new DomainException('company_invoice_native_preferences_invalid');
  return ['cancel_overdue_reminders'=>(int)$value['cancel_overdue_reminders']];
 }
}
