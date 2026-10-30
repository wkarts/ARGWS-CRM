<?php

declare(strict_types=1);

/** Reject foreign invoice ownership before global gateway autoload/settings hydration. */
final class EfiLegacyCheckoutPreflight
{
    public static function allowsBootstrap($ci): bool
    {
        if (strtolower((string) $ci->router->fetch_class()) !== 'gerencia_net') { return true; }
        $module = strtolower((string) $ci->router->fetch_module());
        $directory = trim((string) $ci->router->fetch_directory(), '/');
        if (strtolower((string) $ci->router->fetch_class()) !== 'gerencia_net'
            || ($module !== 'gerencianet_gateway' && $directory !== 'gateways')) { return true; }
        // Any leftover copied controller is retired, not trusted as the modular implementation.
        if ($module !== 'gerencianet_gateway') { return false; }
        $method = strtolower((string) $ci->router->fetch_method());
        $restoreDebug = false; $debug = null;
        try {
            $ci->load->database();
            if (property_exists($ci->db, 'db_debug')) { $debug = $ci->db->db_debug; $ci->db->db_debug = false; $restoreDebug = true; } $prefix = db_prefix();
            if (!is_string($prefix) || !preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { return false; }
            $query = $ci->db->query('SELECT active,installed_version FROM ' . $prefix . 'modules WHERE module_name=?', ['gerencianet_gateway']);
            $rows = $query ? $query->result_array() : [];
            if (count($rows) !== 1 || (int) $rows[0]['active'] !== 1 || $rows[0]['installed_version'] !== '5.1.5') { return false; }
            // These endpoints still require administrator authorization in the controller.
            if (in_array($method, ['view_converter_certificado_p12_para_pem','convertercertificado','fetch_pixes'], true)) { return true; }
            if (in_array($method, ['invoice','gerar_cartao_credito','gerar_pix_por_javascript'], true)) {
                $id = $ci->uri->rsegment(3); $hash = $ci->uri->rsegment(4);
            } elseif ($method === 'callback_check_pix_pago') {
                $id = $ci->uri->rsegment(4); $hash = $ci->uri->rsegment(5);
            } elseif (in_array($method, ['callback_boleto','callback_cartao'], true)) {
                $id = $ci->input->get('invoiceid'); $hash = $ci->input->get('hash');
            } else { return false; }
            if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $id)
                || !is_string($hash) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $hash)) { return false; }
            // No invoice details, credentials or provider state selected.
            $query = $ci->db->query('SELECT id,issuer_id FROM ' . $prefix . 'invoices WHERE id=? AND hash=? AND issuer_id IS NULL LIMIT 2', [$id,$hash]);
            $rows = $query ? $query->result_array() : [];
            return count($rows) === 1 && array_key_exists('issuer_id', $rows[0]) && $rows[0]['issuer_id'] === null;
        } catch (Throwable $error) { return false; }
        finally { if ($restoreDebug) { $ci->db->db_debug = $debug; } }
    }
}
