<?php
defined("BASEPATH") or exit("No direct script access allowed");

require_once __DIR__ . "/../vendor/autoload.php";

use OfxParser\Parser;

class Conciliacao extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("conciliacao_model");
        $this->load->model("banco_model");
        $this->load->model("bank_model");
        $this->load->model("category_model"); // Carregando como "category_model"
    }

    // Tela de upload e visualização de transações
    public function index()
    {
        $data["conciliacoes"] = $this->conciliacao_model->get_all_conciliacoes();
        $this->load->view("finance/conciliacao_list", $data);
    }

    // Exibir o formulário de upload de OFX com pré-visualização das transações
    public function upload_ofx_form()
    {
        $data["banks"] = $this->banco_model->get_all_banks();
        $data["categorias"] = $this->category_model->get_all_categories(); // Corrigido para usar "category_model"
        $this->load->view("finance/upload_ofx_form", $data);
    }

    // public function preview_ofx()
    // {
    //     $data["transactions"] = $this->get_transactions_from_ofx();
    //     $data["categorias"] = $this->category_model->get_all_categories();
    //     $this->load->view("finance/ofx_preview", $data);
    // }


    public function preview_ofx()
{
    // Carrega a moeda base e obtém o símbolo
    $currency = $this->currencies_model->get_base_currency();
    $currency_symbol = $currency ? $currency->symbol : '$';

    // Carrega as transações e categorias
    $data["transactions"] = $this->get_transactions_from_ofx();
    $data["categorias"] = $this->category_model->get_all_categories();

    // Passa o símbolo da moeda para a view
    $data["currency_symbol"] = $currency_symbol;

    // Carrega a view com os dados necessários
    $this->load->view("finance/ofx_preview", $data);
}

    // Processar upload e leitura do arquivo OFX
    public function upload_ofx()
    {
        $bank_id = $this->input->post("bank_id"); // ID do banco selecionado no upload
    
        if (!empty($_FILES["ofx_file"]["name"]) && !empty($bank_id)) {
            $file_path = __DIR__ . "/../uploads/ofx/" . uniqid() . "_" . $_FILES["ofx_file"]["name"];
    
            if (move_uploaded_file($_FILES["ofx_file"]["tmp_name"], $file_path)) {
                try {
                    $parser = new Parser();
                    $ofx = $parser->loadFromFile($file_path);
                    $bankAccount = reset($ofx->bankAccounts);
                    $transactions = $bankAccount->statement->transactions;
    
                    $data["transactions"] = [];
                    foreach ($transactions as $transaction) {
                        $is_conciliado = $this->conciliacao_model->is_conciliado(
                            $transaction->date->format("Y-m-d"),
                            $transaction->memo,
                            $transaction->amount
                        );
    
                        $data["transactions"][] = [
                            "id" => uniqid(),
                            "data" => $transaction->date->format("Y-m-d"),
                            "descricao" => $transaction->memo,
                            "valor" => $transaction->amount,
                            "tipo" => $transaction->amount > 0 ? "entrada" : "saida",
                            "conciliado" => $is_conciliado,
                            "banco_origem_id" => $bank_id, // Incluindo o banco de origem para cada transação
                            "tipo_conciliacao" => "OFX"
                        ];
                    }
    
                    $data["bank_id"] = $bank_id;
                    $data["categorias"] = $this->category_model->get_all_categories();
                    $data["bancos"] = $this->banco_model->get_all_banks();
                    $this->load->view("finance/ofx_preview", $data);
                } catch (Exception $e) {
                    log_activity("Erro ao processar o arquivo OFX: " . $e->getMessage());
                    set_alert("danger", "Erro ao processar o arquivo OFX.");
                    redirect(admin_url("finance/conciliacao/upload_ofx_form"));
                }
            } else {
                set_alert("danger", "Erro ao fazer upload do arquivo OFX.");
                redirect(admin_url("finance/conciliacao/upload_ofx_form"));
            }
        } else {
            set_alert("danger", "Selecione um banco e um arquivo OFX.");
            redirect(admin_url("finance/conciliacao/upload_ofx_form"));
        }
    }

    public function salvar_conciliacao()
    {
        $data = [
            "ofx_id" => $this->input->post("ofx_id"),
            "data" => $this->input->post("data"),
            "descricao" => $this->input->post("descricao"),
            "valor" => $this->input->post("valor"),
            "tipo" => $this->input->post("tipo"),
            "categoria_id" => $this->input->post("categoria"),
            "banco_id" => $this->input->post("banco_destino") ?? null,
            "banco_origem_id" => $this->input->post("banco_origem_id"), // Incluindo o banco de origem
            "conciliado" => 1,
            "tipo_conciliacao" => "OFX", // Ou "Manual" dependendo do contexto
        ];
    
        log_message('debug', 'Dados recebidos para conciliação: ' . print_r($data, true));
    
        if ($this->conciliacao_model->add_conciliacao($data)) {
            echo json_encode(["status" => "success"]);
        } else {
            log_message("error", "Erro ao salvar conciliação no banco de dados.");
            echo json_encode([
                "status" => "error",
                "message" => "Erro ao salvar a conciliação no banco de dados.",
            ]);
        }
    } 
}
