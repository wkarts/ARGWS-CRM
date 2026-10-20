# Google SMTP próprio para notificações de documentos

O fluxo opt-in conecta uma conta Google verificada à empresa e ao funcionário que a autorizou. Ele alimenta as filas canônicas de notificações explícitas de faturas, orçamentos e propostas. Não envia PDFs públicos, anexos ou cópias automáticas. O cliente continua usando o portal autenticado. Recuperação de acesso e outros consumidores de e-mail conservam a configuração SMTP própria já existente; os campos Google legados continuam compatíveis.

A tela `admin/company_google_mail_oauth` exige contexto selado, `settings.view` para consultar e `settings.edit` para salvar, autorizar ou revogar. O endereço `smtp_email` deve estar configurado explicitamente na empresa antes da conexão. A conta Google precisa responder `email_verified=true` e exatamente o mesmo e-mail. O `sub` verificado é mantido apenas como hash para impedir troca silenciosa de identidade na renovação.

## Configuração explícita do servidor e do Google

Defina no arquivo privado de configuração do servidor:

```php
define('COMPANY_GOOGLE_MAIL_REDIRECT_URI',
    'https://crm.example.com/admin/company_google_mail_oauth');
```

Use o caminho efetivo da instalação, inclusive subdiretório e `ADMIN_URI` configurado no servidor. O endereço precisa usar HTTPS, sem credenciais, query ou fragmento. Cadastre exatamente o mesmo retorno em um cliente OAuth **Web** no Google Cloud e configure a tela de consentimento. A aplicação não deriva autoridade de `Host`, `Forwarded`, query, opção genérica da empresa ou URL enviada pelo usuário.

Cada empresa cadastra seu Client ID e client secret. O segredo em branco preserva o valor protegido; ele nunca volta ao formulário. Não há herança de segredo da empresa principal, de outra empresa ou das opções globais. Criar o cadastro preserva inicialmente o SMTP próprio existente. Concluir o consentimento ativa Google somente para as notificações de documentos citadas. Depois da ativação, revogação ou falha de renovação bloqueia esse transporte; não retorna automaticamente ao SMTP anterior.

O Gmail SMTP exige `https://mail.google.com/`; esse escopo permite acesso amplo ao e-mail na conta Google. A autorização também solicita `openid email` para comprovar a identidade do remetente. A aprovação, verificação do aplicativo e restrições de publicação/test users no Google Cloud dependem do projeto e da conta reais. Nenhuma credencial real ou aprovação Google foi usada nesta prova.

## Callback, concorrência e renovação

O serviço persiste somente o hash do `state` aleatório e o verificador PKCE cifrado. A tentativa expira em 15 minutos e fica vinculada a empresa, funcionário, revisão e retorno HTTPS confiável. Há limite de cinco tentativas por funcionário/empresa em 15 minutos. O desafio S256 é público no URL de autorização; o verificador nunca entra no URL.

A tentativa muda de `pending` para `exchanging` e confirma o commit antes de trocar o código no endpoint fixo Google. Código de autorização é entrada transitória do protocolo e não é persistido, auditado ou devolvido. O callback responde 303 para a página limpa, inclusive quando o contexto é negado pelo middleware específico. O worker e os serviços não usam cookies ou empresa ativa para escolher a origem.

Troca do código, renovação e consulta de identidade usam HTTPS/TLS verificado, endpoints fixos, sem redirects, fora de transações. Tokens são cifrados antes de persistir. A conta, identidade, revisão, época e permissões são revalidadas depois da rede. Erro de resultado incerto fecha a tentativa; um código não é repetido automaticamente.

`epoch` representa revogação, reconexão ou troca de credenciais. `token_version` registra somente rotação automática. Uma renovação confirmada conserva `epoch` e a impressão pública da configuração, portanto conserva a compatibilidade das filas pendentes. A renovação usa reserva durável de 90 segundos: concorrentes recebem busy; reserva expirada ou resultado incerto exige nova conexão, sem repetir cegamente o refresh token.

O vínculo imutável da fila registra empresa, funcionário, espécie de origem, request key e época. Ele é criado na mesma transação do recibo canônico, fila e auditoria. Mensagem antiga sem vínculo não adota uma conexão Google nova. Recriar a conexão não reaproveita mensagens antigas: crie uma nova notificação com nova request key após conferir o documento. A exclusão nativa de uma fila remove apenas seu vínculo por FK `ON DELETE CASCADE`; o recibo do documento continua impedindo duplicação por retry.

