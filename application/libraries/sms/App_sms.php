<?php

use GuzzleHttp\Client;

defined('BASEPATH') or exit('No direct script access allowed');

define('SMS_TRIGGER_INVOICE_OVERDUE', 'invoice_overdue_notice');
define('SMS_TRIGGER_INVOICE_DUE', 'invoice_due_notice');
define('SMS_TRIGGER_PAYMENT_RECORDED', 'invoice_payment_recorded');
define('SMS_TRIGGER_ESTIMATE_EXP_REMINDER', 'estimate_expiration_reminder');
define('SMS_TRIGGER_PROPOSAL_EXP_REMINDER', 'proposal_expiration_reminder');
define('SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_CUSTOMER', 'proposal_new_comment_to_customer');
define('SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_STAFF', 'proposal_new_comment_to_staff');
define('SMS_TRIGGER_CONTRACT_EXP_REMINDER', 'contract_expiration_reminder');
define('SMS_TRIGGER_CONTRACT_SIGN_REMINDER', 'contract_sign_reminder_to_customer');
define('SMS_TRIGGER_STAFF_REMINDER', 'staff_reminder');

define('SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_STAFF', 'contract_new_comment_to_staff');
define('SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_CUSTOMER', 'contract_new_comment_to_customer');

class App_sms
{
    private static $gateways = [];

    protected $client;

    private $triggers = [];

    protected $ci;

    public static $trigger_being_sent;

    public $test_mode = false;

