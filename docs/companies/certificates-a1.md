# Certificado A1 por empresa

Esta etapa administra PFX/P12 no contexto da empresa ativa. Não emite NF-e, não
assina documentos, não consulta provedores e não expõe download de material privado.

## Implantação e integração

A nova migration funcional do núcleo deve carregar
`services/issuers/IssuerCertificateSchema.php` e chamar
`IssuerCertificateSchema::ensure($this->db, db_prefix())`. O helper é aditivo e
idempotente; não modifica migrations históricas, não importa arquivos nem cria
chave do aplicativo. Na integração desta entrega, o coordenador escolhe o próximo passo nativo novo
conforme o HEAD efetivo; um marker já publicado no checkpoint não deve ser
reescrito. A implantação real não foi autorizada.

Depois do fechamento do formulário de edição de empresa, incluir
`APPPATH.'views/admin/issuers/certificate_link.php'` nas views
`admin/issuers/manage.php` e `admin/companies/registry.php`. O fragmento espera
`selected` e `can_manage`; só liga a gestão A1 diretamente quando a empresa
editada coincide com o contexto ativo. Caso contrário, direciona ao seletor.
O fragmento fica separado para preservar as alterações simultâneas do wizard.

A rota `admin/company_certificates/index/{issuer_id}` está no limite isolado de
CompanyRequestScope, inclusive para a empresa principal. O ID opcional da URL deve
coincidir com a empresa ativa. Não há fallback para opções ou certificados globais.

Requisitos: extensão OpenSSL, mbstring e chave de cifragem já configurada no CRM.
Não habilitar providers legados globalmente para aceitar arquivos antigos. O limite
é 2 MiB; os limites PHP/proxy de multipart devem permitir essa carga com overhead,
sem elevar o limite efetivo do aplicativo. Erros do OpenSSL nunca são apresentados.

## Segurança e semântica

- `settings/edit` é a capacidade administrativa contextual existente usada no
  cadastro de empresas. Administrador global sem essa autorização não ganha acesso.
- CSRF próprio continua obrigatório mesmo se o CSRF global estiver desativado;
  `_company_context` é conferido pelo limite existente e novamente no controller.
- O serviço usa a transação existente e o lock global de autorização, além dos
  locks da empresa, funcionário, vínculos e credencial. Isso serializa revogações
  com substituições; cada formulário também carrega a revisão otimista. O parse
  PKCS#12 ocorre após autorização e antes de adquirir esses locks, evitando reter
  o lock global durante KDF de arquivo não confiável. Sob lock, ACL, revisão,
  documento atual e validade temporal são verificados novamente. O limite de
  tamanho não limita as iterações KDF do PFX: um arquivo caro ainda pode consumir
  CPU do próprio worker. Limites de recursos/rate limit da infraestrutura são
  hardening operacional; esta etapa não afirma possuir sandbox de parsing.
- PKCS#12 precisa conter chave privada utilizável e certificado correspondente.
  Ausência de CNPJ no SAN otherName OID 2.16.76.1.3.3 produz `unverified`; o CN não
  serve como fonte de CNPJ. O formato canônico alfanumérico já aceito pelo CRM
  conserva suas letras. Divergência com o documento canônico bloqueia upload.
- A validade temporal é conferida no upload. O leitor mostra vencimento posterior
  e alteração posterior do documento da empresa. Confiança ICP-Brasil, OCSP/CRL e
  habilitação NF-e não são afirmadas nem consultadas.
- A cifra autenticada existente de CI (AES-CBC + HMAC por padrão) protege envelopes
  que incluem finalidade, empresa, revisão, fingerprint e hash dos metadados.
  Não há callback de descriptografia ou método público que exporte plaintext.
- `issuer_certificate_credentials` guarda apenas ciphers, metadados permitidos e
  revisão. `issuer_certificate_audit` registra empresa, ator, ação, revisão,
  fingerprint, resultado e data. Query debugging e query saving são desativados
  pela fronteira IssuerStorage/IssuerTransaction durante operações.
- Remoção apaga os ciphers da linha ativa e conserva tombstone da revisão e auditoria.
  Não revoga o certificado na AC e não apaga retroativamente backups.
- Integridade não confirmada não revela metadados suspeitos, mas mantém a opção de
  substituição/remoção por administrador atual.
- Esta API reduz exposição acidental; não isola um módulo PHP malicioso que já
  controla o mesmo processo, banco ou chave do aplicativo.

## Testes reproduzíveis

Somente fixtures sintéticas, criadas durante o teste; nenhum PFX/chave real é
versionado ou enviado a terceiros. A chave fixa dos testes é pública, sintética e
não pode ser usada em produção.

```sh
php tests/certificates/parser.php
php tests/certificates/manager.php
php tests/certificates/controller.php
# MySQL descartável, banco explicitamente chamado issuer_a1_tests:
A1_TEST_DSN='mysql:host=127.0.0.1;dbname=issuer_a1_tests' \
 A1_TEST_USER=root A1_TEST_PASSWORD=synthetic-test-password \
 php tests/certificates/manager.php
A1_TEST_DSN='mysql:host=127.0.0.1;dbname=issuer_a1_tests' \
 A1_TEST_USER=root A1_TEST_PASSWORD=synthetic-test-password \
 php tests/certificates/mysql_concurrency.php
```

Os testes de parser exercitam chave ausente/divergente, senha incorreta, arquivo
inválido, excesso de tamanho, CNPJ igual/divergente/ausente/inválido, validade e
cipher/envelope adulterado. Os de serviço exercitam duas empresas, replay do
schema, ACL, revogação, falha da auditoria com rollback, revisões, remoção e deriva
do schema. O harness de controller adapta I/O do framework e testa métodos HTTP,
CSRF, contexto obsoleto, ACL, no-store e ausência de segredo em erros/GET.
SQLite não prova MySQL/InnoDB, sockets HTTP reais ou execução no navegador.
`tests/companies_certificates_browser.cjs` deve ser chamado após
`tests/companies_onboarding_browser.cjs` no runner nativo descartável. Ele usa o
controller e multipart reais, cobre duas empresas/revisões/contexto/senha/remoção
e salva screenshots somente de estados sem senha. Não foi executado neste
ambiente, que bloqueia sockets/MySQL/Chromium.
O cenário MySQL comprova disputa de revisão e revogação enquanto um writer espera.

## Rollback

Desabilitar acesso à rota até resolver problemas; não rebaixar o marcador nativo.
A evolução é aditiva: manter as tabelas privadas durante rollback de código.
Excluir tabelas destruiria credenciais e auditoria, exigindo plano e autorização
específicos. Conferir backups cifrados e política de retenção operacional.

## Referências

- https://www.php.net/openssl-x509-check-private-key
- https://www.php.net/manual/en/function.openssl-pkcs12-read.php
- https://www.gov.br/iti/pt-br/assuntos/legislacao/documentos-principais/IN2020_15_DOCICP_04.01.pdf
