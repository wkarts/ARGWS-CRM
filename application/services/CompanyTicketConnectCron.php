<?php

declare(strict_types=1);
/** No hidden enable constant: absent/disabled own policies have no delivery effect. Endpoint registry is existing server infrastructure. */
final class CompanyTicketConnectCron
{
    public static function run($ci):void
    {
        try{require_once __DIR__.'/CompanyTicketConnectWorker.php';$ci->load->library('encryption');(new CompanyTicketConnectWorker($ci->db,db_prefix()))->run(fn($v)=>$ci->encryption->encrypt($v),fn($v)=>$ci->encryption->decrypt($v),20);}catch(Throwable $e){log_message('error','Own ticket Connect queue unavailable; external delivery is not confirmed.');}
    }
}
