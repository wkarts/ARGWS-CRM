<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConciliarFaturas extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("finance_model"); // Modelo específico do módulo
        $this->load->model("currencies_model"); // Modelo global do CRM
    }

    public function index()
    {
        // Obter transações e dados auxiliares
        $transacoes = $this->finance_model->get_transacoes_para_conciliacao();
        $bancos = $this->finance_model->get_bancos();
        $categorias_entrada = $this->finance_model->get_categorias_por_tipo("entrada");
        $categorias_saida = $this->finance_model->get_categorias_por_tipo("saida");

        // Obter símbolo da moeda usando o currencies_model global
        $currency = $this->currencies_model->get_base_currency();
        $currency_symbol = isset($currency->symbol) ? $currency->symbol : 'R$'; // Fallback para 'R$'

        // Validar dados para evitar erros
        $transacoes = $transacoes ?: [];
        $bancos = $bancos ?: [];
        $categorias_entrada = $categorias_entrada ?: [];
        $categorias_saida = $categorias_saida ?: [];

        // Marcar transações já conciliadas
        foreach ($transacoes as &$transacao) {
            $transacao['conciliado'] = $this->finance_model->verificar_conciliacao(
                $transacao['date'], 
                $transacao['valor'], 
                $transacao['descricao']
            );
        }

        // Dados para a view
        $data = [
            'transacoes' => $transacoes,
            'bancos' => $bancos,
            'categorias_entrada' => $categorias_entrada,
            'categorias_saida' => $categorias_saida,
            'currency_symbol' => $currency_symbol
        ];

        // Carregar a view
        $this->load->view("finance/conciliar_faturas", $data);
    }

    public function salvar_conciliacao()
    {
        if ($this->input->is_ajax_request()) {
            // Validar dados do formulário
            $transacao_id = $this->input->post('transacao_id');
            $banco_id = $this->input->post('banco_id');
            $categoria_id = $this->input->post('categoria_id');

            if (!$banco_id || !$categoria_id) {
                echo json_encode(['status' => 'error', 'message' => 'Banco ou categoria ausente.']);
                return;
            }

            // Preparar dados para inserção
            $data = [
                'ofx_id' => "ofx-" . bin2hex(random_bytes(4)),
                'data' => $this->input->post('data'),
                'descricao' => $this->input->post('descricao'),
                'valor' => $this->input->post('valor'),
                'tipo' => $this->input->post('tipo'), // Tipo de transação: entrada ou saída
                'categoria_id' => $categoria_id,
                'banco_origem_id' => $banco_id,
                'conciliado' => 1,
                'tipo_conciliacao' => 'Manual'
            ];

            // Log para depuração
            log_activity('Dados para conciliação: ' . print_r($data, true));

            // Inserir no banco de dados
            $insert = $this->finance_model->add_conciliacao($data);

            // Responder com status JSON
            echo json_encode(['status' => $insert ? 'success' : 'error']);
        }
    }
}
