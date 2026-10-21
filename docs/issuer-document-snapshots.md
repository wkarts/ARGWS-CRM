# Snapshots documentais e numeração por emitente

## Entrega e fronteira de ativação

O módulo Emitentes 1.0.4 acrescenta snapshots imutáveis de identidade e numeração documental, com numeração por empresa e série. **Não libera emissão ou operação financeira multiempresa.** O factory de produção e os helpers core chamam o gate operacional, que continua recusando todo emitente positivo. As capabilities `create_invoices`, `view_invoices` e `render_invoices` precisam ser concedidas por uma futura política operacional; a fundação atual não as concede.

Esta entrega depende do contrato core `invoices.issuer_id` nullable para os consumidores positivos. O serviço exige que a fatura já tenha o mesmo `issuer_id`; não associa faturas existentes, não altera número/identidade global e não converte dados legados. `NULL` continua sendo legado e preserva os helpers anteriores. O helper consulta a fatura persistida, mesmo recebendo um objeto, para evitar que omitir ou forjar `issuer_id` reative um fallback global.

## Contrato do serviço

`InvoiceIssuerDocuments($db, $prefix, $actor, callable $authorize)` exige autorização explícita. O callback recebe emitente, ator e capability e precisa retornar exatamente `true`. Não há callback permissivo padrão. No código de produção, utilizar exclusivamente `issuers_invoice_documents()`, que resolve o ator da sessão e aplica gate + ACL por empresa. O serviço puro permite testes isolados; instanciá-lo com um callback permissivo não é um mecanismo de ativação suportado.

- `allocate($invoiceId, $issuerId, $series = 'INV')`: confere InnoDB em todos os participantes via metadados (lock de coordenação, registry, invoices, snapshots, sequências, auditoria, clients e vínculos cadastrais do módulo) e exige `create_invoices`; adquire primeiro a mesma trava de coordenação do `IssuerRegistry`, depois bloqueia emitente, fatura, destinatário/vínculo e sequência na mesma transação; confere propriedade, perfil e autorização novamente sob lock. Cria o snapshot, avança a sequência e registra somente IDs/evento na auditoria de forma atômica.
- `forPdf($invoiceId, $issuerId)`: exige `render_invoices` e `view_invoices`, verifica storage InnoDB dos participantes e lê fatura/snapshot/itens/tributos/moeda por IDs dentro de uma transação. Retorna DTO imutável com campos permitidos; o renderer não aceita um array arbitrário nem objeto de fatura enviado por um caller.
- `get($invoiceId, $issuerId)`: exige `view_invoices`, confere a propriedade persistida e valida o snapshot. Não escreve nem consome números. Snapshot ausente, corrompido ou de outra empresa é erro, nunca retorno para opções globais.
- Repetir `allocate` para a mesma fatura/empresa/série retorna o snapshot existente sem número extra. Pedir outra série falha com `issuer_document_immutable`.
- Ordem fixa de locks: coordenação do módulo → emitente → fatura → snapshot → destinatário/vínculo (só na primeira emissão) → sequência. A trava inicial é a mesma de `IssuerRegistry`, serializando adoção, compartilhamento/revogação e alocação sem inverter a ordem. A captura do DTO também usa essa trava enquanto valida permissões. É uma escolha conservadora de throughput nesta etapa; empresas/séries mantêm contadores independentes, mas suas alocações não são paralelas.
- Número é string decimal, com incremento decimal sem conversão para `float` ou inteiro de plataforma. A sequência possui limite BIGINT UNSIGNED; alcançar o último valor disponível bloqueia a alocação para não produzir overflow em `next_number`.
- Formato imutável: `prefix + series + '-' + number`, com zero-padding explícito. A série aceita somente ASCII maiúsculo, dígitos, `_` e `-`, de 1 a 32 caracteres. Mudança posterior de prefixo/padding/razão social não renumera documentos já emitidos.
- Nenhum endpoint de emissão, concessão de permissão financeira, alteração de cadastro por POST ou configuração de credenciais é introduzido.

## Perfil explícito e dados preservados

