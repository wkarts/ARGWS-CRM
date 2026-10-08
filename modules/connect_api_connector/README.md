# Connect|API Conector 1.1.2

Módulo operacional de uma instância do Connect|API.

Pode ser usado sozinho com URL + nome da instância + token ou em conjunto com o Manage.

Quando o Manage estiver disponível e possuir instâncias com token local, o Conector oferece seleção direta. Caso contrário, o bloco não é exibido.

O histórico completo de migrations permanece no subdiretório `migrations`.

## 1.1.1 — takeover do gateway SMS/WhatsApp

Quando **Assumir notificações SMS/WhatsApp do CRM** está habilitado e o Conector está configurado, o gateway `connect_api_connector` torna-se o gateway ativo do ARGWS CRM. Isso faz com que todos os gatilhos SMS nativos já existentes sejam enviados pela instância vinculada do Connect|API.

Compatibilidade preservada com o módulo anterior:

- gatilho histórico de fatura enviada: `invoice_send_to_customer2`;
- gatilho nativo de pagamento: `invoice_payment_recorded`;
- mensagens existentes não são sobrescritas;
- configurações criadas nas versões 1.0.0-1.0.7 do Connector são migradas quando o destino estiver vazio;
- se nenhuma mensagem existir, a 1.1.1 cria textos padrão para fatura enviada e pagamento registrado;
- o gateway antigo `whatsapiv2` é desativado quando o takeover está ativo;
- se o módulo antigo ainda estiver carregado, o hook `invoice_sent` do Connector não dispara em duplicidade.

Os demais gatilhos do ARGWS CRM (cobrança vencida, propostas, contratos, lembretes de equipe etc.) continuam sendo definidos pelo próprio CRM e passam automaticamente pelo Connector por ele ser o gateway ativo.
