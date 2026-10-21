# Matriz dos 26 parâmetros e contratos posteriores ao155

Conferência do catálogo `EnvironmentSettings::DEFAULTS` e consumidores atuais.
Os 26 campos primários permanecem em `options`, separados por produção/homologação
com prefixo `paymentmethod_asaas_profile_<ambiente>_`. O seletor primário único é
`paymentmethod_asaas_sandbox`. Não há herança desses valores pelas empresas nominais.

No nominal, os campos implementados155 ficam em `asaas_company_profiles`, chave
empresa + ambiente. A seleção única fica em `asaas_company_preferences` e a
origem congela o contrato em `asaas_company_origin_profiles`. Contas/credenciais
continuam em `asaas_company_accounts`. “Legado” abaixo significa consumidor
existente, não homologação financeira universal nem equivalência ao nominal.

| # | Parâmetro primário | Primário154/155 | Nominal155 |
|---|---|---|---|
| 1 | active | Catálogo nativo App_gateway | Sem flag equivalente; conta habilitada/verificada + seleção explícita são controles distintos |
| 2 | label | Nome do modo no catálogo | Implementado: label do perfil, apresentação da seleção e snapshot |
| 3 | default_selected | Seleção padrão no catálogo nativo | Não implementado; ambiente ativo único não é seleção padrão de meio |
| 4 | debug | Diagnóstico legado; payloads removidos155 | Sem flag; auditoria própria obrigatória e sanitizada |
| 5 | file_log_enabled | Log legado sanitizado155 | Sem flag; não controla auditoria nominal |
| 6 | sync_enabled | Preservado, sem consumidor operacional | Indisponível |
| 7 | sync_days | Preservado, sem consumidor operacional | Indisponível |
| 8 | sync_limit | Preservado, sem consumidor operacional | Indisponível |
| 9 | currencies | Catálogo legado; novas emissões limitadas a BRL | Contrato fixo BRL, sem preferência editável |
| 10 | description | Template legado | Implementado: description + único placeholder invoice_number, congelado antes do POST |
| 11 | interest_value | Payload/cálculo nos caminhos legados existentes | Zero obrigatório; contrato financeiro futuro |
| 12 | fine_value | Payload/cálculo nos caminhos legados existentes | Zero obrigatório; contrato financeiro futuro |
| 13 | discount_type | Desconto nos caminhos legados existentes | Sem preferência; contrato financeiro futuro |
| 14 | discount_value | Desconto nos caminhos legados existentes | Zero obrigatório; contrato financeiro futuro |
| 15 | discount_days | Prazo de desconto legado | Sem preferência; contrato financeiro futuro |
| 16 | installmentCount | Preservado/desabilitado; cartão atual à vista | À vista; parcelas exigem novo modelo de origens/recebimentos |
| 17 | diasdevencimento | Regras legadas de vencimento | Vencimento da fatura, sem prorrogação configurável |
| 18 | billet_only | Apresentação/seleção legado; emissão comum pode associar Pix | Implementado como allow_boleto para nova emissão nominal, sem alterar origem anterior |
| 19 | card_only | Apresentação/seleção legado | Implementado como allow_card; checkout hospedado integral à vista |
| 20 | pix_only | Apresentação/seleção legado | Implementado como allow_pix para nova emissão nominal |
| 21 | delete_charge | Política de remoção legado na origem | Flag não implementada; existe ação nominal explícita de exclusão, ACL/prova próprias |
| 22 | update_charge | Atualização legado na origem | Sem atualização financeira configurável; contrato futuro |
| 23 | disable_charge_notification | Cliente Asaas: true quando1, agora coerente também no Pix | Sem flag; cadastro nominal mantém notificationDisabled=true fixo |
| 24 | send_customer_email | Todos os caminhos de cliente e pesquisa respeitam flag155 | Sem flag; pagador nominal mínimo não envia email |
| 25 | send_customer_mobile | Todos os caminhos de cliente e pesquisa respeitam flag155 | Sem flag; pagador nominal mínimo não envia telefone |
| 26 | ultima_atualizacao_servicos_municipais | Informativo preservado/desabilitado | Indisponível; não há recurso nominal correspondente |

A seleção por empresa/ambiente e os cinco valores nominais155 resolvem somente um
subconjunto. “Todos os parâmetros” continua um pedido aberto. Recursos inexistentes
não devem receber campos salváveis sem consumidor. URLs API e webhook/credenciais
pertencem aos contratos154 por conta/ambiente, não aos 26 parâmetros operacionais.

## Próximos contratos mínimos para revisão (não implementados)

1. **Juros, multa, desconto e prazo**: definir tipos/unidades, base de cálculo,
   arredondamento em centavos, instante/fuso de vencimento e validade do desconto.
   Persistir snapshot financeiro versionado por origem, separado de principal,
   acréscimos, descontos, tarifas e líquido. Alterar prova canônica e escritor
   financeiro juntos; o contrato atual recusa valor/encargos divergentes.
   Aceite: exemplos aprovados no vencimento, antes/depois do prazo, centavos-limite,
   duplicidade de evento, A/B × ambientes, rollback e liquidação/refund coerentes.
   A simples inclusão dos objetos no payload não satisfaz esse contrato.
2. **Atualização/prorrogação**: intenção nominal explícita com ACL e revisões,
   snapshot anterior/proposto, bloqueios por estado financeiro e confirmação GET
   antes/depois. Uma tentativa de PUT; timeout exige consulta, nunca repetição
   automática. Preservar trilha de versões, origem e intenção incerta. Aceite:
   concorrência com recebimento/webhook/edição, negativa de troca de pagador/conta,
   estados terminais, falha parcial e efeitos de notificação claramente apresentados.
3. **Parcelamento**: relação um parcelamento → várias cobranças/origens filhas;
   soma exata, arredondamento residual, cada ID remoto com conta/ambiente, vencimento,
   recebimento, tarifa, crédito e reembolso próprios. Aceite: criação parcial ou
   incerta, parcelas fora de ordem, duplicidade, última parcela com diferença de
   centavos, cancelamento/reembolso parcial e proibição de baixa integral antecipada.
4. **Preferências de contato nominais e sincronização**: somente após definir
   finalidade dos dados, permissão, consumidor real e resultado esperado. Mudar
   preferência não deve atualizar clientes remotos existentes silenciosamente.
   Sincronização precisa cursor/checkpoint, limites e tratamento de erro/replay;
   não se reaproveitam três campos atualmente inoperantes como prova de recurso.

## Referências primárias conferidas

A documentação da [criação de cobrança](https://docs.asaas.com/reference/criar-nova-cobranca)
explica que parcelamento pode gerar várias cobranças e distribuir diferença de
arredondamento na última; a resposta inicial não representa todo o recebimento.
Também distingue omitir configurações de encargos de enviá-las explicitamente.

A documentação da [atualização](https://docs.asaas.com/reference/atualizar-cobranca-existente)
restringe operações por estado, mantém o pagador vinculado e alerta que mudanças
em valor/vencimento podem gerar eventos e notificações. Os aceites acima são
proposta de engenharia do CRM, não afirmação de funcionalidade já entregue.