A empresa nasce com `settings_json = {}`. Para preparar um perfil documental futuro, `settings_json.document_profile` precisa conter explicitamente:

```json
{
  "address": "Rua sintética, 10",
  "city": "Cidade sintética",
  "state": "SP",
  "postcode": "00000-000",
  "country_code": "BR",
  "phone": "",
  "email": "",
  "prefix": "EMPRESA-",
  "padding": 6,
  "logo_inline": "",
  "terms": "Condições próprias em texto simples."
}
```

Razão social e CPF/CNPJ vêm do registro da própria empresa. Todos os campos têm limites e validação UTF-8; o documento tributário usa a política de CPF/CNPJ do módulo. Campos opcionais usam string vazia explícita. Chaves inesperadas no perfil são recusadas para evitar captura acidental de segredos ou configuração não prevista. Não existe preenchimento por opções globais, empresa padrão da sessão, cliente compartilhado ou outra empresa.

Logo aceita somente data URI PNG/JPEG com base64 estrito, bytes e MIME coerentes, até 512 KiB, dimensões até 4096 px e até 16 milhões de pixels. URLs, caminhos, SVG e conteúdo que apenas declara ser imagem são recusados. Imagens inline são suportadas pela versão de TCPDF já incluída no repositório. Nome, endereço e termos são escapados antes da renderização; os termos próprios são texto simples.

O destinatário é capturado explicitamente na primeira alocação em `recipient_identity_json`: `client_id`, nome e CPF/CNPJ normalizado (ou vazio quando não informado). Exige cliente ativo, adoção cadastral no módulo e vínculo explícito com o emitente, todos sob lock; o CPF/CNPJ vivo precisa coincidir com a identidade canônica registrada. Não adota nem compartilha automaticamente um cliente. Depois da alocação, alterações do nome/documento cadastral ou revogação do compartilhamento não mudam nem removem o histórico da própria fatura. Trocar o `clientid` da fatura é recusado como divergência de escopo, inclusive em retries. Novo documento sem vínculo válido falha sem consumir número.

O snapshot contém dados identificadores da empresa e do destinatário, incluindo CPF/CNPJ e informações de contato da empresa. Deve receber a mesma proteção de acesso e retenção das faturas. Não registrar o JSON completo em logs; auditoria contém somente evento, ator, empresa e ID da fatura.

## Banco, instalação e atualização

DDL aditivo separado em `IssuerSchema::documentStatements()`:

- `issuers_sequences`: PK `(issuer_id, series)`, `next_number BIGINT UNSIGNED`, FK para registro de emitentes.
- `issuers_invoice_documents`: PK `invoice_id`, FK para `invoices.id`, FK para emitente, unicidade `(issuer_id, series, number)`, identidade legal do emitente e identidade mínima do destinatário em JSON separados, número formatado e data.

Requer a base essencial de módulo 1.0.2/migration102 da PR38. A instalação nativa executa 101 (schema base), 102 (contrato essencial), 103 (permissão de integrações) e 104 (documentos) sequencialmente por `App_module_migration`; o install não executa DDL avulso e confirma o tracking final. Atualização documental 1.0.3 → 1.0.4 utiliza `migrations/104_version_104.php`, repetível com `CREATE TABLE IF NOT EXISTS`. Não há backfill nem cópia de configurações ou alteração dos números core. DDL MySQL faz commit implícito e pode deixar instalação parcial; corrigir a causa e repetir, sem apagar tabelas já existentes. Instalações legadas precisam manter a tabela de faturas InnoDB e o tipo `id INT` compatível com o schema original para criação da FK.

O vínculo restritivo com a fatura impede exclusão física de uma fatura que possua snapshot. Isso protege a identidade/número emitidos; não há cascata destrutiva. Uma futura política de cancelamento/retificação deve preservar o registro histórico. Não excluir snapshots ou decrementar sequências como mecanismo de cancelamento.

## Helpers e PDF

`application/helpers/invoice_issuer_helper.php` é carregado pelo core independentemente da ativação do módulo. Para emitente positivo, módulo ausente/desativado, gate bloqueado, falta de autorização ou snapshot inválido falham antes de produzir documento/número. Para `NULL`, organização, logo, termos, autor e formato seguem o comportamento legado.

