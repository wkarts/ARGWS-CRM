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

## Verificação nativa do passo 458

`tests/certificates/native_458_mysql.php` usa App_Migration/App e os arquivos
457/458 reais da árvore composta. O baseline 456 é mínimo, com tabelas/linhas
sintéticas necessárias aos helpers de perfis Clientes e A1; não representa
replay completo de install.sql. Apenas I/O de framework e reconciliação posterior
do núcleo não relacionada ao passo 458 são adaptados. Nenhum helper funcional da
458 é substituído por stub.

Na suíte MySQL dedicada, o teste verifica os marcadores 457/458, CREATE com cache
pré-preenchido, preservação de perfil/certificado/e-mail customizado, replay,
falhas de DDL, falha/sucesso falso da escrita do marker
e erro de readback. Cada falha deve impedir o hook de conclusão; o retry precisa
concluir preservando dados anteriores. Não testa helper de e-mail ainda ausente
da migration publicada. O banco permitido é exclusivamente issuer_a1_tests,
validado pelo DSN e por SELECT DATABASE() antes de qualquer DDL.

Para smoke adicional sem sockets, pode usar `A1_NATIVE_SQLITE_SMOKE=1` e omitir o
DSN. `A1_NATIVE_SOURCE_ROOT` seleciona uma árvore composta local já existente para
leitura; não copia nem altera suas migrations. SQLite não comprova MySQL/InnoDB.
`schema_cache.php` cobre separadamente os métodos reais de cache do CI e reproduz
o falso negativo de table_exists após CREATE antes da invalidação corrigida.

## Verificação nativa até o passo 460

`tests/certificates/native_460_mysql.php` usa o mesmo adaptador de I/O de teste
(`native_upgrade_support.php`) e os arquivos reais 457, 458, 459 e 460. O teste
458 continua separado e não depende do reconciliador novo. A fixture mínima 456
contém variantes English/PT-BR de fábrica, PT-BR personalizado e terceiro idioma,
com remetente próprio, ordem, plaintext e desativação deliberados. São dados
sintéticos; não há envio de e-mail. O SQL e as transações passam pelo adaptador
PDO do teste, não pelo CI_DB_mysqli_driver completo de produção.

O teste 460 exige todos os marcadores contíguos, tradução dos valores exatos de
fábrica, preservação literal de customizações, HTML, merge fields, propriedade e
configurações de envio, incluindo active=0 na variante criada. Injeta falha na
segunda escrita do reconciliador real para exigir rollback da primeira e nenhuma
gravação do marcador 460. Falhas de tracking e readback após commit do helper
exigem retry idempotente, sem reativação, duplicação ou alteração adicional.
Também verifica que replay da 460 preserva perfis privados, certificados cifrados
e auditoria previamente gravados. A execução MySQL 8 é exigida pelo workflow;
smoke SQLite local continua explicitamente sem provar semântica InnoDB.

## Rollback

Desabilitar acesso à rota até resolver problemas; não rebaixar o marcador nativo.
A evolução é aditiva: manter as tabelas privadas durante rollback de código.
Excluir tabelas destruiria credenciais e auditoria, exigindo plano e autorização
específicos. Conferir backups cifrados e política de retenção operacional.

## Referências

- https://www.php.net/openssl-x509-check-private-key
- https://www.php.net/manual/en/function.openssl-pkcs12-read.php
- https://www.gov.br/iti/pt-br/assuntos/legislacao/documentos-principais/IN2020_15_DOCICP_04.01.pdf
