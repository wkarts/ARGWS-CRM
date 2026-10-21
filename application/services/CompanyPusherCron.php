<?php

declare(strict_types=1);
/** Trusted server opt-in; profiles and source actors are still revalidated per durable intent. */
final class CompanyPusherCron
{
    public static function run($ci):void
    {
        if(!defined('COMPANY_PUSHER_WORKER_ENABLED')||COMPANY_PUSHER_WORKER_ENABLED!==true)return;
        try{require_once __DIR__.'/CompanyPusherTaskNotifications.php';$ci->load->library('encryption');(new CompanyPusherTaskNotifications($ci->db,db_prefix()))->work(fn($v)=>$ci->encryption->decrypt($v),20);}catch(Throwable $e){log_message('error','Own Pusher worker unavailable; websocket delivery not confirmed.');}
    }
}