O dispatcher `invoice_pdf()` resolve o emitente persistido antes de escolher o renderer:

- Legado `NULL`: conserva `Invoice_pdf`, App_pdf, tema, customizações, hooks e regras existentes. O `App_pdf` original não foi alterado no resultado final da série de commits.
- Emitente positivo: exige autorização dedicada e utiliza exclusivamente `InvoiceIssuerDocumentData` → `Issuer_invoice_pdf` → template fixo `application/views/pdf/issuer_invoicepdf.php`. O gate operacional da produção continua fechado, mesmo com esses componentes prontos/testáveis isoladamente.
- `Invoice_pdf` e o template legado têm guard próprio `invoice_issuer_assert_legacy_pdf_scope()`: mesmo uma futura ativação financeira não permite que o caminho global renderize uma fatura positiva por engano.
- O renderer dedicado deriva diretamente de TCPDF. Não consulta `get_instance()`, `get_option()`, `hooks()`, helpers de cliente/itens/valores, classes PDF customizadas, path filters, tema ou `my_invoicepdf.php`. Identidade, autor, número, contatos, logo e termos vêm do snapshot da própria empresa.
- PDF básico completo em PT-BR: identidade histórica do destinatário e endereço da própria fatura, entrega quando explicitamente ativa, itens selecionados, alíquotas/nomes de tributos, subtotal, tributos totais, desconto, ajuste, total, datas, situação, observações, termos e paginação. A4, fonte e layout são defaults do renderer; não herda identidade/configuração global da empresa.
- Nenhum modo, link, instrução de pagamento, assinatura, credencial, campo personalizado, nota administrativa ou token é incluído. Valores e moeda vêm da fatura persistida; não são buscados por empresa padrão ou ambiente global.
- DTO confere pertencimento de item/tributo, destinatário e moeda; datas válidas; limites de conteúdo; subtotal a partir de quantidade × preço e identidade aritmética do total. Cálculos de valores usam centavos inteiros de 64 bits, sem aritmética de dinheiro em ponto flutuante. Reconcilia arredondamento por linha ou agregado. `total_tax` e alíquotas são registros da fatura, não uma validação fiscal/reapuração legal de tributos.
- Conteúdo rico dos campos textuais é tratado como texto literal escapado. Não interpreta HTML, imagens remotas, substituições de períodos ou macros nas descrições/notas. O único elemento de imagem aceito é o logo inline validado no snapshot. Total de conteúdo do DTO limitado a 4 MiB e até 1.000 itens.
- DTO possui construtor privado, nasce somente de serviço com autorização explícita e não aceita serialização/deserialização. `data()` retorna uma cópia da estrutura. Repetir `prepare()` não acrescenta páginas duplicadas.

Não é um documento fiscal eletrônico, não autoriza cobrança e não substitui isolamento do portal, dos relatórios, dos jobs ou dos serviços financeiros. Perfil/identidade do emitente e identidade mínima do destinatário são históricos. Endereços de cobrança/entrega, valores, itens, datas, situação e observações são uma captura consistente da própria fatura no momento da renderização; esta entrega não afirma imutabilidade integral do conteúdo financeiro. Não lê dados vivos do cadastro de clientes para renderizar. A fatura deve preservar seu `clientid`; remoção/reassociação desse identificador não é suportada para documento emitido.

## Testes e evidência

Implementados:

