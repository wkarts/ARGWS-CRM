<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['connect_api_menu_root'] = 'Connect|API';
$lang['connect_api_connector_menu'] = 'Conector';
$lang['connect_api_connector'] = 'Conector do Connect|API';
$lang['connect_api_connector_title'] = 'Conector do Connect|API';
$lang['connect_api_connector_permission_view'] = 'Visualizar';
$lang['connect_api_connector_permission_configure'] = 'Configurar conexão';
$lang['connect_api_connector_permission_send'] = 'Enviar mensagens';
$lang['connect_api_connector_permission_reconnect'] = 'Reconectar instância';
$lang['connect_api_connector_settings'] = 'Configuração';
$lang['connect_api_connector_api_url'] = 'URL do Connect|API';
$lang['connect_api_connector_instance_name'] = 'Nome da instância';
$lang['connect_api_connector_instance_token'] = 'Token da instância';
$lang['connect_api_connector_token_preserved'] = 'Deixe em branco para preservar o token atual.';
$lang['connect_api_connector_default_country'] = 'DDI padrão';
$lang['connect_api_connector_default_area'] = 'DDD padrão';
$lang['connect_api_connector_timeout'] = 'Tempo limite (segundos)';
$lang['connect_api_connector_auto_normalize'] = 'Normalizar números automaticamente';
$lang['connect_api_connector_verify_tls'] = 'Validar certificado TLS';
$lang['connect_api_connector_test_connection'] = 'Testar conexão';
$lang['connect_api_connector_connection_ok'] = 'Conexão realizada. Estado';
$lang['connect_api_connector_not_configured'] = 'Configure a URL, o nome e o token da instância para ativar a conexão operacional.';
$lang['connect_api_connector_connection'] = 'Conexão da instância';
$lang['connect_api_connector_connection_method'] = 'Método de conexão';
$lang['connect_api_connector_pairing_code'] = 'Código de pareamento';
$lang['connect_api_connector_pairing_number'] = 'Telefone para pareamento';
$lang['connect_api_connector_connect'] = 'Iniciar conexão';
$lang['connect_api_connector_restart'] = 'Reiniciar';
$lang['connect_api_connector_restart_ok'] = 'Solicitação de reinício concluída.';
$lang['connect_api_connector_send_test'] = 'Teste de envio';
$lang['connect_api_connector_test_number'] = 'Número de teste';
$lang['connect_api_connector_test_message'] = 'Mensagem';
$lang['connect_api_connector_send'] = 'Enviar';
$lang['connect_api_connector_test_required'] = 'Número e mensagem são obrigatórios.';
$lang['connect_api_connector_test_sent'] = 'Mensagem de teste enviada.';
$lang['connect_api_connector_managed_instances'] = 'Instâncias do Manage';
$lang['connect_api_connector_use_managed'] = 'Usar instância';
$lang['connect_api_connector_managed_bound'] = 'Instância vinculada ao conector.';
$lang['connect_api_connector_managed_not_found'] = 'Instância do Manage não localizada.';
$lang['connect_api_connector_managed_not_available'] = 'O módulo Manage não está ativo.';
$lang['connect_api_connector_trigger_invoice_sent'] = 'Enviar fatura ao cliente';
$lang['connect_api_connector_trigger_invoice_sent_info'] = 'Acionado quando a fatura é enviada aos contatos ativos do cliente.';
$lang['connect_api_connector_trigger_payment_recorded'] = 'Pagamento de fatura registrado';
$lang['connect_api_connector_trigger_payment_recorded_info'] = 'Acionado quando o pagamento da fatura é registrado.';
$lang['connect_api_connector_status_summary'] = 'Resumo da conexão';
$lang['connect_api_connector_configuration'] = 'Configuração principal';
$lang['connect_api_connector_actions'] = 'Conectar / reconectar';
$lang['connect_api_connector_manager_help'] = 'Selecione uma instância já provisionada pelo Manage para preencher a conexão automaticamente.';

$lang['connect_api_connector_pairing_number_required'] = 'Informe um telefone válido para usar o código de pareamento.';
$lang['connect_api_connector_connection_waiting'] = 'Aguardando a conexão da instância...';
$lang['connect_api_connector_connection_connected'] = 'Instância conectada. Retornando automaticamente...';
$lang['connect_api_connector_instance_phone'] = 'Número da instância';
$lang['connect_api_connector_instance_phone_waiting'] = 'Aguardando identificação do número pela API.';

$lang['connect_api_connector_manager_unavailable_help'] = 'O módulo Manage não está instalado ou ativo. Ative o Manage para selecionar uma instância administrada.';
$lang['connect_api_connector_manager_unavailable_option'] = 'Manage não disponível';
$lang['connect_api_connector_no_managed_instances'] = 'Nenhuma instância disponível no Manage';

$lang['connect_api_connector_sms_takeover'] = 'Assumir notificações SMS/WhatsApp do CRM';
$lang['connect_api_connector_sms_takeover_info'] = 'Quando ativo, o Connect|API Conector torna-se o gateway de mensagens do CRM. Todos os gatilhos SMS já configurados no sistema passam a ser enviados por WhatsApp pela instância vinculada, incluindo cobranças, pagamentos, lembretes, propostas, contratos e notificações de equipe.';
$lang['connect_api_connector_sms_gateway_active'] = 'Gateway de notificações ativo';
$lang['connect_api_connector_sms_gateway_inactive'] = 'Gateway de notificações inativo';
$lang['connect_api_connector_trigger_invoice_sent'] = 'Fatura enviada ao cliente / cobrança';
$lang['connect_api_connector_trigger_invoice_sent_info'] = 'Compatível com o gatilho histórico invoice_send_to_customer2. É acionado quando a fatura é enviada aos contatos do cliente.';

$lang['connect_api_connector_sms_configure_triggers'] = 'Editar mensagens e gatilhos';
