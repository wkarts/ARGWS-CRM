<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/services/IssuerSchema.php';
$CI = &get_instance();
foreach (IssuerSchema::statements(db_prefix()) as $statement) {
    if ($CI->db->query($statement) === false) {
        throw new RuntimeException('issuer_install_failed');
    }
}
