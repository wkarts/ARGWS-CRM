# Operação do ARGWS CRM

## PHP tradicional

A aplicação preserva a instalação em PHP 8.1 ou superior com MySQL/MariaDB e extensões `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`. O instalador continua em `install/`. Antes de atualizar, faça backup do banco, de `application/config/app-config.php` e de `uploads/`. Aplique o ZIP incremental, mantenha os caminhos existentes de uploads e execute a migration informada no painel administrativo.

## FrankenPHP pelo GHCR

A instalação PHP tradicional mantém o instalador web em `install/`. A imagem FrankenPHP é diferente: ela não contém esse diretório nem publica uma rota de setup. As stacks oficiais por ambiente usam um banco MySQL/MariaDB persistente e guardam a configuração da instalação no volume `installation_config`.

1. Gere os arquivos de produção com o binário `argws-crm-deployer`, autentique no GHCR se os packages estiverem privados, valide o Compose e faça pull conforme [deploy/README.md](../deploy/README.md).
2. Execute `docker compose --env-file .env -f compose.yaml up -d`. O banco sobe primeiro; até o provisionamento, o serviço web responde 503 com instruções.
3. No terminal do servidor, execute `docker compose --env-file .env -f compose.yaml exec web php /opt/argws-crm-provisioner/provision.php`. O comando recusa banco com tabelas e instalação já configurada. Em execução interativa, a senha do primeiro administrador é digitada sem eco; não há conta ou senha padrão.
4. Reinicie com `docker compose --env-file .env -f compose.yaml restart web` e configure o CloudPanel para encaminhar HTTPS à porta local vinculada a `127.0.0.1`.
5. Para atualizar, faça backup do banco e volumes, preserve `.env`, execute `pull` e `up -d` e aplique a migration pelo painel. Não use `docker compose down -v`.

O processo web continua executando como `www-data`. Caddy bloqueia `/install`, `application/`, `system/`, `temp/`, arquivos `.env` e scripts PHP dentro de diretórios de recursos. O provisionador e o schema ficam fora da raiz web; somente o schema e os helpers de hash/parser compatíveis são levados à imagem, sem os formulários ou handlers do instalador. O provisionamento é exclusivo para uma base vazia e não sobrescreve dados de instalações existentes.

### Compose com banco externo

O `compose.yaml` da raiz permanece para uso avançado com MySQL/MariaDB externo; crie um `.env` local a partir de `container.env.example` e preencha `ARGWS_DB_HOST`, `ARGWS_DB_NAME`, `ARGWS_DB_USER` e `ARGWS_DB_PASSWORD`. Para a implantação recomendada com banco interno e deployer, use as stacks em `deploy/`.

## Suporte e idioma

Configure o widget na seção **Configurações → Plataforma ARGWS**. O endereço HTTPS, o token público e as opções de exibição são guardados em `tbloptions`, com alteração restrita a administradores. O script é renderizado somente na área de clientes e recebe apenas endereço, token público e opções necessárias. Não são usadas variáveis `HUB_BASE_URL` ou `HUB_TOKEN` no `.env`. O único idioma habilitado é Português do Brasil; a política de terminologia define termos traduzidos, termos mantidos, traduções aprovadas e contexto.

## Privacidade

Logs operacionais, auditoria e dados de negócio continuam locais. Validação remota de licença, envio de chave/identificador da instalação e métricas de telemetria do SDK Stripe estão desativados. A lista opcional de bloqueio de IPs, agentes e referenciadores usa somente arquivos de cache locais e não consulta mais um serviço externo. Pixels de abertura e registro de cliques em e-mails não geram eventos novos; registros históricos permanecem no banco. Condições de automação que dependam de abertura/clique ficam sem novos eventos e precisam ser revistas em fluxos já salvos. Integrações de negócio configuradas pelo cliente, como cobrança Asaas/Stripe e webhooks, continuam enviando as chamadas necessárias à operação que o cliente iniciou.
