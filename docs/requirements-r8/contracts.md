# R10 / R11 — Contratos: informações e rascunhos

## Bases e limite desta composição

Referência funcional inspecionada: tag `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`. Destino identificado antes das alterações: `main` 4.8.8, commit `3cd2ac577a969840c94b019f602cb69d710c0e78`. A indicação 4.8.7 do registro não foi usada para substituir a base efetiva. Esta composição pertence à PR para `main`; não altera a frente 4.0.3.

O trabalho abaixo estende os contratos próprios da empresa ativa. Conserva as rotas e a numeração canônica já existentes, o estado de rascunho, a ativação com snapshot, a imutabilidade após emissão e os contratos históricos do principal. Não atribui contratos legados com propriedade indefinida a outra empresa.

A listagem original, suas colunas, filtros e resumo já haviam sido adaptados no conjunto R8 anterior. Esta composição acrescenta campos reais ao formulário de criação/edição de rascunho e à projeção da coluna Projeto. A equivalência visual completa do detalhe de Contratos permanece pendente: o editor rico, a organização integral das abas e os templates da referência não estão implementados por este conjunto. Isso é trabalho de adaptação ainda não concluído, não uma decisão que o usuário precise repetir.

## Inventário comparado e comportamento entregue

A referência `application/views/admin/contracts/contract.php` contém a área de informações com cliente, projeto, assunto, valor, tipo, início, término opcional, descrição e campos personalizados. Em contratos existentes, contém também as áreas de conteúdo, anexos, comentários, renovações, tarefas, notas, templates e rastreamento de e-mail. O conteúdo contratual é distinto da descrição administrativa. `application/views/themes/perfex/views/contracthtml.php` e `application/views/themes/perfex/views/contractpdf.php` usam o conteúdo do contrato; não tornam automaticamente públicos a descrição, o projeto ou todos os campos personalizados.

| Recurso real | Adaptação deste conjunto | Evidência / limite |
| --- | --- | --- |
| Cliente | Seletor original com busca, cliente atual preservado fora da primeira página e troca autorizada enquanto rascunho | Leitura pela empresa ativa e `customers.view`; edição de cliente exige o novo formulário, não altera o contrato dos payloads antigos |
| Projeto | Seleção pelo cliente; referência tipada `company_project`, ID, revisão e título capturado | Mesma empresa e cliente, `projects.view`, confirmação dentro da transação; sem converter ID canônico em FK legada |
| Assunto | Controle obrigatório do formulário de informações | Persistido no campo canônico `name`, com o limite já existente |
| Valor / moeda | Valor opcional, moeda explícita do catálogo atual | Vazio permanece ausência, não zero; o novo formulário pode limpar um valor do rascunho de forma explícita |
| Tipo | Catálogo próprio atual e criação em modal junto ao seletor | Mantém a autorização administrativa do catálogo e o recibo durável; não adota tipos globais por inferência |
| Início / término | Início obrigatório e término realmente opcional | `due_date` passa a admitir SQL `NULL` mediante migration aditiva; valores antigos não são regravados |
| Descrição | Campo administrativo separado do conteúdo | Armazenamento privado, ausente do DTO público, portal e PDF |
| Campos personalizados | Os dez tipos reais: texto, número, textarea, select, multiselect, checkbox, data, data/hora, cor e link | `render_custom_fields` e `fields_helper` reais; opções literais, valores múltiplos, revisão das definições e data de exibição preservados |
| Conteúdo inicial | Atalho de texto já existente mantido; criação pode salvar primeiro as informações | Ativação ainda exige conteúdo não vazio. O editor HTML/merge fields da 4.0.2 continua pendente |
| Editar rascunho | Formulário completo com os valores atuais e revisão otimista | Atualização atômica do registro e dos campos privados; bloqueia rascunho obsoleto e documento emitido |
| Cópia / renovação | Metadados administrativos e campos personalizados preservados | Projeto revalidado e capturado na revisão atual para o novo rascunho; origem emitida permanece inalterada |
| Coluna Projeto / busca | Relação exata do contrato próprio ao projeto capturado | Não infere vínculo a partir de todos os contratos de um mesmo cliente |
| Privacidade dos campos | Permissões atuais e privacidade capturada são aplicadas conjuntamente | Tornar uma definição pública depois não publica automaticamente um valor capturado como privado; inclui busca da tabela |

O container e a ordem dos campos de informações usam a largura e a estrutura funcional da referência. As classes de marca, cabeçalho, scripts e apresentação continuam na base recente. Esta implementação não alterou manifest, service worker, ícones ou configuração do PWA. A ausência de alterações nesses arquivos não substitui a homologação visual/PWA exigida por AC06 e AC07.