Antes de instalar o provedor XOAUTH2 e novamente antes de liberar o token ao PHPMailer, o adaptador fechado revalida o recibo original, documento, funcionário, contato, configuração e época. Revalida também após uma renovação que cedeu execução à rede. Quando instalado, o guard tipado de ocorrência de despesa é chamado fora da transação para revalidar a agenda. Não existem callbacks arbitrários de autorização. A perda de acesso do funcionário que conectou a conta, desativação da empresa ou revogação impede o transporte. Tokens não aparecem em DTOs, cabeçalhos persistidos, estado de navegador ou fingerprint.

## Redação de logs antes do PHP

Google retorna `code` e `state` na query por contrato. Um303 do PHP não impede que proxies/servidores registrem o request original. Configure redação **antes de habilitar o retorno real**, inclusive no CDN, WAF, balanceador, proxy e APM. Não registre query, headers Authorization ou corpos dos endpoints token/SMTP.

Exemplo Nginx: logar `$uri` em vez de `$request` e `$request_uri` retira a query de todo o access log:

```nginx
log_format crm_no_query '$remote_addr - $remote_user [$time_local] '
                        '"$request_method $uri $server_protocol" $status $body_bytes_sent';
access_log /var/log/nginx/crm-access.log crm_no_query;
```

Exemplo Apache: omitir `%q` e `%r`, usando `%U` (caminho sem query):

```apache
LogFormat "%h %l %u %t \"%m %U %H\" %>s %b" crm_no_query
CustomLog logs/crm-access.log crm_no_query
```

Adapte o mesmo contrato no APM e nos logs de erros que capturam URLs. O servidor PHP de desenvolvimento usado nos testes não comprova configuração de ingresso em produção. Os códigos dos ensaios eram sintéticos.

## Migração e prova

O schema aditivo contém `company_google_mail_accounts`, `company_google_mail_states` e `company_google_mail_queue_bindings`, InnoDB com chaves/FKs e metadados validados. Não altera dados ou OAuth legados. A instalação e a migration nativa futura devem chamar `CompanyGoogleMailOAuthSchema::ensure($db, db_prefix())`; o protótipo não altera marker de versão nem simula upgrade editando `VERSION`.

Testes SQLite e MariaDB exercitam configuração A/B/principal, isolamento de segredo, PKCE, one-use, expiração, escopo/identidade inválidos, revisão obsoleta, rollback tardio, tokens cifrados, demissão, revogação durante rede, fila/recibo/vínculo atômicos e revalidação após refresh. Sessões MariaDB independentes observam dois waiters reais, uma única troca por disputa e revogação/demissão durante resposta pausada. `App_Email`, `App_mailer`, `CI_Email` e PHPMailer reais são exercitados; somente o transporte externo final é capturado. Chromium prova GUI A/B/principal, redirect303 limpo, segredo mascarado, rollback 503 por trigger executado com mysqlCLI, retry e stale 409.

Fila criada ou envio simulado não comprova entrega real, aprovação de escopo Google, reputação ou homologação de SMTP. Não há promessa de exactly-once externo: após a última validação, uma transmissão em curso pode ter resultado incerto. Revogar localmente não revoga permissões remotas compartilhadas de maneira automática; o titular pode remover a autorização na conta Google.

## Contratos primários consultados

- [Google OAuth Web Server](https://developers.google.com/identity/protocols/oauth2/web-server): autorização, state, redirect exato, offline e token/refresh POST.
- [Google Gmail XOAUTH2](https://developers.google.com/workspace/gmail/imap/xoauth2-protocol): escopo e formato SASL XOAUTH2.
- [Google OpenID Connect reference](https://developers.google.com/identity/openid-connect/reference): userinfo e `email_verified`.
- [Google Auth Library, exemplo Web PKCE](https://github.com/googleapis/google-auth-library-nodejs/blob/main/samples/oauth2-codeVerifier.js): `keys.web`, S256 e code verifier. Usado como evidência de protocolo; não adiciona dependência Node ao runtime PHP.
- [League OAuth2 Client](https://oauth2-client.thephpleague.com/usage/): preservação do verificador e renovação do refresh token. O OAuth legado League permanece intacto; o novo token provider implementa a interface PHPMailer nativa.

O pacote de evidência contém URL resolvida, data de captura e SHA256 de cada fonte primária. Não copia texto integral de documentação externa para o repositório.
