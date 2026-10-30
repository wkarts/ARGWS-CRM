<?php
declare(strict_types=1);
/** Pure existing native status presentation; no model construction, hooks, authorization or SQL. */
final class InvoiceStatusPresentation
{
    public const CANCELLED=5;
    public static function labelKey($status):string
    {
        if($status==1)return 'invoice_status_unpaid';
        if($status==2)return 'invoice_status_paid';
        if($status==3)return 'invoice_status_not_paid_completely';
        if($status==4)return 'invoice_status_overdue';
        if($status==5)return 'invoice_status_cancelled';
        return 'invoice_status_draft';
    }
    public static function css($status):string
    {
        if($status==1)return 'danger';
        if($status==2)return 'success';
        if($status==3||$status==4)return 'warning';
        if($status==5||$status==6||(!is_numeric($status)&&$status==='not_sent'))return 'default';
        return '';
    }
}
