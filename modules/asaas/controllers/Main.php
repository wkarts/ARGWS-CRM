<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Main extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('asaas_gateway');
        $this->load->helper('general');
    }

    public function index()
    {
        // Esta rota antiga criava chaves Pix durante um simples GET.
        show_404();
    }

    public function create_key($api_url, $api_key, $post_data)
    {
        $this->requireAdministrativeRequest(true);
        $payload = json_decode((string) $post_data, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('POST', 'pix/addressKeys', [], [], $payload);

        return json_encode(is_array($response) ? $response : []);
    }

    public function list_keys($api_url, $api_key)
    {
        $this->requireAdministrativeRequest();
        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('GET', 'pix/addressKeys', [], [], null);

        return json_decode(json_encode(is_array($response) ? $response : []));
    }

    public function get_key($api_url, $api_key, $id)
    {
        $this->requireAdministrativeRequest();
        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('GET', 'pix/addressKeys/' . $id, [], [], null);

        return json_decode(json_encode(is_array($response) ? $response : []));
    }

    public function delete_key($api_url, $api_key, $id)
    {
        $this->requireAdministrativeRequest(true);
        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('DELETE', 'pix/addressKeys/' . $id, [], [], null);

        return json_decode(json_encode(is_array($response) ? $response : []));
    }

    public function retorna_cobranca($id = 178458832)
    {
        $this->requireAdministrativeRequest();
        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('GET', 'payments/' . $id, [], [], null);

        echo json_encode(is_array($response) ? $response : []);
    }

    public function retorna_cobrancas($hash = '')
    {
        $this->requireAdministrativeRequest();
        $client = $this->asaas_gateway->getProvider()->client();
        $response = $client->request('GET', 'payments', ['externalReference' => $hash], [], null);

        echo json_encode(is_array($response) ? $response : []);
    }
    private function requireAdministrativeRequest(bool $write = false): void
    {
        if (!is_staff_logged_in() || !is_admin()) {
            show_404();
        }
        if ($write && $this->input->method(true) !== 'POST') {
            show_404();
        }
    }


}
