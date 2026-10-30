<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/services/EfiChargeLedger.php';
require_once dirname(__DIR__) . '/services/EfiChargeIntent.php';
require_once dirname(__DIR__) . '/services/EfiCertificateStore.php';

use Gerencianet\Exception\GerencianetException;
use Gerencianet\Gerencianet;


class Gerencia_net extends App_Controller
{

    protected $ci;

    protected $gn_gateway;

    public function __construct()
    {

        parent::__construct();

        $CI = &get_instance();

        $this->ci = $CI;

        $this->gn_gateway = $this->gerencianet_gateway;
        $module = $this->db->where('module_name','gerencianet_gateway')->get(db_prefix().'modules')->row();
        if (!$module || (int)$module->active !== 1 || (string)$module->installed_version !== '5.1.5') { show_404(); exit; }
    }

    public function invoice($invoice_id, $invoice_hash)
    {

        $invoice = $this->authorizedInvoice($invoice_id, $invoice_hash);
        if ($this->input->method(true) === 'POST') { $this->requirePost(); }

        if ($this->input->server('REQUEST_METHOD') === 'GET') {
            $this->gerar_html($invoice);
        } elseif ($this->input->server('REQUEST_METHOD') === 'POST') {

            $tipo = [1 => 'billet', 2 => 'pix', 3 => 'credit_card'];

            $tipo_pagamento =  $this->input->post('gerencianet_pagamento_input_tipo');

            if ((!is_string($tipo_pagamento)&&!is_int($tipo_pagamento)) || !in_array((string)$tipo_pagamento,['1','2','3'],true)) {
                set_alert('warning', "Por favar, selecione um método de pagamento do EFÍ.");
                redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
            }

            $options = $this->get_gn_options();

            $invoice->post_data = true;

            switch ($tipo[$tipo_pagamento]) {
                case 'billet':
                    $this->requireKind('boleto');
                    if ($this->gn_gateway->getSetting('check_billet') == 1) {
                        if (!empty($invoice->token)) {
                            $charge = $this->fetch_payment($invoice);
                            if ($charge["code"] == 200) {
                                if ($charge["data"]["status"] == "expired") {
                                    $pay_charge = $this->create_billet($invoice);
                                    redirect($pay_charge["data"]["link"]);
                                } else {
                                    redirect($charge["data"]["payment"]["banking_billet"]["link"]);
                                }
                            }
                        } else {
                            $pay_charge = $this->create_billet($invoice);
                            redirect($pay_charge["data"]["link"]);
                        }
                    } else {
                        $pay_charge = $this->create_billet($invoice);
                        redirect($pay_charge["data"]["link"]);
                    }
                    break;
                case 'pix':
                    $this->gerar_pix_por_javascript($invoice_id,$invoice_hash);
                    break;
                default:
                    set_alert('warning', "Por favar, selecione um método de pagamento do EFÍ.");
                    redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
                    break;
            }
        }
    }

    private function fetch_payment($data = null)
    {

        if (empty($data)) {
            return;
        }

        if ($this->gn_gateway->getSetting('test_mode_enabled') == 1) {
            $clientId = $this->gn_gateway->decryptSetting('dev_client_id');
            $clientSecret = $this->gn_gateway->decryptSetting('dev_client_secret');
            $sandbox = true;
        } else {
            $clientId = $this->gn_gateway->decryptSetting('production_client_id');
            $clientSecret = $this->gn_gateway->decryptSetting('production_client_secret');
            $sandbox = false;
        }

        $options = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'sandbox' => $sandbox
        ];

        $this->ledger()->bound('boleto',(string)$data->token,$options,(array)$data);
        $gateway = $this->client($options);

        $params = [
            'id' => (int)$data->token
        ];

        $charge = $gateway->detailCharge($params, []);

