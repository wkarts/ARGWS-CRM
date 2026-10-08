# Operação do ARGWS CRM

## PHP tradicional

A aplicação preserva a instalação em PHP 8.1 ou superior com MySQL/MariaDB e extensões `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`. O instalador continua em `install/`. Antes de atualizar, faça backup do banco, de `application/config/app-config.php` e de `uploads/`. Aplique o ZIP incremental, mantenha os caminhos existentes de uploads e execute a migration informada no painel administrativo.

## FrankenPHP pelo GHCR

O Compose usa uma imagem ARGWS CRM genérica, o banco externo e volumes persistentes para configurações e dados de cada instalação. O volume `installation_config` guarda `application/config/app-config.php` fora da imagem; o entrypoint cria o modelo inicial e o instalador grava nele os dados locais. Volumes separados persistem uploads, arquivos temporários, logs, cache e os diretórios de upload dos recursos distribuídos.

1. Configure a rede para que o container alcance o servidor MySQL/MariaDB.
2. Ajuste `ARGWS_VERSION` e, se necessário, `ARGWS_HTTP_PORT` em `compose.yaml`.
3. Execute `docker compose pull` e `docker compose up -d`.
4. Acesse `http://localhost:8080` (ou a URL publicada pelo proxy HTTPS) e conclua a instalação pelo instalador.
5. Para atualizar, escolha uma tag ARGWS publicada, faça backup do banco e dos volumes, execute `docker compose pull` e `docker compose up -d` e aplique a migration no painel.

A porta interna é `8080`; configure TLS no proxy reverso. O Caddy bloqueia acesso HTTP direto a `application/`, `system/`, `temp/`, ao dump `install/database.sql`, a arquivos `.env` e a scripts PHP dentro dos diretórios de recursos. O processo web é executado como `www-data`.

## Suporte e idioma

Configure o widget na seção **Configurações → Plataforma ARGWS**. O endereço HTTPS, o token público e as opções de exibição são guardados em `tbloptions`, com alteração restrita a administradores. O script é renderizado somente na área de clientes e recebe apenas endereço, token público e opções necessárias. Não são usadas variáveis `HUB_BASE_URL` ou `HUB_TOKEN` no `.env`. O único idioma habilitado é Português do Brasil; a política de terminologia define termos traduzidos, termos mantidos, traduções aprovadas e contexto.

## Privacidade

Logs operacionais, auditoria e dados de negócio continuam locais. Validação remota de licença, envio de chave/identificador da instalação e métricas de telemetria do SDK Stripe estão desativados. A lista opcional de bloqueio de IPs, agentes e referenciadores usa somente arquivos de cache locais e não consulta mais um serviço externo. Pixels de abertura e registro de cliques em e-mails não geram eventos novos; registros históricos permanecem no banco. Condições de automação que dependam de abertura/clique ficam sem novos eventos e precisam ser revistas em fluxos já salvos. Integrações de negócio configuradas pelo cliente, como cobrança Asaas/Stripe e webhooks, continuam enviando as chamadas necessárias à operação que o cliente iniciou.
