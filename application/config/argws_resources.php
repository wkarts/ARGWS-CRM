<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Native ARGWS CRM resources. Keys intentionally match existing module system
 * names so activation state, routes, stored data and local migrations remain
 * compatible with installations already in production.
 */
$config['argws_resources'] = [
    'accounting' => ['name' => 'Contabilidade', 'description' => 'Rotinas contábeis integradas ao CRM.', 'requires' => []],
    'api' => ['name' => 'API', 'description' => 'Endpoints e autenticação de integrações.', 'requires' => []],
    'asaas' => ['name' => 'Asaas', 'description' => 'Integração de pagamentos com Asaas.', 'requires' => []],
    'backup' => ['name' => 'Backup', 'description' => 'Rotinas locais de cópia e restauração.', 'requires' => []],
    'connect_api_chat' => ['name' => 'Connect API Chat', 'description' => 'Chat e comunicação pelo Connect API.', 'requires' => ['connect_api_connector']],
    'connect_api_connector' => ['name' => 'Connect API Connector', 'description' => 'Conexão operacional com instâncias Connect API.', 'requires' => [], 'optional' => ['connect_api_manager']],
    'connect_api_manager' => ['name' => 'Connect API Manager', 'description' => 'Gerenciamento local das conexões Connect API.', 'requires' => []],
    'custom_links' => ['name' => 'Links personalizados', 'description' => 'Links e páginas incorporadas por instalação.', 'requires' => []],
    'einvoice' => ['name' => 'Nota fiscal eletrônica', 'description' => 'Emissão e gestão de documentos fiscais.', 'requires' => []],
    'elite_custom_js_css' => ['name' => 'Personalização de interface', 'description' => 'Recursos de personalização visual da instalação.', 'requires' => []],
    'exports' => ['name' => 'Exportação de dados', 'description' => 'Exportação de listas e relatórios.', 'requires' => []],
    'finance' => ['name' => 'Financeiro', 'description' => 'Conciliação e rotinas financeiras complementares.', 'requires' => []],
    'fleet' => ['name' => 'Frota', 'description' => 'Gestão de veículos, abastecimentos e manutenção.', 'requires' => []],
    'gerencianet_gateway' => ['name' => 'Efí', 'description' => 'Integração de pagamentos com Efí.', 'requires' => []],
    'goals' => ['name' => 'Metas', 'description' => 'Metas e acompanhamento de resultados.', 'requires' => []],
    'hr_payroll' => ['name' => 'Folha de pagamento', 'description' => 'Rotinas de folha integradas aos cadastros.', 'requires' => []],
    'hr_profile' => ['name' => 'Recursos humanos', 'description' => 'Cadastros e rotinas de recursos humanos.', 'requires' => []],
    'ideal' => ['name' => 'iDEAL', 'description' => 'Integração de pagamentos iDEAL.', 'requires' => []],
    'invoices_builder' => ['name' => 'Editor de faturas', 'description' => 'Composição e personalização de documentos.', 'requires' => []],
    'ma' => ['name' => 'Automação de marketing', 'description' => 'Automação de campanhas e contatos.', 'requires' => []],
    'menu_setup' => ['name' => 'Menus', 'description' => 'Configuração da navegação da instalação.', 'requires' => []],
    'multiple_companies' => ['name' => 'Múltiplas empresas', 'description' => 'Organização de empresas dentro da instalação.', 'requires' => []],
    'openai' => ['name' => 'OpenAI', 'description' => 'Integrações OpenAI configuradas pelo administrador.', 'requires' => []],
    'page_builder' => ['name' => 'Construtor de páginas', 'description' => 'Edição de páginas e conteúdo visual.', 'requires' => []],
    'products' => ['name' => 'Produtos', 'description' => 'Catálogo de produtos e serviços.', 'requires' => []],
    'purchase' => ['name' => 'Compras', 'description' => 'Pedidos e rotinas de compra.', 'requires' => []],
    'service_management' => ['name' => 'Gestão de serviços', 'description' => 'Ordens e acompanhamento de serviços.', 'requires' => []],
    'si_custom_theme' => ['name' => 'Tema do cliente', 'description' => 'Opções de apresentação da área de clientes.', 'requires' => []],
    'surveys' => ['name' => 'Pesquisas', 'description' => 'Pesquisas e coleta de respostas locais.', 'requires' => []],
    'theme_style' => ['name' => 'Estilos da interface', 'description' => 'Ajustes de estilo da interface administrativa.', 'requires' => []],
    'timesheets' => ['name' => 'Apontamento de horas', 'description' => 'Registro e acompanhamento de horas.', 'requires' => []],
    'translations' => ['name' => 'Traduções', 'description' => 'Gerenciamento interno dos textos traduzíveis.', 'requires' => []],
    'webhooks' => ['name' => 'Webhooks', 'description' => 'Recebimento e envio de eventos configurados.', 'requires' => []],
    'wiki' => ['name' => 'Base de conhecimento', 'description' => 'Artigos e documentação interna.', 'requires' => []],
    'zillapage' => ['name' => 'Páginas públicas', 'description' => 'Páginas e formulários publicados pela instalação.', 'requires' => []],
];