## Armazenamento e compatibilidade

`CompanyContractMetadataSchema::ensure($db, $prefix)` é um helper exclusivo da migration 489. Ele torna `company_contracts.due_date` anulável e cria `company_contract_metadata`, com chave composta `(issuer_id, contract_id)` e FK composta para o contrato próprio. O helper confirma o schema resultante e compara um fingerprint paginado dos registros de contratos antes/depois. Não executa DDL em controller, leitor ou writer. Na fixture SQLite, o DDL evoluído é preparado explicitamente; a alteração MySQL real deve executar em CI.

A tabela privada contém revisão, JSON versionado, hash de integridade, indicação de congelamento e data de atualização. Seu envelope é:

```text
version: 1
description: texto administrativo
project: null ou {source_kind, source_id, source_revision, name}
custom_fields: [{id, field_source_kind, source_field_id, definition, value}]
```

O input opcional usa `native_metadata.version=1`. Requisições antigas que não trazem esse envelope conservam a normalização e o hash de payload anteriores. O comando `clear_value` pertence somente ao input versionado: ele torna explícita a remoção do valor de um rascunho e não é armazenado em nenhum snapshot. A ausência de valor nos payloads antigos continua significando preservação do valor existente.

**O snapshot público não recebe descrição, projeto, campos personalizados nem um hash desses dados privados.** `CompanyContracts::get()` mantém as mesmas chaves. API, portal e preparação de e-mail continuam usando o registro público. `getForAdmin()` acrescenta os dados privados somente depois das verificações administrativas. O PDF continua baseado no snapshot público; o único ajuste de apresentação necessário foi omitir a parte final da vigência quando não existe término.

Não há migração retroativa de conteúdo privado para contratos já emitidos. No primeiro uso do novo formulário em um rascunho próprio antigo, projeções de campos personalizados existentes, inclusive privadas/inativas, são preservadas. Essa preservação não revela os valores ao operador que não possui a permissão correspondente.

## Autoridade, transação e recibos

O writer canônico mantém uma prova privada de curta duração, vinculada à instância, banco, prefixo, empresa, ator, contrato, revisão, campos esperados e fase da escrita. `NativeCompanyCustomFields::saveContractMetadataInOwned()` aceita a escrita somente enquanto essa prova estiver ativa. Abrir outra transação válida não permite fabricar a prova. O frame é limpo em `finally`.

A ativação congela apenas a tabela privada, dentro da mesma transação que numera e preserva o contrato público. O leitor confirma o JSON, seu hash, o vínculo ao evento de auditoria e a igualdade das projeções de campos personalizados. Editar um documento emitido pelo endpoint genérico de campos personalizados também é recusado; a edição compatível ocorre no formulário de rascunho.

A revisão independente reproduziu uma falha adicional: um trigger após a inserção do recibo podia alterar os metadados depois da primeira confirmação e antes do commit. O writer foi corrigido com uma confirmação final localizada em `CompanyContractsStorage` / `CompanyContracts`. Depois do último audit/receipt, ele confirma novamente o registro esperado, os metadados e suas projeções. Cópia e renovação confirmam também a origem preservada. O hook padrão de Tickets é vazio; não houve alteração no modelo de tickets.

Os testes de triggers comprovam rollback integral em criação, edição, ativação, cópia e renovação. O frame de confirmação também é limpo em `finally`, permitindo retry normal após remover a falha. Replays válidos continuam retornando o resultado já confirmado, inclusive quando o rascunho foi ativado depois do primeiro POST.

## Transporte e contexto de empresa

Rotas de escrita continuam sendo as rotas reais de Contratos. O formulário usa `contracts/contract`, com ID canônico para edição, revisão, nonce, CSRF próprio e contexto capturado. A adaptação não aceita `issuer_id` como alternativa ao contexto autorizado.

O único callback novo é:

```text
GET / HEAD /admin/contracts/client_change_data
    client_id=<cliente autorizado>
    contract_id=<company_contract canônico opcional>
```

Sem `contract_id`, exige `contracts.view`, `contracts.create` e `customers.view`. Com ID, exige `contracts.view`, `contracts.edit`, `customers.view` e um rascunho próprio. Projetos só são retornados com `projects.view`, para o cliente e a empresa indicados pelo contexto. O callback confirma novamente dados e permissões antes de responder, não retorna dados administrativos do contrato e recusa POST. Na principal, essa rota nova também usa o adapter próprio, sem fallback global.