    public function __construct()
    {
        $this->ci = &get_instance();

        $this->client = new Client(
            [ 'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'verify'               => true,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
        $this->set_default_triggers();
    }

    public function add_gateway($id, $data = [])
    {
        // IDs antigos não são excluídos do banco, mas não podem enviar mensagens.
        if ($id !== 'connect_api_connector') {
            return;
        }
        if (!$this->is_initialized($id) || $this->is_options_page()) {
            foreach ($data['options'] as $option) {
                add_option($this->option_name($id, $option['name']), (isset($option['default_value']) ? $option['default_value'] : ''));
            }

            add_option($this->option_name($id, 'active'), 0);
            add_option($this->option_name($id, 'initialized'), 1);
        }

        $data['id'] = $id;

        self::$gateways[$id] = $data;
    }

    public function get_option($id, $option)
    {
        return get_option($this->option_name($id, $option));
    }

    public function get_gateway($id)
    {
        $gateway = isset(self::$gateways[$id]) ? self::$gateways[$id] : null;

        return $gateway;
    }

    public function set_test_mode($value)
    {
        $this->test_mode = $value;

        return $this;
    }

    public function get_gateways()
    {
        $gateways = hooks()->apply_filters('get_sms_gateways', self::$gateways);
        return array_filter(is_array($gateways) ? $gateways : [], static function ($gateway) {
            return is_array($gateway) && ($gateway['id'] ?? '') === 'connect_api_connector';
        });
    }

    public function get_trigger_value($trigger)
    {
        $oc_name = 'sms-trigger-' . $trigger . '-value';
        $message = $this->ci->app_object_cache->get($oc_name);
        if (!$message) {
            $message = get_option($this->trigger_option_name($trigger));
            $this->ci->app_object_cache->add($oc_name, $message);
        }

        return $message;
    }

    public function add_trigger($trigger)
    {
        $this->triggers = array_merge($this->triggers, $trigger);
    }

    public function get_available_triggers()
    {
        $triggers = hooks()->apply_filters('sms_gateway_available_triggers', $this->triggers);

        foreach ($triggers as $trigger_id => $definition) {
            if ($this->is_options_page()) {
                // add_option não substitui textos vazios nem modelos editados.
                add_option($this->trigger_option_name($trigger_id),
                    (string) ($definition['default_message'] ?? ''), 0);
            }
            $triggers[$trigger_id]['value'] = $this->get_trigger_value($trigger_id);
            $triggers[$trigger_id]['enabled'] = $this->is_trigger_enabled($trigger_id, $definition);
        }

        return $triggers;
    }

    public function trigger($trigger, $phone, $merge_fields = [])
    {
        if (empty($phone)) {
            return false;
        }

        $gateway = $this->get_active_gateway();

        if ($gateway !== false) {
            $className = 'sms_' . $gateway['id'];
            if ($this->is_trigger_active($trigger)) {
                $message = $this->parse_merge_fields(
                    $merge_fields,
                    $this->get_trigger_value($trigger)
                );

                $message = clear_textarea_breaks($message);

                static::$trigger_being_sent = $trigger;

                $retval = $this->ci->{$className}->send($phone, $message, $trigger);

                hooks()->do_action('sms_trigger_triggered', ['message' => $message, 'trigger' => $trigger, 'phone' => $phone]);

                static::$trigger_being_sent = null;

                return $retval;
            }
        }

        return false;
    }

    /**
     * Parse sms gateway merge fields
     * We will use the email templates merge fields function because they are the same
     * @param  array $merge_fields merge fields
     * @param  string $message      the message to bind the merge fields
     * @return string
     */
    public function parse_merge_fields($merge_fields, $message)
    {
        $template           = new stdClass();
        $template->message  = $message;
        $template->subject  = '';
        $template->fromname = '';

        return parse_email_template_merge_fields($template, $merge_fields)->message;
    }

    public function option_name($id, $option)
    {
        return 'sms_' . $id . '_' . $option;
    }

    public function trigger_option_name($trigger)
    {
        return 'sms_trigger_' . $trigger;
    }

    public function trigger_enabled_option_name($trigger)
    {
        return $this->trigger_option_name($trigger) . '_enabled';
    }

    /**
     * Compatibilidade: gatilhos antigos com mensagem configurada continuam
     * ativos até que o administrador escolha expressamente Sim ou Não.
     * Novos eventos são instalados desativados para não produzir disparos
     * inesperados durante atualizações.
     */
    public function is_trigger_enabled($trigger, $definition = null)
    {
        $stored = (string) get_option($this->trigger_enabled_option_name($trigger));
        if ($stored === '1') {
            return true;
        }
        if ($stored === '0') {
            return false;
        }

        if ($definition === null) {
            $definition = $this->triggers[$trigger] ?? [];
        }
        if (array_key_exists('default_enabled', (array) $definition)) {
            return (bool) $definition['default_enabled'];
        }

        return trim((string) $this->get_trigger_value($trigger)) !== '';
    }

    public function is_any_trigger_active()
    {
        foreach ($this->get_available_triggers() as $trigger_id => $definition) {
            if ($definition['enabled'] && trim((string) $definition['value']) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function set_error($error, $log_message = true)
    {
        $GLOBALS['sms_error'] = $error;

        if ($log_message) {
            log_activity('Failed to send SMS via ' . get_class($this) . ': ' . $error);
        }

        return $this;
    }

    private function _is_trigger_message_empty($message)
    {
        if (trim($message) === '') {
            return false;
        }

        return true;
    }

    public function is_trigger_active($trigger)
    {
        if ($trigger === '') {
            return $this->is_any_trigger_active();
        }

        return $this->is_trigger_enabled($trigger)
            && trim((string) $this->get_trigger_value($trigger)) !== '';
    }

    public function get_active_gateway()
    {
        $active = false;

        foreach ($this->get_gateways() as $gateway) {
            if ($this->get_option($gateway['id'], 'active') == '1') {
                $active = $gateway;

                break;
            }
        }

        return $active;
    }

    /**
     * Check if is settings page in admin area
     * @return boolean
     */
    private function is_options_page()
    {
        return $this->ci->input->get('group') == 'sms' && $this->ci->uri->segment(2) == 'settings';
    }

    /**
     * Check if sms gateway is initialized and options are added into database
     * @return boolean
     */
    private function is_initialized($id)
    {
        return $this->get_option($id, 'initialized') == '' ? false : true;
    }

    /**
     * Log success message
     *
     * @param  string $number
     * @param  string $message
     *
     * @return void
     */
    protected function logSuccess($number, $message)
    {
        return log_activity('SMS sent to ' . $number . ', Message: ' . $message);
    }

    private function set_default_triggers()
    {
        $customer_merge_fields = [
            '{contact_firstname}',
            '{contact_lastname}',
            '{client_company}',
            '{client_vat_number}',
            '{client_id}',
        ];

        $invoice_merge_fields = [
            '{invoice_link}',
            '{invoice_number}',
            '{invoice_duedate}',
            '{invoice_date}',
            '{invoice_status}',
            '{invoice_subtotal}',
            '{invoice_total}',
            '{invoice_amount_due}',
            '{invoice_short_url}',
        ];

        $proposal_merge_fields = [
            '{proposal_number}',
            '{proposal_id}',
            '{proposal_subject}',
            '{proposal_date}',
            '{proposal_open_till}',
            '{proposal_subtotal}',
            '{proposal_total}',
            '{proposal_proposal_to}',
            '{proposal_link}',
            '{proposal_short_url}',
        ];

        $contract_merge_fields = [
            '{contract_id}',
            '{contract_subject}',
            '{contract_datestart}',
            '{contract_dateend}',
            '{contract_contract_value}',
            '{contract_link}',
            '{contract_short_url}',
        ];

        $triggers = [

            SMS_TRIGGER_INVOICE_OVERDUE => [
                'merge_fields' => array_merge($customer_merge_fields, $invoice_merge_fields, ['{total_days_overdue}']),
                'label'        => 'Aviso de fatura vencida',
                'info'         => 'Enviado aos contatos do cliente quando a fatura estiver vencida.',
            ],

            SMS_TRIGGER_INVOICE_DUE => [
                'merge_fields' => array_merge($customer_merge_fields, $invoice_merge_fields),
                'label'        => 'Aviso de vencimento da fatura',
                'info'         => 'Enviado aos contatos do cliente no aviso de vencimento da fatura.',
            ],

            SMS_TRIGGER_PAYMENT_RECORDED => [
                'merge_fields' => array_merge($customer_merge_fields, $invoice_merge_fields, ['{payment_total}', '{payment_date}']),
                'label'        => 'Pagamento de fatura registrado',
                'info'         => 'Enviado quando um pagamento da fatura for registrado.',
            ],

            SMS_TRIGGER_ESTIMATE_EXP_REMINDER => [
                'merge_fields' => array_merge(
                    $customer_merge_fields,
                    [
                        '{estimate_link}',
                        '{estimate_number}',
                        '{estimate_date}',
                        '{estimate_status}',
                        '{estimate_subtotal}',
                        '{estimate_total}',
                        '{estimate_short_url}',
                    ]
                ),
                'label' => 'Lembrete de vencimento do orçamento',
                'info'  => 'Enviado aos contatos do cliente no lembrete de vencimento do orçamento.',
            ],

            SMS_TRIGGER_PROPOSAL_EXP_REMINDER => [
                'merge_fields' => $proposal_merge_fields,
                'label'        => 'Lembrete de vencimento da proposta',
                'info'         => 'Enviado ao destinatário da proposta quando estiver próxima do vencimento.',
            ],

            SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_CUSTOMER => [
                'merge_fields' => $proposal_merge_fields,
                'label'        => 'Novo comentário em proposta (cliente)',
                'info'         => 'Enviado ao telefone da proposta quando a equipe registrar um comentário.',
            ],

            SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_STAFF => [
                'merge_fields' => $proposal_merge_fields,
                'label'        => 'Novo comentário em proposta (equipe)',
                'info'         => 'Enviado ao responsável pela proposta quando o cliente registrar um comentário.',
            ],

            SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_CUSTOMER => [
                'merge_fields' => array_merge($customer_merge_fields, $contract_merge_fields),
                'label'        => 'Novo comentário em contrato (cliente)',
                'info'         => 'Enviado aos contatos do cliente quando a equipe comentar no contrato.',
            ],

            SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_STAFF => [
                'merge_fields' => $contract_merge_fields,
                'label'        => 'Novo comentário em contrato (equipe)',
                'info'         => 'Enviado ao responsável quando o cliente comentar no contrato.',
            ],

            SMS_TRIGGER_CONTRACT_EXP_REMINDER => [
                'merge_fields' => array_merge($customer_merge_fields, $contract_merge_fields),
                'label'        => 'Lembrete de vencimento do contrato',
                'info'         => 'Enviado aos contatos do cliente pelo agendamento automático de vencimento de contratos.',
            ],

            SMS_TRIGGER_CONTRACT_SIGN_REMINDER => [
                'merge_fields' => array_merge($customer_merge_fields, $contract_merge_fields),
                'label'        => 'Lembrete para assinatura de contrato',
                'info'         => 'Enviado quando o contrato for apresentado para assinatura; cessam os lembretes após a assinatura.',
            ],

            SMS_TRIGGER_STAFF_REMINDER => [
                'merge_fields' => [
                    '{staff_firstname}',
                    '{staff_lastname}',
                    '{staff_reminder_description}',
                    '{staff_reminder_date}',
                    '{staff_reminder_relation_name}',
                    '{staff_reminder_relation_link}',
                ],
                'label' => 'Lembrete para colaborador',
                'info'  => 'Enviado ao colaborador quando receber um <a href="' . admin_url('misc/reminders') . '">lembrete</a> individual.',
            ],
        ];


        // Novos gatilhos ligados a eventos reais do CRM; instalação desativada.
        // O envio usa exclusivamente o gateway Connect|API já configurado.
        $additional = [
            'contact_created_notice' => [
                'label' => 'Boas-vindas ao novo contato',
                'info' => 'Enviado ao contato após o cadastro.',
                'merge_fields' => array_merge($customer_merge_fields, ['{contact_email}']),
                'group' => 'Relacionamento',
                'default_message' => 'Olá {contact_firstname}, seu cadastro foi realizado. Seja bem-vindo(a)!',
            ],
            'lead_created_notice' => [
                'label' => 'Nova oportunidade recebida',
                'info' => 'Confirma ao interessado o recebimento de uma nova solicitação.',
                'merge_fields' => ['{lead_name}', '{lead_id}'],
                'group' => 'Relacionamento',
                'default_message' => 'Olá {lead_name}, recebemos sua solicitação. Nossa equipe entrará em contato.',
            ],
            'ticket_created_notice' => [
                'label' => 'Novo chamado de suporte',
                'info' => 'Enviado ao contato vinculado ao chamado quando ele é aberto.',
                'merge_fields' => ['{ticket_id}', '{ticket_subject}', '{contact_firstname}'],
                'group' => 'Suporte',
                'default_message' => 'Olá {contact_firstname}, recebemos o chamado #{ticket_id}: {ticket_subject}.',
            ],
            'estimate_sent_notice' => [
                'label' => 'Orçamento enviado',
                'info' => 'Enviado aos contatos ativos do cliente quando o orçamento é encaminhado.',
                'merge_fields' => ['{estimate_id}', '{estimate_number}', '{contact_firstname}'],
                'group' => 'Vendas',
                'default_message' => 'Olá {contact_firstname}, seu orçamento nº {estimate_number} está disponível.',
            ],
            'proposal_sent_notice' => [
                'label' => 'Proposta enviada',
                'info' => 'Enviado ao telefone cadastrado na proposta após o envio.',
                'merge_fields' => $proposal_merge_fields,
                'group' => 'Vendas',
                'default_message' => 'Sua proposta nº {proposal_number} foi enviada. Assunto: {proposal_subject}.',
            ],
            'proposal_accepted_notice' => [
                'label' => 'Proposta aprovada',
                'info' => 'Informa ao colaborador responsável quando a proposta é aprovada.',
                'merge_fields' => ['{proposal_id}', '{proposal_subject}'],
                'group' => 'Vendas',
                'default_message' => 'Proposta aprovada! Referência #{proposal_id}: {proposal_subject}.',
            ],
            'task_assignee_added_notice' => [
                'label' => 'Tarefa atribuída',
                'info' => 'Enviado ao colaborador que foi atribuído à tarefa.',
                'merge_fields' => ['{task_id}', '{task_name}'],
                'group' => 'Equipe',
                'default_message' => 'Uma tarefa foi atribuída a você: {task_name} (#{task_id}).',
            ],
            'task_completed_notice' => [
                'label' => 'Tarefa concluída',
                'info' => 'Enviado ao criador da tarefa quando ela for concluída.',
                'merge_fields' => ['{task_id}', '{task_name}'],
                'group' => 'Equipe',
                'default_message' => 'A tarefa {task_name} (#{task_id}) foi concluída.',
            ],
            'project_finished_notice' => [
                'label' => 'Projeto concluído',
                'info' => 'Enviado aos contatos ativos do cliente após a conclusão do projeto.',
                'merge_fields' => ['{project_id}', '{project_name}', '{contact_firstname}'],
                'group' => 'Projetos',
                'default_message' => 'Olá {contact_firstname}, o projeto {project_name} foi concluído.',
            ],
            'invoice_cancelled_notice' => [
                'label' => 'Fatura cancelada',
                'info' => 'Enviado aos contatos ativos do cliente quando a fatura é cancelada.',
                'merge_fields' => ['{invoice_id}', '{invoice_number}', '{contact_firstname}'],
                'group' => 'Financeiro',
                'default_message' => 'Olá {contact_firstname}, a fatura {invoice_number} foi cancelada.',
            ],
        ];
        foreach ($additional as $id => $definition) {
            $definition['default_enabled'] = false;
            $triggers[$id] = $definition;
        }

        $defaults = [
            SMS_TRIGGER_INVOICE_OVERDUE => ['Financeiro', 'Olá {contact_firstname}, sua fatura {invoice_number} está vencida. Consulte: {invoice_link}'],
            SMS_TRIGGER_INVOICE_DUE => ['Financeiro', 'Olá {contact_firstname}, a fatura {invoice_number} vence em {invoice_duedate}. Consulte: {invoice_link}'],
            SMS_TRIGGER_PAYMENT_RECORDED => ['Financeiro', 'Olá {contact_firstname}, recebemos o pagamento da fatura {invoice_number}. Obrigado!'],
            SMS_TRIGGER_ESTIMATE_EXP_REMINDER => ['Vendas', 'Olá {contact_firstname}, seu orçamento {estimate_number} está próximo do vencimento.'],
            SMS_TRIGGER_PROPOSAL_EXP_REMINDER => ['Vendas', 'A proposta {proposal_number} está próxima do vencimento. Consulte: {proposal_link}'],
            SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_CUSTOMER => ['Vendas', 'Há um novo comentário na proposta {proposal_number}. Acesse: {proposal_link}'],
            SMS_TRIGGER_PROPOSAL_NEW_COMMENT_TO_STAFF => ['Vendas', 'O cliente comentou na proposta {proposal_number}.'],
            SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_CUSTOMER => ['Contratos', 'Há um novo comentário no contrato {contract_subject}.'],
            SMS_TRIGGER_CONTRACT_NEW_COMMENT_TO_STAFF => ['Contratos', 'O cliente comentou no contrato {contract_subject}.'],
            SMS_TRIGGER_CONTRACT_EXP_REMINDER => ['Contratos', 'O contrato {contract_subject} está próximo do vencimento ({contract_dateend}).'],
            SMS_TRIGGER_CONTRACT_SIGN_REMINDER => ['Contratos', 'O contrato {contract_subject} está aguardando sua assinatura.'],
            SMS_TRIGGER_STAFF_REMINDER => ['Equipe', 'Olá {staff_firstname}, lembrete: {staff_reminder_description}.'],
        ];
        foreach ($defaults as $id => $default) {
            if (isset($triggers[$id])) {
                $triggers[$id]['group'] = $default[0];
                $triggers[$id]['default_message'] = $default[1];
            }
        }

        $this->triggers = hooks()->apply_filters('sms_triggers', $triggers);
    }
}