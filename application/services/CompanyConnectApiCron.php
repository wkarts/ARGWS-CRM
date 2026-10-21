<?php

declare(strict_types=1);

/** Trusted server opt-in only; no session/company selection or global SMS provider initialization. */
final class CompanyConnectApiCron
{
    public static function run($ci):void
    {
        if(!defined('COMPANY_CONNECT_API_WORKER_ENABLED')||COMPANY_CONNECT_API_WORKER_ENABLED!==true)return;
        try{require_once __DIR__.'/CompanyConnectApiWorker.php';$ci->load->library('encryption');(new CompanyConnectApiWorker($ci->db,db_prefix()))->run(fn($v)=>$ci->encryption->decrypt($v),20);}catch(Throwable$e){log_message('error','Own Connect API worker unavailable; pending external delivery was not confirmed.');}
    }
}