1. `tests/issuers_documents.php`: schema real, numeração independente, idempotência, escopo, autorização obrigatória/rechecagem, perfil explícito, histórico após mudança do emitente/destinatário e revogação de vínculo, troca indevida de clientid, erro de armazenamento/rollback sem consumir número, corrupção, constraints e limites de imagens/números.
2. `tests/issuers_document_helpers.php`: compatibilidade legada, gate com módulo ativo/desativado, objetos adulterados, ausência de snapshot/registro, escape HTML, metadados e métodos PDF sem identidade/assinatura/payment modes globais. Também cobre o bloqueio do renderer legado para positivos, sem driblar o gate real.
3. `tests/issuers_document_render.php`: DTO autorizado, escopo de cliente/itens/moeda, integridade de datas/valores, imutabilidade, conteúdo escapado e geração real TCPDF, incluindo logo inline e paginação de quatro páginas. Os acessos a contexto/opções/hooks globais são substituídos por funções que falham no teste.
4. `tests/issuers_document_concurrency.php`: quatro corridas reais de dois processos MySQL, para retry da mesma fatura, duas faturas da mesma série, duas empresas e alocação concorrente com revogação de compartilhamento pelo `IssuerRegistry`. A última aceita apenas captura anterior à revogação (histórico preservado) ou bloqueio sem consumir sequência. Requer banco descartável explicitamente configurado, falha com código 2 quando não há DSN MySQL.
5. `tests/issuers_document_migration.php`: executor real `App_module_migration`, upgrade 103→104, versão rastreada, replay/no-op, falha DDL mantém versão pendente e retry, histórico/contador/core preservados e downgrade destrutivo recusado.
6. Workflow `issuer-foundation.yml`: inclui os novos testes e os caminhos de integração PDF/helpers em PHP 8.1 e 8.5 com MySQL 8.0 sintético.

Execução local (PHP com PDO SQLite):

```sh
php tests/issuers_document_migration.php
php tests/issuers_documents.php
php tests/issuers_document_helpers.php
php tests/issuers_document_render.php
php tests/issuers_registry.php
php tests/issuers_controller.php
```

Para concorrência, configurar exclusivamente um MySQL descartável com `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER`, `ISSUERS_TEST_PASSWORD` e executar `php tests/issuers_document_concurrency.php`. O teste cria/remove somente seu prefixo aleatório `test_docs_race_*`. Nunca usar DSN de produção.

Evidência inicial local: PHP 8.5.10 via PHP-WASM, 75 verificações de serviço SQLite, 105 verificações cumulativas de helpers/guards legados, 116 cumulativas de DTO/renderer e 14 de migração nativa aprovadas. PDF de uma página e PDF de quatro páginas com logo inline foram gerados de fato; texto/metadados foram extraídos com Poppler e a renderização de exemplo foi inspecionada visualmente. O autor/número são do snapshot e o PDF não contém JavaScript. A suíte não prova emissão operacional end-to-end no CRM: o gate deve continuar bloqueado. Concorrência/DDL MySQL e integração autenticada completa não foram executadas localmente; confirmar CI e homologação antes de qualquer rollout.

## Homologação, publicação e rollback

Somente após autorização de uma implantação futura: backup verificado, revisão da migração em banco descartável, instalação/atualização do módulo e testes de regressão legada. Não preencher `issuer_id` em faturas reais nesta entrega. Confirmar que novos emitentes têm perfil vazio e não ganham sequência/identidade automaticamente; numeração/PDF positivos devem permanecer bloqueados.

Rollback conservador: manter tabelas, sequências e snapshots; desativar a funcionalidade mantendo os guards core que recusam emitentes positivos. **Não reverter cegamente para código anterior ao guard caso existam faturas com `issuer_id` positivo**, pois ele pode tratar dados positivos como legados. Não remover FKs, truncar snapshots, reutilizar números nem apagar auditoria. Não há uninstall destrutivo.

Publicação remota, merge, ativação, deploy e emissão/cobrança real ficam fora desta entrega local.

## Fronteira transacional própria

`allocate` e `readForPdf` recusam transação externa CI3/PDO e estado MySQL autocommit desabilitado ou COMMIT encadeado. O teste de isolamento anterior ao BEGIN também recusa uma transação SQL externa não rastreada pelo contador CI. Só a transação própria pode ser revertida; o retorno exige commit confirmado e transação encerrada. O modo de erro do driver é sempre restaurado. `issuers_document_transactions.php` cobre falhas, nesting e falsos commits com métodos reais CI3 e PDO. Em SQLite as sondagens de sessão MySQL são simulações declaradas; o mesmo teste deve executar contra o MySQL isolado do CI antes de afirmar essa evidência.

A migration103 isolada introduz a concessão explícita `manage_integrations`, default negado, sem conceder capacidades de criação, visualização ou renderização financeira.
