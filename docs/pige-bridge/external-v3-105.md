# Ponte v3: comandos externos explícitos (módulo 1.0.5)

## Branch
`feature/pige-bridge-473`; composição depende de core4.7.3/473 e Asaas1.5.8 para conciliação. Não é publicação/ativação.

## Descrição
Migrações104/105 aditivas: consentimento externo separado e intenções externas imutáveis. Nenhum backfill/adoption de faturas antigas, clientes ou cobranças. Primeiro adapter é **Asaas PIX/BOLETO**. BB ainda não é aceito por este contrato; sua integração exige adapter/guard separado. Não considerar a ponte integralmente homologada com este checkpoint.

## Configuração e autorização
UI nativa existente `/admin/pige_bridge/settings` ganha consentimento externo: conexão/revisão, conta Asaas cadastrada/verificada, mesmo actor local, habilitação e consentimento separado de conciliação. Wallet, issuer e ambiente devem coincidir exatamente com scope autenticado; segredo da conta continua na configuração nativa, nunca enviado no payload. Caller e actor precisam ACL da empresa; delegação exige master. Revogar não exige credencial/provedor ativo. Configuração não chama banco.

Gates de instalação estritamente booleanos, todos desligados quando ausentes:
- `APP_PIGE_BRIDGE_ENABLED`: transporte;
- `APP_PIGE_BRIDGE_INVOICES_ENABLED`: criação local v2;
- `APP_PIGE_BRIDGE_EXTERNAL_ENABLED`: emissão, observação e instrumentos;
- `APP_PIGE_BRIDGE_RECONCILE_ENABLED`: além do anterior, conciliação explícita.

Consentimento externo vincula revisão da conexão e da conta. Rotação/reconfiguração invalida operações anteriores; não transfere propriedade financeira. Retry histórico não redefine conta/pagador. Consulta continua acessível por token atual e ACL de origem sem mutação. Retomada após alteração de consentimento não é automática.

## Contrato HMAC schema3
Mesmos headers, janela temporal e body JSON canônico. Event_type é `charge.<ação>.requested`, event_id UUID, scope exato autenticado. POSTs:
| Ação | Path | data exato |
|---|---|---|
| issue | `/pige_bridge/api/v3/charges` | `charge_id`, `payer_id` inteiro local existente, `billing_type` PIX ou BOLETO |
| status | `/pige_bridge/api/v3/charges/status` | `charge_id` |
| sync | `/pige_bridge/api/v3/charges/sync` | `charge_id`, `recovery_id` (string vazia ou ID remoto explicitamente fornecido para tentativa incerta) |
| instruments | `/pige_bridge/api/v3/charges/instruments` | `charge_id` |
| reconcile | `/pige_bridge/api/v3/charges/reconcile` | `charge_id`, `proof_id` inteiro persistido por sync |

Issue exige fatura v2 já criada para charge_id. `payer_id` é registro nativo Asaas ready, do cliente daquela fatura e mesma empresa/conta, identidade e hash da prova válidos. **Nunca** cria/upserta cliente ou pagador remoto. Native service verifica wallet/cliente/cobrança canônicos antes de aceitar resultado.

Resposta200 tem exatamente: schema_version3, event_id, charge_id, request_id, scope, provider=`asaas`, status nativo (ou reserved), origin_id|null, remote_id|null, proof_id|null, reconciliation_status=`not_reconciled`|`reconciled`, payment_id|null, instrument|null, payment_effect_applied, academic_effect_applied=false. `reconciled` indica recibo canônico histórico, não autoriza efeitos acadêmicos. Instrumento retorna texto limitado: Pix billing_type/payload/expiration_date; boleto billing_type/identification_field/bar_code/nosso_numero. Nunca HTML, imagem ou URL arbitrária.

## Efeitos separados
- **Status**: somente SELECTs/metadados ACL, sem transaction helper que grave lock, HTTP, writer, fila ou audit. GETs não chamam serviços.
- **Issue**: origem/journal fixam evento/pagador/conta/empresa antes de emissão. Native issue usa originGuard sob lock e policy157 `manual_only` persistida na mesma reserva. Origem nativa existente, inclusive uncertain, nunca é re-POSTada pelo adapter.
- **Sync**: chamada explícita ao provedor e gravação de prova; observe não enfileira e a policy permanente bloqueia webhook/cron futuro para estas origens, mesmo se automação da conta mudar. Outras origens mantêm automação nativa.
- **Instruments**: consulta explícita validada antes/depois, sem prova/baixa/enqueue. Não é chamada pelo status.
- **Reconcile**: Asaas158, sem HTTP, proof_id explícito e writer/ledger canônico. ACL, origem/revision, prova latest/fresca e integral BRL são revalidadas antes/depois dentro da mesma transação. Retry devolve mesmo pagamento. Parcial, juros, desconto, estorno e divergências ficam fechados/revisão. Policy manual_only nunca é removida.

## Evidência nova e limites
Testes estruturais Python passam; inventário nativo99views/55controllers atualizado. Fixtures PHP controller/protocol e composição MySQL nativa com transport mock cobrem vínculo A/B, replay, consulta zero escrita/HTTP, sync sem pagamento/job e conciliação idempotente. Esses fixtures foram **escritos, não executados**: PHP/MySQL indisponíveis localmente. CI deve executar antes de homologação. Nenhuma API financeira real, credencial de produção, merge/deploy ou ativação. Publicação está pausada na confirmação específica informada ao coordenador.
