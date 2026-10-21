# Administração Asaas e cartão hospedado

## Escopo desta entrega

A tela existente `admin/asaas` administra recebedores locais e metadados de eventos. O formulário de meios de pagamento Asaas passa a ter guias Autenticação, Segurança, Pagamentos, Webhook, Recursos e Diagnóstico. Não há cadastro remoto automático de webhook nesta entrega.

- Ambiente é uma seleção única: Homologação [sandbox] ou Produção. O campo persistido continua `paymentmethod_asaas_sandbox` (`1`/`0`), preservando o contrato existente. `debug` e `file_log_enabled` são diagnóstico, independentes do ambiente.
- O servidor aceita apenas os valores textuais exatos `0` e `1`; configuração legada ausente/malformada exige revisão. A UI usa metadados estritos do servidor, pois os radios genéricos antigos usam comparação frouxa. Não se escolhe Produção por inferência.
- A configuração global existente atende a instalação legada. Recebedores têm conta e ambiente explícitos e verificados; emitentes positivos continuam bloqueados até o isolamento operacional completo. Trocar a seleção global não troca a origem imutável de uma cobrança emitida.
- As guias reorganizam os mesmos campos, sem duplicação de nomes ou mudança de identificadores. Campos futuros não reconhecidos ficam em Recursos. Sem JavaScript, o formulário básico continua disponível; a validação do servidor permanece obrigatória.

## URL, token e permissões

O endereço é composto exclusivamente a partir de `APP_BASE_URL` HTTPS, `index_page` configurado e `public_id` público do recebedor. Não utiliza Host, X-Forwarded-Host ou URL livre enviada pelo navegador. Endereço readonly e botão de copiar não comprovam disponibilidade pública do endpoint nem cadastro no Asaas.

Cadastro local, regeneração, desativação e revisão de eventos exigem administrador, POST e CSRF próprio da sessão, mesmo se o CSRF global estiver desabilitado. O controller verifica a permissão antes de carregar o gateway. A tela de consulta não precisa descriptografar chave da API.

GERAR/REGERAR usa CSPRNG no servidor; o backend não aceita token arbitrário. A resposta protegida mostra o novo token em campo readonly, inicialmente mascarado, com ações explícitas de revelar/copiar. Apenas o hash persiste. Não há token em querystring, flash ou sessão; um GET posterior não o recupera. A resposta possui `Cache-Control: no-store` e `Referrer-Policy: no-referrer`. O CSRF é renovado depois da geração para impedir reenvio acidental do mesmo POST.

**Regenerar invalida imediatamente o token anterior no CRM.** O administrador precisa atualizar o valor no painel Asaas. Não há sobreposição/grace period nesta versão; planeje a janela operacional. Nada regenera token legado automaticamente. A opção legada continua disponível internamente para autenticação existente, mas foi removida do formulário genérico para não revelar o segredo salvo.

Cadastro remoto é exibido como **Não verificado**. A UI separa habilitação local, último evento autenticado, rejeições de entrada/autenticação, falhas/revisão de processamento e automações pendentes. Evento autenticado não comprova pagamento. Eventos legados só podem voltar ao CRON após conciliação explícita da origem e coincidência exata de conta/ambiente. A ação administrativa não dá baixa financeira.

## Cartão: capacidade efetivamente implementada

Cartão de crédito à vista abre o `invoiceUrl` retornado pelo Asaas para uma cobrança `CREDIT_CARD`. O CRM não pede, recebe como contrato válido, persiste ou repassa PAN, validade, CVV, titular ou token reutilizável de cartão. Campos dos formulários antigos são recusados antes de chamar o gateway.

O POST do checkout exige CSRF próprio por fatura, restrições de acesso da fatura, gateway ativo, cartão habilitado, modo Asaas permitido, estado elegível, escopo permitido e moeda BRL no gateway. Não há emissão por GET e nenhuma chamada a `addPayment` no checkout.

