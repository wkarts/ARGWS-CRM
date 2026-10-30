# Ponte: fatura local v2 (módulo 1.0.3 / migration 103)

## Branch
`feature/pige-bridge-473`, dependente da composição core 4.7.3/473. O integrador mantém a migration core real; o scaffold local inicial não é entrega.

## Descrição
Implementação nova, não recuperação dos bytes perdidos. Não substitui nem reinterpreta recibos v1. Criar fatura local **não** significa emitir boleto/Pix, receber pagamento ou produzir efeito acadêmico. As etapas externas continuam fechadas até adapter e controles próprios.

## Contrato
HMAC temporal canônico existente, segredo próprio por conexão e escopo autenticado `{school_id,issuer_id,account_id,environment}`.
- `POST /pige_bridge/api/v2/invoices`: schema_version=2, event_type=`invoice.requested`, event_id UUID; data exato `{charge_id,client_id,amount_cents,currency,due_date,description}`. Cliente existente varia por solicitação; CRM exige ativo e propriedade/vínculo explícito ao issuer. Nunca cria cliente.
- `POST /pige_bridge/api/v2/invoices/status`: schema_version=2, event_type=`invoice.status.requested`, novo event_id; data exato `{charge_id}`. Apenas SELECTs/metadados/ACL, sem transação que grave lock, sem provider, enqueue, audit ou baixa. GET não executa serviços.
- Ambos retornam200: `{schema_version:2,event_id,charge_id,request_id,scope,status,invoice_id,capabilities:{issue_charge:false},payment_effect_applied:false,academic_effect_applied:false}`; status=`pending` com invoice_id=null ou `invoice_created` com ID inteiro. Event_id ecoa a operação atual. Ausência404; conflito409; indisponibilidade503. Respostas nunca incluem SQL, token ou ciphertext.

## Autoridade e recuperação
Master `APP_PIGE_BRIDGE_ENABLED === true`, módulo ativo e credencial protegida são necessários. Criar exige ainda `APP_PIGE_BRIDGE_INVOICES_ENABLED === true` e mapping explícito enabled/actor/moeda BRL na UI existente. Todos defaults desligados. Capability diagnóstica é só informativa; writer revalida sob lock.

Migration103 aditiva guarda origem imutável, hash do envelope, escopo, data de emissão São Paulo, mapping/revision e hash da credencial (não segredo). Unicidade por conexão+charge e conexão+evento. Cliente inválido é recusado antes da reserva. Repetição com mesmo evento/dados/configuração devolve a mesma fatura. Alterar valores, cliente ou evento para a mesma cobrança conflita. A data de emissão permanece congelada na reserva.

`CompanyInvoices::create` é o único writer. Seu originGuard revalida conexão/token/ACL/mapping e origem dentro da transação nativa. O recibo canônico `company_invoice_requests` é a fonte do status, inclusive se a resposta HTTP se perder depois do commit; não depende de atualizar um status paralelo. Falha antes do commit permite POST explícito idempotente; consulta de pending nunca tenta emissão. Vínculo de empresa não vem da sessão.

**Limites intencionais:** rotação ou alteração da revisão de consentimento impede retomar uma origem pending antiga; não migra, reatribui nem emite automaticamente. Consulta histórica continua possível com credencial atual e ACL original vigente. Retomada pós-rotação exige fluxo explícito de novo consentimento ainda não implementado. Texto da fatura é deliberadamente genérico (“Cobrança escolar”): descrição de origem permanece no journal para idempotência, sem copiar dados acadêmicos arbitrários ao documento financeiro. Status não interpreta pagamentos, estornos, juros, descontos ou efeitos acadêmicos.

## Evidências e pendências
Novos testes estáticos Python verificam quatro invariantes; não executam PHP. Fixtures PHP de protocolo/controller, runner de migrations e MySQL nativo cobrem A/B, cliente externo, idempotência, falha do writer, retry, status zero escrita e revogação. CI PHP8.1/8.5 + MySQL8.4 foi ampliado. PHP/MySQL/HTTP real **não executados localmente**; publicação depende da autorização específica e CI verde antes de qualquer aceitação operacional. Nenhuma chamada financeira real.
