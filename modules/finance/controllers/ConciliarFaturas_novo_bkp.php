<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConciliarFaturas extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("finance_model"); // Removida a dependência de currencies_model
    }

    public function index()
    {
        // Obter dados do modelo
        $transacoes = $this->finance_model->get_transacoes_para_conciliacao();
        $bancos = $this->finance_model->get_bancos();
        $categorias_entrada = $this->finance_model->get_categorias_por_tipo("entrada");
        $categorias_saida = $this->finance_model->get_categorias_por_tipo("saida");

        // Validar dados retornados
        if (!$transacoes) {
            $transacoes = [];
        }
        if (!$bancos) {
            $bancos = [];
        }
        if (!$categorias_entrada) {
            $categorias_entrada = [];
        }
        if (!$categorias_saida) {
            $categorias_saida = [];
        }

        // Obter símbolo da moeda diretamente do banco de dados
        $currency_symbol = $this->finance_model->get_currency_symbol();

        // Marcar transações já conciliadas
        foreach ($transacoes as &$transacao) {
            $transacao['conciliado'] = $this->finance_model->verificar_conciliacao(
                $transacao['date'], 
                $transacao['valor'], 
                $transacao['descricao']
            );
        }

        // Preparar dados para a view
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
            // Receber dados do formulário
            $transacao_id = $this->input->post('transacao_id');
            $banco_id = $this->input->post('banco_id');
            $categoria_id = $this->input->post('categoria_id');

            // Validar campos obrigatórios
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
                'tipo' => $this->input->post('tipo'),
                'categoria_id' => $categoria_id,
                'banco_origem_id' => $banco_id,
                'conciliado' => 1,
                'tipo_conciliacao' => 'Manual'
            ];

            // Registrar atividade para depuração
            log_activity('Dados para conciliação: ' . print_r($data, true));

            // Inserir os dados no banco
            $insert = $this->finance_model->add_conciliacao($data);

            // Retornar status como JSON
            echo json_encode(['status' => $insert ? 'success' : 'error']);
        }
    }

    // Método de teste para verificar carregamento do controlador
    public function test()
    {
        echo "Controlador ConciliarFaturas está funcionando.";
    }
}
