<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Checkout extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('asaas_gateway');
        $this->load->helper('general');
    }

    public function index($hash)
    {

        $this->db->where('hash', $hash);

        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        $data = [
            'billet_only' => $this->asaas_gateway->getSetting('billet_only'),
            'card_only'   => $this->asaas_gateway->getSetting('card_only'),
            'pix_only'    => $this->asaas_gateway->getSetting('pix_only'),
            'environment' => $this->asaas_gateway->getEnvironmentLabel(),
            'invoice'     => $invoice,
            'hash'        => $hash,
            'title'       => 'Asaas'
        ];
        // $this->disableNavigation();
        // $this->disableSubMenu();
        $this->data($data);
        $this->view('asaas/payment');
        $this->layout();
    }

    public function boleto($hash)
    {
        $this->db->where('hash', $hash);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        $data = [];
        $data['invoice'] = $invoice;

        $charge = $this->asaas_gateway->get_charge($hash);

        if ($charge) {
            redirect($charge->bankSlipUrl);
        } else {
            // redirect(site_url('checkout/error/' . $invoice->hash), 'refresh');
            $this->session->set_flashdata('error', 'Não foi possível gerar a cobrança, Fale com o financeiro');

            set_alert('warning', 'Não foi possível gerar a cobrança, Fale com o financeiro');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash), 'refresh');
        }
    }

    public function cartao($hash)
    {
        require_once __DIR__ . '/../libraries/Asaas/HostedCardCheckout.php';
        if (!is_string($hash) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $hash)) {
            show_404();
            return;
        }
        $this->db->where('hash', $hash);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();
        if (!$invoice) { show_404(); return; }
        check_invoice_restrictions($invoice->id, $hash);
        if (!in_array((int) $invoice->status, [1, 4], true)
            || $this->asaas_gateway->getSetting('active') !== '1'
            || $this->asaas_gateway->getSetting('card_only') !== '1'
            || !\Asaas\HostedCardCheckout::allowsInvoice($invoice->allowed_payment_modes ?? null)) {
            show_error('Pagamento por cartão indisponível para esta fatura.', 409);
            return;
        }
        $csrfKey = 'asaas_hosted_card_csrf_' . (int) $invoice->id;
        $csrf = $this->session->userdata($csrfKey);
        if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
            $csrf = bin2hex(random_bytes(32));
            $this->session->set_userdata($csrfKey, $csrf);
        }
        $error = '';
        if ($this->input->method(true) === 'POST') {
            $submitted = $this->input->post('hosted_card_csrf');
            if (!is_string($submitted) || !hash_equals($csrf, $submitted)) {
                show_error('Sessão inválida. Atualize a página e tente novamente.', 403);
                return;
            }
            // Old raw-card forms are rejected without retaining, echoing or logging their values.
            foreach (['holderName', 'cardNumber', 'expirationMonth', 'expirationYear', 'securityCode', 'card', 'creditCard', 'creditCardToken', 'creditCardHolderInfo'] as $field) {
                if ($this->input->post($field) !== null) {
                    show_error('Dados de cartão devem ser informados somente na página segura do Asaas.', 400);
                    return;
                }
            }
            try {
                \Asaas\HostedCardCheckout::installments($this->input->post('installmentCount'));
                $result = json_decode($this->asaas_gateway->charge_credit_card(['invoice' => $invoice, 'installmentCount' => 1]), true);
                if (!is_array($result) || isset($result['errors']) || empty($result['invoiceUrl'])) {
                    throw new DomainException('hosted_checkout_unavailable');
                }
                // The origin is immutable; never validate a saved link against a newly selected environment.
                $origin = $this->asaas_gateway->getAdapter()->originStore()->byInvoice((int) $invoice->id);
                if (!$origin || ($origin['state'] ?? '') !== 'issued' || ($origin['remote_id'] ?? '') !== ($result['id'] ?? null)) {
                    throw new DomainException('origin_review_required');
                }
                $result['externalReference'] = $invoice->hash;
                $url = \Asaas\HostedCardCheckout::verifiedUrl($result, (string) $origin['environment'], $invoice->hash);
                redirect($url);
                return;
            } catch (Throwable $failure) {
                $error = 'Não foi possível abrir o pagamento por cartão. Se já existe boleto ou outra cobrança, solicite ao financeiro a revisão da origem antes de trocar a forma de pagamento.';
            }
        } elseif ($this->input->method(true) !== 'GET') {
            show_404(); return;
        }
        $this->data(['invoice' => $invoice, 'hash' => $hash, 'csrf_token' => $csrf, 'mensagem_erro' => $error, 'title' => 'Cartão de crédito via Asaas']);
        $this->view('asaas/cartao');
        $this->layout();
    }

    public function qrcode($hash)
    {
        $this->db->where('hash', $hash);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        if (!$invoice) {
            set_alert('warning', 'Fatura não encontrada.');
            redirect(site_url('invoices'));
        }

        $invoice->total_left_to_pay = get_invoice_total_left_to_pay($invoice->id, $invoice->total);

        $data = [];
        $data['invoice'] = $invoice;

        $billet = $this->asaas_gateway->get_charge($hash);

        // Verifica se a cobrança foi retornada
        if ($billet && isset($billet->id)) {
            $qrcode = $this->asaas_gateway->create_qrcode($billet->id);

            $data = [
                'invoice' => $invoice,
                'response' => json_decode($qrcode),
                'environment' => $this->asaas_gateway->getEnvironmentLabel(),
            ];
        } else {
            set_alert('warning', 'Não foi possível gerar o QR code. Verifique a cobrança.');
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash), 'refresh');
        }

        //$this->disableNavigation();
        //$this->disableSubMenu();
        $this->title('Asaas');
        $this->data($data);
        $this->view('asaas/qrcode');
        $this->layout();
    }


    public function error($hash)
    {
        $this->title('Asaas');
        $this->disableNavigation();
        $this->disableSubMenu();
        $this->view('asaas/error');
        $this->layout();
    }
}
