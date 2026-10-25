<?php

declare(strict_types=1);
/** Explicit catalog for company grants. Definitions do not grant any permission by themselves. */
final class CompanyResourceCatalog
{
    public static function definitions():array
    {
        $crud=['view'=>'Consultar','create'=>'Criar','edit'=>'Editar','delete'=>'Cancelar quando elegível'];
        $result=[];foreach(['invoices'=>'Faturas da empresa','credit_notes'=>'Créditos da empresa','payments'=>'Recebimentos e estornos','projects'=>'Projetos da empresa','tasks'=>'Tarefas da empresa','expenses'=>'Despesas da empresa','contracts'=>'Contratos da empresa','tickets'=>'Atendimento da empresa','estimates'=>'Orçamentos da empresa','proposals'=>'Propostas da empresa'] as $key=>$name)$result[$key]=['name'=>$name,'capabilities'=>$crud];
        $result['roles']=['name'=>'Funções da equipe','capabilities'=>['view'=>'Consultar','create'=>'Criar','edit'=>'Editar','delete'=>'Excluir quando sem vínculos']];
        $result['staff']=['name'=>'Equipe da empresa','capabilities'=>['view'=>'Consultar vínculos próprios','create'=>'Criar ou vincular colaborador','edit'=>'Editar perfil permitido e concessões próprias','delete'=>'Revogar vínculo próprio']];
        $result['purchases']=['name'=>'Compras e fornecedores da empresa','capabilities'=>['view'=>'Consultar','create'=>'Criar fornecedores e requisições','edit'=>'Editar fornecedores e anexos','approve'=>'Aprovar requisições, pedidos e documentos','receive'=>'Registrar recebimentos','pay'=>'Registrar liquidações externas e reversões','portal'=>'Emitir e revogar convites próprios','report'=>'Consultar e exportar relatórios próprios','settings'=>'Configurar numeração e câmbio próprios']];
        $result['leads']=['name'=>'Leads privados da empresa','capabilities'=>['view'=>'Consultar leads, Kanban e avisos próprios','create'=>'Criar e importar leads próprios','edit'=>'Editar, configurar, anexar e converter com acesso a clientes','delete'=>'Arquivar e restaurar leads elegíveis']];
        $result['customers']=['name'=>'Clientes (identidade)','capabilities'=>['view'=>'Consultar','create'=>'Criar','edit'=>'Editar próprios','share'=>'Compartilhar identidade']];
        $result['calendar']=['name'=>'Calendário privado da empresa','capabilities'=>['view'=>'Consultar somente fontes próprias autorizadas','configure'=>'Configurar apresentação e fontes com edição de definições']];
        $result['settings']=['name'=>'Definições da empresa','capabilities'=>['view'=>'Consultar','edit'=>'Editar']];
        $result['email_templates']=['name'=>'Modelos de e-mail','capabilities'=>['view'=>'Consultar','edit'=>'Editar']];
        $result['reports']=['name'=>'Relatórios da empresa','capabilities'=>['view'=>'Consultar e exportar com acesso aos recursos']];
        $result['company_api']=['name'=>'Credenciais da API da empresa','capabilities'=>['manage'=>'Gerenciar as próprias credenciais vinculadas a esta empresa']];
        $result['imports']=['name'=>'Importação e exportação de clientes/despesas da empresa','capabilities'=>['manage'=>'Validar e confirmar lotes próprios']];
        $result['banco_brasil']=['name'=>'Banco do Brasil','capabilities'=>['view'=>'Consultar','manage'=>'Configurar','import'=>'Importar retorno observacional','generate'=>'Gerar remessa240 local','download'=>'Baixar remessa','reconcile'=>'Conciliar retorno com evidência','local_noncash'=>'Declarar e restaurar redução LOCAL integral sem caixa','settle_after_noncash'=>'Registrar fundos independentes após restauração LOCAL','reconcile_api_components'=>'Conciliar composição de crédito API16','reconcile_api_return'=>'Conciliar retorno240 de origem API declarada','reconcile_components'=>'Conciliar componentes com confirmação independente','reconcile_tariff'=>'Conciliar integral nominal240 com tarifa positiva documentada','external_reverse_api_components'=>'Declarar estorno externo integral API16','external_reverse'=>'Declarar estorno externo integral de recibo composto','api_issue'=>'Registrar cobrança API','api_query'=>'Consultar cobrança API','api_charges'=>'Emitir API com juros diários/mensais declarados','api_discount'=>'Declarar e emitir ofertas de desconto API','api_receipt_window'=>'Emitir prazo de recebimento explícito API','api_amend_window'=>'Alterar prazo explícito de recebimento API','api_cancel_discount'=>'Cancelar primeiro desconto API','api_assign_discount'=>'Atribuir primeiro desconto API','api_amend_discount'=>'Alterar primeiro desconto API do mesmo perfil','api_amend_reference'=>'Alterar Seu Número operacional API','api_amend_address'=>'Alterar endereço operacional do pagador API','api_amend_abatement'=>'Solicitar inclusão ou alteração de abatimento registrado API','api_amend'=>'Solicitar alteração de vencimento API','api_cancel'=>'Solicitar baixa API']];
        $result['connect-api-connector']=['name'=>'Connect|API próprio da empresa','capabilities'=>['view'=>'Consultar perfil e fluxo próprio','configure'=>'Configurar token e conferir instância','send'=>'Enfileirar e tentar avisos privados','reconnect'=>'Parear por QR e reservar, tentar e consultar reinício próprio']];
        $result['pusher']=['name'=>'Pusher privado da empresa','capabilities'=>['view'=>'Receber e consultar avisos próprios','configure'=>'Configurar e revogar aplicativo próprio','publish'=>'Enfileirar e tentar aviso de tarefa própria']];
        $result['company-ai']=['name'=>'Assistente privado da empresa','capabilities'=>['view'=>'Consultar configuração própria','configure'=>'Configurar e revogar credenciais próprias','use'=>'Revisar e solicitar assistência de chamados próprios']];
        $result['hr']=['name'=>'RH da empresa','capabilities'=>['view'=>'Consultar vínculos','create'=>'Criar vínculos','edit'=>'Editar vínculos','configure'=>'Configurar estrutura e orientações','import'=>'Importar vínculos revisados','export'=>'Exportar conteúdo autorizado']];
        $result['hr_sensitive']=['name'=>'RH — dados privados','capabilities'=>['view'=>'Consultar dados privados','edit'=>'Editar dados privados','approve'=>'Decidir dependentes']];
        $result['hr_contracts']=['name'=>'RH — contratos','capabilities'=>['view'=>'Consultar contratos','create'=>'Criar contratos','edit'=>'Alterar e confirmar contratos']];
        $result['hr_training']=['name'=>'RH — treinamentos','capabilities'=>['view'=>'Consultar treinamentos','configure'=>'Configurar cursos e questões','assign'=>'Atribuir e cancelar treinamentos']];
        $result['hr_files']=['name'=>'RH — arquivos','capabilities'=>['view'=>'Consultar arquivos do domínio autorizado','create'=>'Adicionar arquivos','delete'=>'Ocultar arquivos']];
        $result['hr_portal']=['name'=>'RH — participantes','capabilities'=>['manage'=>'Emitir e revogar acessos de participante']];
        $result['hr_reports']=['name'=>'RH — relatórios','capabilities'=>['view'=>'Consultar relatórios autorizados']];
        return $result;
    }
}
