# Connect|API por empresa: aviso privado de fatura

Este incremento adapta o contrato existente do conector nativo Connect|API 1.1.2 sem chamar seus hooks globais. É uma saída WhatsApp, não um gateway SMS. O App_sms nativo só admite connect_api_connector; Twilio, Clickatell e MSG91 históricos continuam desabilitados. Nenhuma opção global, chave de gerenciador ou segredo da empresa principal é herdado.

## Perfil explícito e configuração

O perfil compatível implementado é `evolution-v2-flat-instance-v1`, fixo em CompanyConnectApi::PROTOCOL e incluído no fingerprint. As fontes primárias oficiais, commit e SHA-256 estão em connect-api-protocol-sources.json. Isto identifica o contrato implementado, não prova que um servidor Connect|API instalado tenha uma determinada versão Evolution. Servidores com wrappers históricos, LID sem telefone ou shape desconhecido falham fechados. Nenhuma versão é inferida pelo nome do host.

O operador do servidor cadastra uma allowlist HTTPS confiável em app-config.php, fora das opções editáveis por empresa:

```php
define('COMPANY_CONNECT_API_ENDPOINTS', [
    'approved' => ['label' => 'Servidor aprovado', 'url' => 'https://connect.example.invalid'],
]);
// Opt-in independente. O padrão é desativado.
define('COMPANY_CONNECT_API_WORKER_ENABLED', false);
```

As empresas selecionam somente o identificador da allowlist. Não há URL livre, Host/request como autoridade, redirect ou TLS desativado. O token informado deve ser específico da instância, sem chave administrativa. O contrato remoto não oferece introspecção do alcance de uma chave: a aplicação restringe todas as operações ao nome/identidade próprios; validar o privilégio efetivo da chave no provisionamento continua necessário. Tokens são cifrados pela instalação em envelope com origem/servidor/nome/hash da URL. A GUI exibe apenas presença do segredo, UUID e últimos quatro dígitos do proprietário; senha vazia preserva o segredo apenas no mesmo servidor/nome. Alterar a URL da allowlist invalida o envelope anterior.

## Identidade e autorização

A tela `/admin/company_connect_api` exige contexto nativo selado, settings.view e connect-api-connector.view. Configurar/conferir/revogar exige settings.edit e configure. Avisar exige settings.view, send, invoices.view/edit e customers.view. O configurador que protege a credencial também precisa continuar autorizado no momento de usar o perfil. Não existe elevação por admin ou ID1.

Conferir um perfil faz GET connectionState e GET fetchInstances?instanceName fora da transação; revalida origem/ator/revisão entre as chamadas e antes de persistir. O segundo retorno deve ser uma lista de exatamente uma instância com name exato, UUIDv4 id, connectionStatus=open e ownerJid telefônico @s.whatsapp.net. O sufixo de dispositivo :N é normalizado estritamente para o mesmo número. Outros formatos são recusados. Apenas os campos conhecidos UUID/proprietário são retidos; a resposta completa, que pode conter outros segredos remotos, nunca é persistida ou exposta.

UUID e proprietário são imutáveis durante a revisão do perfil e entram no fingerprint da fila. Antes de cada POST ambos os GETs são repetidos, com revalidação local após cada rede. Se a identidade mudou, uma transação grava status review, incrementa a revisão e audita sem substituir a identidade anterior; só depois a operação retorna erro. Conferir novamente não reativa review. O operador precisa salvar explicitamente e conferir um novo perfil. Intenções antigas preservam seu fingerprint, nunca são reatadas nem reclassificadas. A API remota não possui uma precondição atômica de UUID/proprietário no sendText: uma troca entre o último GET e o POST não pode ser eliminada por esta aplicação; não se afirma atomicidade entre sistemas.

## Contato, documento e fila

A GUI permite editar somente contacts.phonenumber (varchar100 nativo) de contato ativo de cliente privado pertencente à empresa da fatura. customers.edit também é exigido. O número precisa conter + e de dois a quinze dígitos internacionais; espaços/pontuação conhecidos são normalizados, sem adivinhar país/DDD. O editor não concede permissão de faturas, não reativa contato nem muda identidade/propriedade. Revisão inclui os campos nativos e epoch da auditoria para impedir ABA (trocar e voltar ao telefone antigo). O contato precisa manter permissão nativa 1 de faturas no envio.