- Sem origem: a criação verifica conta e cliente, reserva a origem e só retorna o link após a associação persistida. Não cria quando há mapa legado pendente ou recebimentos locais. Timeout de emissão leva a revisão; não se repete POST cegamente.
- Origem emitida: consulta canônica no ambiente/conta originais, sem atualizar, excluir ou duplicar a cobrança. `CREDIT_CARD` ou `UNDEFINED` canônico permite o link; `BOLETO`/PIX exige revisão e futura troca assistida de origem. Não se presume que um boleto aceite cartão.
- O link precisa ser HTTPS, em host Asaas específico do ambiente, rota `/i/…`, sem credenciais, porta, query ou fragmento. Mudança de formato pelo provedor falha de forma conservadora e exige revisão. Retorna-se apenas ID, billingType e URL; campos de cartão que apareçam numa resposta remota não são propagados.
- A confirmação financeira ocorre exclusivamente pelo fluxo de webhook validado/CRON. Voltar do checkout ou receber resposta de criação não marca a fatura como paga.

**Parcelamento não está implementado nesta integração.** Valores diferentes de uma parcela são recusados. O limite legado é preservado e mostrado readonly, com motivo: falta vínculo de cada cobrança/parcela e conciliação cumulativa 1:N. Não se transforma o primeiro recebimento parcial em quitação integral.

**Assinatura com cartão salvo/tokenizado não está implementada.** Recorrência de faturas do CRM não equivale a autorização para débito automático. Os endpoints de tokenização e assinatura do provedor não são anunciados como capacidades prontas.

O fluxo antigo gravava dados de cartão na tabela legada `ac_a_ccta`. O caminho de gravação foi removido. Esta entrega não lê, copia, exporta nem apaga automaticamente registros antigos. Avaliação de existência, retenção e saneamento exige procedimento operacional sensível separado; não inclua dumps desses dados em PR, logs ou chamados.

## Módulo genérico de webhooks

O helper existente `modules/webhooks/helpers/request_webhook_helper.php::call_webhook` é voltado a envio de dados e merge fields para URLs configuradas. Pode persistir corpo de requisição/resposta em agendamento e logs. Não fornece o vínculo financeiro por conta/ambiente, autenticação específica do Asaas nem consulta canônica necessária à baixa. Não foi reutilizado como receptor financeiro e não recebe tokens/PAN/payloads financeiros deste novo fluxo. Automações de contabilidade, produtos e notificações permanecem visíveis como pendentes na outbox, separadas do registro financeiro validado.

## Fontes primárias consultadas

- [Asaas: cobranças via cartão de crédito](https://docs.asaas.com/docs/cobrancas-via-cartao-de-credito): checkout hospedado sem enviar dados do cartão, invoiceUrl, tokenização e condições de parcelamento.
- [Asaas: criar cobrança com cartão de crédito](https://docs.asaas.com/reference/criar-cobranca-com-cartao-de-credito): CREDIT_CARD/UNDEFINED, campos de parcelamento e diferença entre criação da cobrança e confirmação.
- [Asaas: assinaturas](https://docs.asaas.com/docs/assinaturas): cobranças independentes por ciclo, diferentes de um parcelamento.
- [Asaas: webhooks](https://docs.asaas.com/docs/about-webhooks): recepção de eventos e autenticação do webhook.

Estas referências descrevem o provedor; não comprovam que toda capacidade esteja implementada no CRM. Nenhuma credencial real, emissão, cobrança, rotação ou alteração de webhook remoto foi executada nos testes.

## Verificação reproduzível

PHP 8.1/8.5: `tests/asaas_webhook_admin.php`, `tests/asaas_hosted_card.php`, `tests/asaas_hosted_card_gateway.php`, `tests/asaas_settings_policy.php`. O teste do gateway usa implementações reais com transporte em memória e inclui reuso da origem, boleto bloqueado, BRL, timeout incerto e ausência de baixa direta.

Node: `node tests/asaas_settings_tabs.test.js`. Para smoke DOM real: `CHROMIUM_BIN=/caminho/do/chromium node tests/asaas_settings_tabs.test.js`; abre somente fixture local temporária, sem credenciais ou navegação externa. O ambiente local restrito não permitiu iniciar Chromium (sockets/launcher); o teste DOM deve ser confirmado no CI com navegador disponível.
