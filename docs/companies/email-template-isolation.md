# Modelos de e-mail por empresa: continuação local

Esta continuação é separada do candidate congelado `b8a5cec7` (368 arquivos).
Habilita apenas o editor nativo de modelos (`Emails::index/email_template`) no
escopo autenticado da segunda empresa. Envio, teste SMTP, recuperação pública e
ativações legadas por GET continuam bloqueados para esse contexto.

## Origem e atualização

- A atualização nativa 404 executa `IssuerCore::reconcile`: adiciona `issuer_id`
  nullable à tabela nativa de modelos e concilia os modelos das empresas existentes.
- Registros históricos permanecem NULL e pertencem exclusivamente à principal.
  Personalizações, estados habilitado/desabilitado e idiomas antigos são preservados.
- Cada nova empresa recebe os 82 modelos de fábrica em inglês e PT-BR, extraídos
  de `install/database.sql`, traduzidos pelo catálogo já existente. Não copia modelos,
  remetentes personalizados, SMTP ou outros segredos da principal.
- O cadastro e a criação dos modelos participam da mesma transação. Falha na criação
  dos modelos desfaz o novo cadastro. Falha de upgrade é propagada; DDL MySQL não é
  transacional, portanto a repetição deve completar a atualização sem remover dados.
- A conciliação não sobrescreve modelos existentes. A fábrica é gerada pelo script
  `scripts/generate-company-email-catalog.cjs`; seu conteúdo é validado contra o seed.

## Leitura e escrita

O modelo nativo `Emails_model` filtra listagem, ID direto, edição multilíngue e
ativação em lote pelo contexto da empresa. IDs de outra empresa no lote inteiro
são recusados antes da primeira escrita. Falhas de armazenamento desfazem o lote.
Fallbacks de idioma do preparador nativo usam o mesmo modelo filtrado.
`findForIssuer` atende consumidores com origem já autenticada/persistida, mantém
os fallbacks na mesma origem e não modifica o contexto interativo nem concede acesso.
Duplicidade de idioma/slug gera erro explícito; empresa inativa não é origem válida.

## Evidências e limites

Executados localmente com PHP WASM/SQLite e fixtures sintéticas:

- upgrade pelo executor de serviço real, repetição e preservação da principal;
- cadastro real no serviço, falha de templates com rollback e retry;
- catálogo EN/PT-BR próprio para A/B, sem personalização/SMTP herdados;
- seleção explícita A durante contexto B e fallback sem emprestar modelos;
- modelo nativo: listagem, leitura, edição, negação A→B e rollback multilíngue;
- preparador nativo: idioma da empresa, fallback inglês, ausência sem fallback global.

Pendente: MySQL real (metadata/DDL/transações), concorrência, HTTP/CSRF/RBAC do
editor visual, integração do portal com template/merge fields/origem persistida da
fila e transporte, instalação/upgrade em container e validação remota de CI.
O editor exige RBAC `email_templates` na empresa, sela o controller antes de
hidratar App/modelos/hooks globais, mantém o token do contexto e usa CSRF nativo.
O conteúdo HTML persistido aparece escapado em textarea, sem preview executável.
A gravação gera auditoria da empresa e não inclui assunto/corpo/segredo no log.
Também foram exercitados o controller real (I/O simulado), bootstrap real,
revogação de ACL, token obsoleto e renderização do HTML. Isso não equivale a uma
requisição HTTP real ou validação visual em navegador.

Os testes não enviam e-mails e não exercitam uma recuperação pública completa.
