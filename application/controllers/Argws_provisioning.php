<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Argws_provisioning extends CI_Controller
{
    public function apply_migrations()
    {
        $bridgeToken = getenv('ARGWS_SETUP_MIGRATION_TOKEN');
        if (!is_cli()) {
            show_404();
            return;
        }

        if (!is_string($bridgeToken) || preg_match('/\A[a-f0-9]{64}\z/i', $bridgeToken) !== 1) {
            fwrite(STDERR, "Token interno de migrations ausente ou inválido." . PHP_EOL);
            exit(78);
        }

        $result = $this->app->apply_pending_migrations_for_provisioning();
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        if (empty($result['success'])) {
            exit(1);
        }
    }
}