        return $charge;
    }

    public function callback_check_pix_pago(string $txid, int $id_fatura, string $hash = '')
    {
        try {
            $invoice=$this->authorizedInvoice($id_fatura,$hash);
            $options=$this->get_gn_options();
            $this->ledger()->bound('pix',$txid,$options,(array)$invoice);
            $response=$this->client($options)->pixDetailCharge(['txid'=>$txid]);
            $this->recordBoundPayment('pix',$txid,$options,$invoice,$response);
            echo '1';
        } catch (Throwable $error) { $this->failure(); }
    }

    private function get_bairro(int $user_id)
    {

        $db = $this->ci->db;
        $db->where('name', 'Bairro');
        $db->where('relid', $user_id);
        $db->from(db_prefix() . 'customfields');
        $db->join(db_prefix() . 'customfieldsvalues', db_prefix() .
            'customfieldsvalues.fieldid = ' . db_prefix() . 'customfields.id');
        $query =  $db->get();
        return (string) ($query->row()->value ?? '');
    }

    private function formatar_message(bool $erro, string $message)
    {
        echo json_encode(['erro' => $erro, 'message' => $message]);
        die;
    }

    public function gerar_cartao_credito($invoice_id, $invoice_hash)
    {
        $this->requirePost();
        $this->requireKind('card');
        $this->authorizedInvoice($invoice_id, $invoice_hash);

        $options = $this->get_gn_options();

        unset($options['pix_cert']);

        $this->load->model('invoices_model');

        $this->load->model('custom_fields_model');

        $invoice = $this->invoices_model->get($invoice_id);

        $paymentToken = $this->input->post('payament_token');

        $nome_cliente = $this->input->post('nome_cliente');

        $birth = $this->input->post('birth');

        $cpf = $this->input->post('cpf');

        $email = $this->input->post('email');
        foreach ([$paymentToken,$nome_cliente,$birth,$cpf,$email] as $value) {
            if (!is_string($value) || $value === '' || strlen($value) > 2048) { $this->failure(); }
        }


        $callbackUrl = site_url('gateways/gerencia_net/callback_cartao?invoiceid=' . $invoice_id . '&hash=' . $invoice_hash);

        $invoiceNumber = format_invoice_number($invoice_id);

        $description = str_replace('{invoice_number}', $invoiceNumber, $this->gn_gateway->getSetting('description_dashboard'));

        if (mb_strlen($description, 'UTF-8') > 255) {
            $this->formatar_message(true, 'O campo descrição não pode ser superior a 255 caracteres.');
        }

        $valor_cobrado = EfiChargeLedger::minor($invoice->total);

        $item_1 = [
            'name' => $description, // nome do item, produto ou serviço
            'amount' => 1, // quantidade
            'value' => $valor_cobrado  // valor (1000 = R$ 10,00) (Obs: É possível a criação de itens com valores negativos. Porém, o valor total da fatura deve ser superior ao valor mínimo para geração de transações.)
        ];

        $items = [
            $item_1
        ];

        $metadata = ['notification_url' => $callbackUrl];



        if (str_word_count(trim($nome_cliente)) < 2) {
            $this->formatar_message(true, 'O nome do cliente deve ter no mínimo duas palavras.');
        }

        $cpf = trim(preg_replace('/\D/', '', $cpf));

        if (mb_strlen($cpf) != 11) {
            $this->formatar_message(true, 'O CPF deve ter 11 números somente.');
        }

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $birth)) {
            $this->formatar_message(true, 'A data não está em um formato correto. Formato correto: YYYY-MM-DD.');
        }

        $customer = [
            'name' => $nome_cliente, // nome do cliente
            'cpf' => $cpf, // cpf do cliente
            'phone_number' => $invoice->client->phonenumber, // telefone do cliente
            'email' => $email, // endereço de email do cliente
            'birth' => $birth // data de nascimento do cliente
        ];

        $cep =  $invoice->client->billing_zip;

        $cep  = preg_replace('/\D/', '', $cep);

        if (!$cep) {
            $this->formatar_message(true, 'Não foi possível encontrar o CEP.');
        }

        if (!$this->get_bairro($invoice->client->userid)) {
            $this->formatar_message(true, 'O bairro é obrigatório.');
        }

        if (mb_strlen($invoice->client->billing_state, 'UTF-8') != 2) {
            $this->formatar_message(true, 'A UF deve ter dois caracteres, por exemplo: BA.');
        }

        if (!mb_strlen($invoice->client->billing_street, 'UTF-8')) {
            $this->formatar_message(true, 'O nome da rua é obrigatório.');
        }

        $billingAddress = [
            'street' => $invoice->client->billing_street,
            'neighborhood' => $this->get_bairro($invoice->client->userid),
            'zipcode' => $cep,
            'number' => 0,
            'city' => $invoice->client->billing_city,
            'state' =>  $invoice->client->billing_state
        ];

        $credit_card = [
            'customer' => $customer,
            'installments' => 1, // número de parcelas em que o pagamento deve ser dividido
            'billing_address' => $billingAddress,
            'payment_token' => $paymentToken,
            'message' => 'FATURA ' . $invoiceNumber
        ];

        $payment = [
            'credit_card' => $credit_card // forma de pagamento (credit_card = cartão)
        ];

        $body = [
            'items' => $items,
            'metadata' => $metadata,
            'payment' => $payment
        ];

        $permit=null;
        try {
            $permit=$this->intents()->reserve('card',$options,(array)$invoice);
            $api = $this->client($options);
            $pay_charge = $permit['dispatch'] ? $api->createOneStepCharge([], $body)
                : $api->detailCharge(['id'=>$permit['row']['provider_id']]);


            if ($pay_charge['code'] == 200) {
                if($permit['dispatch'])$this->intents()->complete($permit,(string)$pay_charge['data']['charge_id'],$options,(array)$invoice);
                elseif((string)$pay_charge['data']['charge_id']!==$permit['row']['provider_id'])throw new DomainException('efi_charge_identity_conflict');
                $this->saveInvoiceToken($invoice_id,(string)$pay_charge['data']['charge_id']);

                $this->formatar_message(!true, 'success');
            } else {
                $this->failure();
            }
        } catch (GerencianetException $e) {
            $this->markUnknown($permit); $this->failure();
        } catch (Throwable $e) {
            $this->markUnknown($permit); $this->failure();
        }
    }

    private function gerar_html($data)
    {
        $pix                        = $this->gn_gateway->getSetting('tipo_pagamento_pix');
        $cartao                     = $this->gn_gateway->getSetting('tipo_pagamento_cartaocredito');
        $boleto                     = $this->gn_gateway->getSetting('tipo_pagamento_boleto');
        $identificador_conta        = $this->gn_gateway->decryptSetting('codigo_identificador_conta');
        $test_mode_enabled          = $this->gn_gateway->getSetting('test_mode_enabled');
        $arr = ['active', '', '', 1];
        // BOLETO
        if (($boleto && !$pix  && !$cartao) || ($boleto && $pix && !$cartao) || ($boleto && !$pix && $cartao)) {
            $arr[0] = ''; // PIX
            $arr[1] = ''; // CARTAO
            $arr[2] = 'active'; // BOLETO
            $arr[3] = 1; // INDICADOR
        }

        // PIX
        if (($pix && !$cartao && !$boleto) || ($pix && $cartao && !$boleto) || ($pix && !$cartao && $boleto)) {
            $arr[0] = 'active'; // PIX
            $arr[1] = ''; // CARTAO
            $arr[2] = ''; // BOLETO
            $arr[3] = 2; // INDICADOR
        }

        // CARTÃO
        if (($cartao && !$boleto && !$pix) || ($cartao && $boleto && !$pix) || ($cartao && !$boleto && $pix)) {
            $arr[0] = 'active'; // PIX
            $arr[1] = ''; // CARTAO
            $arr[2] = ''; // BOLETO
            $arr[3] = 3; // INDICADOR
        }

        // echo payment_gateway_head();

        $data->arr = $arr;
        $data->pix =  $pix;
        $data->cartao =  $cartao;
        $data->boleto =  $boleto;
        $data->data =  $data;
        $data->identificador_conta =  $identificador_conta;
        $data->test_mode_enabled =  $test_mode_enabled;
        $this->ci->load->view('gerencianet_gateway/gateway_page', $data);
    }

    public function view_converter_certificado_p12_para_pem()
    {
        $this->requireAdmin();
        $this->checkoutToken();
        $this->ci->load->view('gerencianet_gateway/gerencia_net');
    }

    public function converterCertificado()
    {
        $this->requireAdmin(); $this->requirePost();
        try {
            $environment=$this->input->post('nome');
            $file=$_FILES['cert_p12']??null;
            if (!in_array($environment,['producao','homologacao'],true) || !is_array($file)
                || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null)
                || !is_uploaded_file($file['tmp_name']) || (int)($file['size']??0)<1 || (int)$file['size']>EfiCertificateStore::MAX_BYTES) {
                throw new DomainException('efi_certificate_invalid');
            }
            $bytes=file_get_contents($file['tmp_name']);
            if (!is_string($bytes))throw new DomainException('efi_certificate_invalid');
            $this->load->library('encryption');
            $password=$this->input->post('certificate_password');
            if(!is_string($password))$password='';
            $cipher=EfiCertificateStore::seal($bytes,$password,$this->encryption);
            $key='paymentmethod_gerencianet_certificate_cipher_'.$environment;
            update_option($key,$cipher);
            if (get_option($key)!==$cipher)throw new RuntimeException('efi_certificate_tracking_failed');
            echo 'success';
        } catch(Throwable $error) { $this->failure(); }
    }

    public function gerar_pix_por_javascript($invoice_id, $invoice_hash)
    {
        $permit=null;
        try {

            $this->requirePost();
            $this->requireKind('pix');
            $this->authorizedInvoice($invoice_id, $invoice_hash);

            $this->load->model('invoices_model');

            $invoice = $this->invoices_model->get($invoice_id);

            $options = $this->get_gn_options();


            if (!isset($options['pix_cert']) || !is_file($options['pix_cert'])) {
                set_alert('warning', 'O arquivo do certificado não existe ou o caminho está incorreto.');
                redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
                die;
            }

            $infoAdicionais = [
                [
                    "nome" => "FATURA",
                    "valor" => format_invoice_number($invoice->id),
                ],
                [
                    "nome" => "CÓDIGO",
                    "valor" => $invoice->id,
                ]
            ];

            foreach ($invoice->items as $value) {
                $infoAdicionais[] = ['nome' => substr($value['description'], 0, 50), 'valor' => (int) $value['qty'] . 'x' . $value['rate']];
            }

            $body = [
                "calendario" => [
                    "expiracao" => 3600
                ],
                "valor" => [
                    "original" => $invoice->total
                ],
                "chave" => $this->gn_gateway->getSetting('pagamento_pix_chave'),
                "solicitacaoPagador" => "Cobrança dos serviços prestados.",
                "infoAdicionais" => $infoAdicionais
            ];

            $permit=$this->intents()->reserve('pix',$options,(array)$invoice);
            $api = $this->client($options);
            $pix = $permit['dispatch'] ? $api->pixCreateImmediateCharge([], $body)
                : $api->pixDetailCharge(['txid'=>$permit['row']['provider_id']]);

            if ($pix['txid']) {
                if(EfiChargeLedger::minor($pix['valor']['original']??null)!==EfiChargeLedger::minor($invoice->total))throw new DomainException('efi_charge_amount_conflict');
                if($permit['dispatch'])$this->intents()->complete($permit,(string)$pix['txid'],$options,(array)$invoice);
                elseif((string)$pix['txid']!==$permit['row']['provider_id'])throw new DomainException('efi_charge_identity_conflict');

                $params = [
                    'id' => $pix['loc']['id']
                ];

                $qrcode = $api->pixGenerateQRCode($params);

                $qrcode['txid'] = $pix['txid'];

                echo json_encode($qrcode);
            }
        } catch (GerencianetException $e) {
            $this->markUnknown($permit); $this->failure();


        } catch (Throwable $e) {
            $this->markUnknown($permit); $this->failure();
        }
    }

    private function create_billet($data = null)
    {
        if (empty($data)) {
            return;
        }

        $gerencia_net = $this->gn_gateway;

        if ($gerencia_net->getSetting('test_mode_enabled') == 1) {
            $clientId = $gerencia_net->decryptSetting('dev_client_id');
            $clientSecret = $gerencia_net->decryptSetting('dev_client_secret');
            $sandbox = true;
        } else {
            $clientId = $gerencia_net->decryptSetting('production_client_id');
            $clientSecret = $gerencia_net->decryptSetting('production_client_secret');
            $sandbox = false;
        }

        $options = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'sandbox' => $sandbox
        ];

        $invoiceNumber = format_invoice_number($data->id);

        $description = str_replace('{invoice_number}', $invoiceNumber, $gerencia_net->getSetting('description_dashboard'));

        $callbackUrl = site_url('gateways/gerencia_net/callback_boleto?invoiceid=' . $data->id . '&hash=' . $data->hash);

        $valor_cobrado = EfiChargeLedger::minor($data->total);

        if ($valor_cobrado < 5) {
            set_alert('warning', 'O valor não pode ser inferior a R$ 5,00');
            redirect(site_url('invoice/' . $data->id . '/' . $data->hash));
        }

        $item_1 = [
            'name' =>  $description,
            'amount' => 1,
            'value' => $valor_cobrado
        ];

        $items =  [
            $item_1
        ];

        $metadata =  [
            'custom_id' => $data->id,
            'notification_url' =>  $callbackUrl
        ];

        $body  =  [
            'items' => $items,
            'metadata' => $metadata
        ];

        $permit=null;
        try {
            $permit=$this->intents()->reserve('boleto',$options,(array)$data);
            $gateway = $this->client($options);
            if(!$permit['dispatch']) {
                $charge=$gateway->detailCharge(['id'=>$permit['row']['provider_id']]);
                if(($charge['code']??null)!==200||(string)($charge['data']['charge_id']??'')!==$permit['row']['provider_id'])throw new DomainException('efi_charge_identity_conflict');
                return ['code'=>200,'data'=>['charge_id'=>$permit['row']['provider_id'],'link'=>$charge['data']['payment']['banking_billet']['link']]];
            }
            $charge = $gateway->createCharge([], $body);

            if ($charge["code"] == 200) {
                $this->intents()->providerCreated($permit,(string)$charge['data']['charge_id']);
                $params = ['id' => $charge["data"]["charge_id"]];

                $vat = $data->client->vat;

                $vat = preg_replace("/[^0-9]/", "", $vat);

                $phone_number = $data->client->phonenumber;

                $phone_number = preg_replace("/[^0-9]/", "", $phone_number);

                if (strlen($vat) == 11) {
                    $customer = [
                        'name' => $data->client->company,
                        'cpf' => $vat,
                        'phone_number' => $phone_number
                    ];
                } elseif (strlen($vat) == 14) {
                    $customer = [
                        'phone_number' => $phone_number,
                        'juridical_person' => [
                            'corporate_name' => $data->client->company,
                            'cnpj' => $vat
                        ]
                    ];
                }

                if (strtotime(date("Y-m-d")) > strtotime($data->duedate)) {
                    $expire_at = date('Y-m-d', strtotime("+1 days", strtotime(date("Y-m-d"))));
                } else {
                    $expire_at = $data->duedate;
                }

                $bankingBillet = [
                    'expire_at' => $expire_at,
                    'customer' => $customer
                ];

                $payment = ['banking_billet' => $bankingBillet];

                $body = ['payment' => $payment];

                $gateway = $this->client($options);

                $this->intents()->boletoPayment($permit,(string)$charge['data']['charge_id']);
                $pay_charge = $gateway->definePayMethod($params, $body);

                if ($pay_charge["code"] == 200) {
                    if((string)$pay_charge['data']['charge_id']!==(string)$charge['data']['charge_id'])throw new DomainException('efi_charge_identity_conflict');
                    $this->intents()->complete($permit,(string)$pay_charge['data']['charge_id'],$options,(array)$data);
                    $this->saveInvoiceToken($data->id,(string)$pay_charge['data']['charge_id']);

                    return $pay_charge;
                } else {
                    return $charge;
                }
            } else {
                return $charge;
            }
        } catch (GerencianetException $e) {
            $this->markUnknown($permit); $this->failure();


        } catch (Throwable $e) {
            $this->markUnknown($permit); $this->failure();
        }
    }

    public function callback_boleto() { $this->chargeCallback('boleto'); }
    public function callback_cartao() { $this->chargeCallback('card'); }

    private function chargeCallback(string $kind): void
    {
        try {
            $invoice=$this->authorizedInvoice($this->input->get('invoiceid'),(string)$this->input->get('hash'));
            $options=$this->get_gn_options(); unset($options['pix_cert']);
            $provider=(string)$invoice->token;
            $this->ledger()->bound($kind,$provider,$options,(array)$invoice);
            $response=$this->client($options)->detailCharge(['id'=>$provider],[]);
            $this->recordBoundPayment($kind,$provider,$options,$invoice,$response);
            echo '1';
        } catch(Throwable $error) { $this->failure(); }
    }

    private function recordBoundPayment(string $kind,string $provider,array $options,$invoice,array $response): int
    {
        $this->load->model('payments_model');
        return $this->ledger()->settle($kind,$provider,$options,(array)$invoice,$response,
            function(array $data) {
                // The native model offers extension hooks. Veto substitutions under its
                // canonical invoice row lock, before INSERT, not merely after a bad commit.
                $guard=static function($allowed,array $locked,array $record)use($data):bool {
                    if($allowed!==true||($locked['issuer_id']??null)!==null||(int)$locked['id']!==(int)$data['invoiceid'])return false;
                    foreach(['invoiceid','paymentmode','paymentmethod','transactionid'] as $key) {
                        if((string)($record[$key]??'')!==(string)$data[$key])return false;
                    }
                    try{return EfiChargeLedger::minor($record['amount']??null)===EfiChargeLedger::minor($data['amount']);}
                    catch(Throwable $error){return false;}
                };
                hooks()->add_filter('before_invoice_payment_insert_allowed',$guard,PHP_INT_MAX,3);
                try{return $this->payments_model->add($data);}
                finally{hooks()->remove_filter('before_invoice_payment_insert_allowed',$guard,PHP_INT_MAX);}
            });
    }

    private array $temporaryCertificates = [];

    private function get_gn_options(): array
    {
        $sandbox=(int)$this->gn_gateway->getSetting('test_mode_enabled')===1;
        $environment=$sandbox?'homologacao':'producao';
        $options=['client_id'=>$this->gn_gateway->decryptSetting($sandbox?'dev_client_id':'production_client_id'),
            'client_secret'=>$this->gn_gateway->decryptSetting($sandbox?'dev_client_secret':'production_client_secret'),
            'sandbox'=>$sandbox,'debug'=>false,'timeout'=>30];
        $cipher=get_option('paymentmethod_gerencianet_certificate_cipher_'.$environment);
        if (is_string($cipher)&&$cipher!=='') {
            $this->load->library('encryption');
            $path=EfiCertificateStore::temporary($cipher,$this->encryption);
            $this->temporaryCertificates[]=$path;
            register_shutdown_function(static function()use($path):void{if(is_file($path))@unlink($path);});
            $options['pix_cert']=$path;
        } else {
            // Preserve configured external private files; never fall back to web-accessible media.
            $configured=$this->gn_gateway->getSetting('tipo_pagamento_pix_certificado_pem_'.$environment);
            $path=is_string($configured)&&$configured!==''?realpath($configured):false;
            $web=realpath(FCPATH);
            if($path!==false&&$web!==false&&!str_starts_with($path,$web.DIRECTORY_SEPARATOR)&&$path!==$web
                &&is_file($path)&&is_readable($path)&&((fileperms($path)&0077)===0))$options['pix_cert']=$path;
        }
        return $options;
    }

    public function fetch_pixes()
    {
        $this->requireAdmin(); $this->requirePost();
        try {
            $options=$this->get_gn_options(); $completed=0;
            // Query only locally bound charges. Never trust provider infoAdicionais as invoice IDs.
            foreach($this->ledger()->pendingPix($options) as $row) {
                $invoice=$this->authorizedInvoice($row['id'],$row['hash']);
                $response=$this->client($options)->pixDetailCharge(['txid'=>$row['provider_id']]);
                if(($response['status']??null)!=='CONCLUIDA')continue;
                $this->recordBoundPayment('pix',$row['provider_id'],$options,$invoice,$response); ++$completed;
            }
            echo json_encode(['confirmed'=>$completed]);
        } catch(Throwable $error) { $this->failure(); }
    }

    private function saveInvoiceToken($invoice,string $provider): void
    {
        $result=$this->db->query('UPDATE '.db_prefix().'invoices SET token=? WHERE id=? AND issuer_id IS NULL',[$provider,$invoice]);
        $check=$this->db->query('SELECT token FROM '.db_prefix().'invoices WHERE id=? AND issuer_id IS NULL',[$invoice]);
        if($result===false||!$check||(string)($check->row()->token??'')!==$provider)throw new RuntimeException('efi_invoice_tracking_unconfirmed');
    }

    private function intents(): EfiChargeIntent { return new EfiChargeIntent($this->db,db_prefix()); }

    private function markUnknown(?array $permit): void
    {
        if(!$permit||($permit['dispatch']??false)!==true)return;
        try{$this->intents()->uncertain($permit);}catch(Throwable $error){log_message('error','Efí intent update unconfirmed; existing durable dispatch fence retained.');}
    }

    private function ledger(): EfiChargeLedger { return new EfiChargeLedger($this->db,db_prefix()); }

    private function authorizedInvoice($id,string $hash)
    {
        $this->ledger()->invoice($id,$hash);
        check_invoice_restrictions($id,$hash);
        $this->load->model('invoices_model');
        $invoice=$this->invoices_model->get($id);
        if(!$invoice||!array_key_exists('issuer_id',(array)$invoice)||$invoice->issuer_id!==null
            ||!hash_equals((string)$invoice->hash,$hash)||(int)$this->gn_gateway->getSetting('active')!==1
            ||!in_array('gerencianet',\app\services\PaymentGatewayCatalog::decodeModes($invoice->allowed_payment_modes),true)) {
            show_404(); throw new DomainException('efi_invoice_denied');
        }
        $this->checkoutToken();
        return $invoice;
    }

    private function checkoutToken(): string
    {
        $token=$this->session->userdata('efi_checkout_csrf');
        if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)) {
            $token=bin2hex(random_bytes(32)); $this->session->set_userdata('efi_checkout_csrf',$token);
        }
        return $token;
    }

    private function requirePost(): void
    {
        $expected=$this->session->userdata('efi_checkout_csrf'); $actual=$this->input->post('efi_checkout_csrf');
        if($this->input->method(true)!=='POST'||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)
            ||!is_string($actual)||!hash_equals($expected,$actual)) { show_error('Solicitação inválida.',403); throw new DomainException('efi_csrf_denied'); }
    }

    private function requireKind(string $kind): void
    {
        $keys=['pix'=>'tipo_pagamento_pix','boleto'=>'tipo_pagamento_boleto','card'=>'tipo_pagamento_cartaocredito'];
        if(!isset($keys[$kind])||(int)$this->gn_gateway->getSetting($keys[$kind])!==1) { show_error('Forma de pagamento indisponível.',403); throw new DomainException('efi_payment_kind_disabled'); }
    }

    private function requireAdmin(): void
    {
        if(!is_staff_logged_in()||!is_admin()) { show_error('Acesso negado.',403); throw new DomainException('efi_admin_denied'); }
    }

    protected function client(array $options)
    {
        require_once dirname(__DIR__) . '/services/EfiSdkClient.php';
        return new EfiSdkClient($options);
    }

    private function failure(): void
    {
        show_error('Operação Efí não confirmada. Cobranças sem vínculo local de conta, ambiente e fatura exigem revisão manual. Nenhum vínculo legado é inferido automaticamente.',409);
        throw new RuntimeException('efi_operation_unconfirmed');
    }
}
