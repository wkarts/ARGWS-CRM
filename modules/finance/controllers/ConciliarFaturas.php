<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConciliarFaturas extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("finance_model");
        $this->load->model("currencies_model");
    }

    public function index()
    {
        $transacoes = $this->finance_model->get_transacoes_para_conciliacao();
        $bancos = $this->finance_model->get_bancos();
        $categorias_entrada = $this->finance_model->get_categorias_por_tipo("entrada");
        $categorias_saida = $this->finance_model->get_categorias_por_tipo("saida");
        $currency = get_base_currency();
        $currency_symbol = isset($currency->symbol) ? $currency->symbol : 'R$';

         // Marcar transações já conciliadas
    foreach ($transacoes as &$transacao) {
        $transacao['conciliado'] = $this->finance_model->verificar_conciliacao(
            $transacao['date'], 
            $transacao['valor'], 
            $transacao['descricao']
        );
    }

        $data = [
            'transacoes' => $transacoes,
            'bancos' => $bancos,
            'categorias_entrada' => $categorias_entrada,
            'categorias_saida' => $categorias_saida,
            'currency_symbol' => $currency_symbol
        ];

        $this->load->view("finance/conciliar_faturas", $data);
    }

    public function salvar_conciliacao()
{
    if ($this->input->is_ajax_request()) {
        // Receber e preparar os dados do formulário
        $transacao_id = $this->input->post('transacao_id');
        $data = [
            'ofx_id' => "ofx-" . bin2hex(random_bytes(4)),
            'data' => $this->input->post('data'),
            'descricao' => $this->input->post('descricao'),
            'valor' => $this->input->post('valor'),
            'tipo' => $this->input->post('tipo'), // Tipo de transação: entrada ou saída
            'categoria_id' => $this->input->post('categoria_id'), // ID da categoria
            'banco_id' => "", // ID do banco
            'banco_origem_id' => $this->input->post('banco_id'), // Banco de origem agora selecionado no modal
            'conciliado' => 1,
            'tipo_conciliacao' => 'Manual'
        ];
        
        // Log de dados para depuração
        log_activity('Dados para conciliação: ' . print_r($data, true));

        // Inserir os dados no banco
        $insert = $this->finance_model->add_conciliacao($data);

        // Resposta JSON com o status da inserção
        echo json_encode(['status' => $insert ? 'success' : 'error']);
    }
}
}
