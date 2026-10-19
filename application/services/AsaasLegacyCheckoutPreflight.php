<?php

declare(strict_types=1);

/** Ownership veto for the exact legacy HMVC controller, before application/provider autoload. */
final class AsaasLegacyCheckoutPreflight
{
    public static function allowsBootstrap($ci):bool
    {
        if(strtolower((string)$ci->router->fetch_module())!=='asaas'
            ||trim((string)$ci->router->fetch_directory(),'/')!=='../../modules/asaas/controllers'
            ||strtolower((string)$ci->router->fetch_class())!=='checkout'
            ||!in_array(strtolower((string)$ci->router->fetch_method()),['index','boleto','cartao','qrcode'],true))return true;
        if(!isset($ci->uri)||!method_exists($ci->uri,'rsegment'))return false;
        // This is the resolved HMVC argument, not an attacker-selected raw URL/query/header.
        $hash=$ci->uri->rsegment(3);
        if(!is_string($hash)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$hash))return false;
        try{
            $ci->load->database();$prefix=db_prefix();if(!is_string($prefix)||!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))return false;
            $debug=$ci->db->db_debug;$ci->db->db_debug=false;
            try{
                // No document, settings, totals, currency, client or provider information is selected.
                $query=$ci->db->query('SELECT id,issuer_id FROM '.$prefix.'invoices WHERE hash=? LIMIT 2',[$hash]);
                if($query===false)return false;$rows=$query->result_array();
            }finally{$ci->db->db_debug=$debug;}
            return count($rows)===1&&array_key_exists('issuer_id',$rows[0])&&$rows[0]['issuer_id']===null;
        }catch(\Throwable $error){return false;}
        // This grants no invoice access: the controller still queries IS NULL and checks native restrictions.
    }
}
