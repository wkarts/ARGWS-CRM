<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyReconciliation.php';

/** Hook adapter only; no gateway, current staff identity or current company is consulted. */
final class CompanyCron
{
    public static function observePix():void
    {
        try{require_once __DIR__.'/CompanyPixInbox.php';$ci=&get_instance();$ci->load->library('encryption');(new CompanyPixInbox($ci->db,db_prefix()))->tick(fn($value)=>$ci->encryption->decrypt($value));}
        catch(\Throwable $error){if(function_exists('log_message'))log_message('error','[Asaas] Observação Pix não confirmada; confira a fila da empresa.');}
    }
    public static function run():void
    {
        try{$ci=&get_instance();$ci->load->library('encryption');(new CompanyReconciliation($ci->db,db_prefix()))->runOnce(fn($value)=>$ci->encryption->decrypt($value));}
        catch(\Throwable $error){if(function_exists('log_message'))log_message('error','[Asaas] Conciliação por empresa não confirmada; consulte os estados dos jobs.');}
    }
}