Apenas faturas próprias emitidas (status1..4) de cliente privado ativo são admitidas. Shared clients e faturas NULL legadas não são adotados. O leitor fiscal canônico valida a identidade/snapshot, moeda e origem existentes. O novo digest `company-connect-invoice-v1` normaliza somente tipos SQL conhecidos do novo guard de notificação para ser estável entre mysqli/PDO; não modifica o hash fiscal, documento ou writer histórico.

O texto é fixo: há um documento no portal autenticado da empresa e a URL de login daquela empresa. Não contém valor financeiro, PDF público, bearer, token de acesso ou anexo. A fila guarda corpo cifrado com origem+request_key no envelope, hash do payload e fingerprints do documento/contato/conta. Mesmo request_key e mesmo conteúdo retornam a intenção já criada; conteúdo alterado conflita. A mensagem não muda os saldos ou registros financeiros.

| Estado local | Significado e retomada |
| --- | --- |
| pending | Intenção autorizada, ainda sem POST; revalida tudo ao executar. |
| submitting | Claim durável antes da rede; outro worker não faz novo POST. Crash pode permanecer sem confirmação, não reenviar automaticamente. |
| accepted | Uma resposta PENDING com key.fromMe=true, remoteJid do destinatário e id válido foi associada; não comprova entrega ou leitura. |
| uncertain | Houve tentativa ou falha após claim sem confirmação canônica; retry retorna o mesmo estado sem novo POST. |
| blocked | Worker vetou a fonte antes do claim, sem custo de envio. Não reativa automaticamente. |

Estado/status adverso, resposta desconhecida ou timeout não viram sucesso. Um resultado remoto pode existir apesar de falha/revogação local após o POST; a aplicação não promete exactly-once externo. Repetir a mesma chave não duplica o POST. A conciliação remota de resultados incertos e recibos de entrega/leitura ainda não integram este perfil; verificar no administrador do provedor antes de criar uma nova intenção.

A execução manual é explícita; o worker sessionless usa origem/ator persistidos e não aceita contexto interativo. O hook CompanyConnectApiCron::run só executa se a constante opt-in for true, carrega o cipher nativo e processa até20. Não reaproveita triggers globais SMS nem faz envio automático na emissão da fatura.

## Banco, integração e reversão

CompanyConnectApiSchema::ensure cria/valida exatamente duas tabelas InnoDB: company_connect_api_accounts e company_connect_api_messages. Accounts tem PK origem, UNIQUE endpoint_id+instance_name, FK registry e identidade remota. Messages tem PK request_key, índice origem/data e FK registry+accounts+invoice; todos os campos são NOT NULL sem defaults SQL. Collations ASCII/UTF8 são explícitas. Idempotência/schema/constraints são verificados; a migration nativa do próximo núcleo é responsabilidade da composição, sem alterar histórico. Limpeza somente de fixtures prefixadas: messages antes de accounts, ambas antes de invoices/registry. Não apagar filas/contas de produção em rollback.

Reverter código ou desligar o opt-in interrompe novos consumos; revogar o perfil limpa o segredo local e impede intenções pendentes. Preserve tabelas/auditoria/recibos para investigação, sem refazer envios. Chaves remotas devem ser revogadas no próprio provedor se necessário: revoke local não é uma revogação remota.

## Evidência e limites

SQLite e MariaDB10.11 reais exercitam fontes canônicas, A/B, NULL, segredo cifrado, substituição de envelope, telefone, auditoria/rollback, retry, revogação, troca de identidade e worker. MariaDB adicional usa sessões independentes, waiters reais no lock, concorrência do editor/fila/claim e pausa da rede durante revogação em state/identity/POST. Chromium/MariaDB exercitam GUI A/B/principal, editor de telefone real, perfis, máscaras, fila+worker,409 e triggers SQL503 com rollback/retry. Apenas a última fronteira HTTP do provedor é simulada; não há credenciais reais, WhatsApp/SMS/SMTP externo nem homologação bancária. O protótipo mantém metadados4.2.8: estes testes não substituem instalação/upgrade/tracking da próxima versão nativa composta ou MySQL8 no CI.

Ainda são código não entregue por este perfil: criação/provisionamento de instâncias, QR/pairing/restart próprios por empresa, triggers/templates automáticos, outros documentos, mídia, entrada e conciliação/recibos de entrega. Essas lacunas não são classificadas como falta de credenciais. A compatibilidade do servidor real e a entrega efetiva exigem validação externa separada e autorizada.
