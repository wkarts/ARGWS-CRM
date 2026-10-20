<?php

declare(strict_types=1);
/** Explicit catalog for company grants. Definitions do not grant any permission by themselves. */
final class CompanyResourceCatalog
{
    public static function definitions():array
    {
        $crud=['view'=>'Consultar','create'=>'Criar','edit'=>'Editar','delete'=>'Cancelar quando elegível'];
        $result=[];foreach(['invoices'=>'Faturas da empresa','credit_notes'=>'Créditos da empresa','payments'=>'Recebimentos e estornos','projects'=>'Projetos da empresa','tasks'=>'Tarefas da empresa','expenses'=>'Despesas da empresa','contracts'=>'Contratos da empresa','tickets'=>'Atendimento da empresa','estimates'=>'Orçamentos da empresa','proposals'=>'Propostas da empresa'] as $key=>$name)$result[$key]=['name'=>$name,'capabilities'=>$crud];
        $result['customers']=['name'=>'Clientes (identidade)','capabilities'=>['view'=>'Consultar','create'=>'Criar','edit'=>'Editar próprios','share'=>'Compartilhar identidade']];
        $result['settings']=['name'=>'Definições da empresa','capabilities'=>['view'=>'Consultar','edit'=>'Editar']];
        $result['email_templates']=['name'=>'Modelos de e-mail','capabilities'=>['view'=>'Consultar','edit'=>'Editar']];
        $result['reports']=['name'=>'Relatórios da empresa','capabilities'=>['view'=>'Consultar e exportar com acesso aos recursos']];
        $result['company_api']=['name'=>'Credenciais da API da empresa','capabilities'=>['manage'=>'Gerenciar as próprias credenciais vinculadas a esta empresa']];
        $result['imports']=['name'=>'Importação e exportação de clientes/despesas da empresa','capabilities'=>['manage'=>'Validar e confirmar lotes próprios']];
        $result['banco_brasil']=['name'=>'Banco do Brasil','capabilities'=>['view'=>'Consultar','manage'=>'Configurar','import'=>'Importar retorno observacional','generate'=>'Gerar remessa240 local','download'=>'Baixar remessa','reconcile'=>'Conciliar retorno com evidência','reconcile_api_components'=>'Conciliar composição de crédito API16','reconcile_api_return'=>'Conciliar retorno240 de origem API declarada','reconcile_components'=>'Conciliar componentes com confirmação independente','external_reverse'=>'Declarar estorno externo integral de recibo composto','api_issue'=>'Registrar cobrança API','api_query'=>'Consultar cobrança API','api_charges'=>'Emitir API com juros diários/mensais declarados','api_discount'=>'Declarar e emitir ofertas de desconto API','api_amend'=>'Solicitar alteração de vencimento API','api_cancel'=>'Solicitar baixa API']];
        return $result;
    }
}