Datas e campos de data transportam a convenção de exibição do GET. Uma mudança posterior de formato regional não troca dia e mês no POST. O formulário também transporta o tipo de conversão de seus campos de data; a revisão/hash das definições continua sendo confirmada no writer. O frontend descarta respostas antigas após trocar cliente/empresa e impede salvar enquanto a consulta atual estiver pendente ou não confirmada.

`assets/js/native-admin-contracts.js` é carregado somente na página `contracts/contract`. O helper browser `native_contract_driver.cjs` reconhece o seletor Bootstrap e aguarda a hidratação real do callback antes de prosseguir; ele atualiza as seleções existentes de contratos em sete suites, sem alterar suas operações de suporte, arquivos, portal ou PDF.

## Verificação deste conjunto

| Verificação executada localmente | Resultado | Alcance |
| --- | --- | --- |
| `tests/companies/native_contract_metadata.php` | **37 adicionais / 94 cumulativos PASS, SQLite** | Writer real, compatibilidade histórica, campos privados, término NULL, hash/replay, edição, cópia, renovação, projeções, PDF TCPDF real extraído com `pdftotext`, tabela/busca |
| `tests/companies/native_contract_commit_guard.php` | **11 adicionais / 105 cumulativos PASS, SQLite** | Triggers SQL reais após recibo; rollback em cinco fluxos; confirmação da origem; retry limpo. Reexecutado por revisão independente |
| `tests/companies/native_contract_form.php` | **24 adicionais / 81 cumulativos PASS, SQLite** | Controller real, schema e writer reais, helpers de campos reais, DOM e controles enviados, dez tipos de CF, lookup, ACL e verbos |
| `tests/companies/native_contract_index.php` | **27 adicionais / 84 cumulativos PASS, SQLite** | Regressão da listagem, união principal/legado, filtros e colunas anteriores |
| `tests/native_company/contract_form.test.cjs` | **5 PASS, Node** | Concorrência do callback, cancelamento, resposta tardia, troca de empresa e falha de transporte |

Os testes de formulário usam doubles somente para shell/framework/contexto, com a fronteira declarada nos arquivos. O parser DOM local é HTML4; scripts de template são retirados da entrada do parser porque não são controles enviados. O preenchimento do hidden do widget de hyperlink a partir de seu `data-value` é uma fronteira explícita; não constitui execução JavaScript do navegador. O cenário browser deve comprovar essa interação separadamente.

Lint PHP passou nos 19 arquivos PHP envolvidos; `node --check` passou no asset, teste Node, helper browser e sete suites browser (dez arquivos). `git diff --check` não apresentou erros.

`tests/companies/contracts_browser.cjs` foi ampliado para selecionar projeto, criar tipo no modal, persistir descrição privada, criar término NULL, copiar os campos e limpar o valor opcional. Ele preserva seus cenários anteriores de principal/A/B, colisão de ID, PDF, replay/revisão, cópia e cancelamento. As demais suites de contratos passaram a aguardar o callback do seletor. Esses scripts receberam verificação de sintaxe local; **não foram executados localmente com Chromium**. MySQL e browser ficam para os gates remotos da composição. Não há afirmação de homologação visual, entrega real de e-mail ou isolamento integral de produção.

## Pendências distinguíveis de conflitos reais

**Implementação compatível ainda pendente:** editor rico/merge fields do conteúdo, organização integral das abas do detalhe original, templates, tarefas e rastreamento de e-mail da referência. Recursos que já existiam no adapter recente — comentários, anexos privados e externos, notas, publicação explícita, envio autenticado, renovação, assinatura declarada e histórico — foram preservados. A ausência de uma aba original adaptada não autoriza declarar equivalência completa.

**Conflito real preservado:** a referência permite editar contratos em situações nas quais o destino já preserva um documento emitido. Não se removeu essa imutabilidade nem se alterou conteúdo emitido. A marcação manual de assinatura continua distinta de assinatura eletrônica do cliente. A exclusão atual conserva tombstone/histórico; não foi transformada no checkbox reversível de lixeira da referência. Publicação para contatos continua explícita e sujeita às permissões do portal, sem mapear um checkbox de informações a publicação automática.

**Validação pendente:** comparação lado a lado na mesma tela/dados, todos os perfis/empresas da instalação, MySQL real da nova migration e execução browser desta composição. Os resultados locais cobrem os cenários descritos, não encerram AC50–AC55 ou AC57–AC64 de toda a aplicação.
