# Operação do ARGWS CRM

## PHP tradicional

A aplicação preserva a instalação em PHP 8.1 ou superior com MySQL/MariaDB e extensões `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`. O instalador continua em `install/`. Antes de atualizar, faça backup do banco, de `application/config/app-config.php` e de `uploads/`. Aplique o ZIP incremental, mantenha os caminhos existentes de uploads e execute a migration informada no painel administrativo.

## FrankenPHP pelo GHCR

A instalação PHP tradicional mantém o instalador web em `install/`. A imagem FrankenPHP não contém o instalador legado `install/`; ela oferece o assistente web `/setup` somente enquanto não há configuração persistente. As stacks oficiais por ambiente usam um banco MySQL/MariaDB persistente e guardam a configuração da instalação em `./storage/installation_config`, caminho do host relativo à pasta da stack. O valor `ARGWS_CONFIG_DIR=/var/lib/argws-crm/config` indica o mesmo diretório **dentro do container web**; o Compose liga os dois por um bind mount. O banco usa `./storage/database_data` no host.

1. Gere os arquivos de produção com o binário `argws-crm-deployer`, autentique no GHCR se os packages estiverem privados e valide o Compose conforme [deploy/README.md](../deploy/README.md). O deployer cria uma chave aleatória `ARGWS_SETUP_TOKEN` no `.env`.
2. Execute `docker compose --env-file .env -f compose.yaml up -d` e configure o CloudPanel para encaminhar HTTPS à porta local vinculada a `127.0.0.1`.
3. Abra o domínio; a raiz redireciona automaticamente para `/setup`. Informe a chave gerada no `.env`, URL pública, nome, e-mail e senha do primeiro administrador. O assistente importa o schema somente se o banco estiver vazio, cria a conta e aplica todas as migrations antes de liberar o CRM. Ao terminar, encaminha à tela de acesso; `/setup` e `/install` ficam bloqueados. Não é necessário provisionar pelo terminal nem atualizar o banco manualmente após a criação do administrador.
4. Depois de confirmar o acesso, remova `ARGWS_SETUP_TOKEN` do `.env` e recrie somente o serviço web para eliminá-lo também dos metadados do container: `docker compose --env-file .env -f compose.yaml up -d --force-recreate web`.
5. Para atualizar, faça backup do banco e volumes, preserve `.env`, execute `pull` e `up -d` e aplique a migration pelo painel. Não use `docker compose down -v`.

O processo web continua executando como `www-data`. O entrypoint prepara os diretórios dos bind mounts no mesmo container e não depende de um serviço auxiliar de inicialização. Caddy bloqueia `/install`, `application/`, `system/`, `temp/`, arquivos `.env` e scripts PHP dentro de diretórios de recursos. O provisionador, o schema e os helpers ficam fora da raiz da aplicação. A imagem inclui somente um handler próprio e mínimo para o primeiro acesso; os formulários e handlers do instalador legado não são distribuídos no container. O provisionamento é exclusivo para uma base vazia e não sobrescreve dados de instalações existentes.

### Compose com banco externo

O `compose.yaml` da raiz permanece para uso avançado com MySQL/MariaDB externo; crie um `.env` local a partir de `container.env.example`, preencha `ARGWS_DB_HOST`, `ARGWS_DB_NAME`, `ARGWS_DB_USER` e `ARGWS_DB_PASSWORD`, e defina `ARGWS_SETUP_TOKEN` com `openssl rand -hex 32` antes do primeiro acesso. Para a implantação recomendada com banco interno e deployer, use as stacks em `deploy/`.

## Suporte e idioma

Configure o widget na seção **Configurações → Plataforma ARGWS**. O endereço HTTPS, o token público e as opções de exibição são guardados em `tbloptions`, com alteração restrita a administradores. O script é renderizado somente na área de clientes e recebe apenas endereço, token público e opções necessárias. Não são usadas variáveis `HUB_BASE_URL` ou `HUB_TOKEN` no `.env`. O único idioma habilitado é Português do Brasil; a política de terminologia define termos traduzidos, termos mantidos, traduções aprovadas e contexto.

## Marca da instalação

Os arquivos em `assets/images/argws/` são a marca padrão da aplicação. Após entrar como administrador, acesse **Configurações → Geral** e envie o logo para superfícies claras, a variante adicional e o favicon. Arquivos enviados são armazenados em `uploads/company/` e têm prioridade sobre os arquivos padrão. Ao remover um arquivo personalizado, o padrão volta a aparecer. Em Docker, preserve o volume `${ARGWS_STORAGE_ROOT:-./storage}/uploads` entre atualizações; ele contém a marca e os demais arquivos da instalação. A tela `/setup` exibe a marca ARGWS antes de existir uma instalação configurada.

## Privacidade

Logs operacionais, auditoria e dados de negócio continuam locais. Validação remota de licença, envio de chave/identificador da instalação e métricas de telemetria do SDK Stripe estão desativados. A lista opcional de bloqueio de IPs, agentes e referenciadores usa somente arquivos de cache locais e não consulta mais um serviço externo. Pixels de abertura e registro de cliques em e-mails não geram eventos novos; registros históricos permanecem no banco. Condições de automação que dependam de abertura/clique ficam sem novos eventos e precisam ser revistas em fluxos já salvos. Integrações de negócio configuradas pelo cliente, como cobrança Asaas/Stripe e webhooks, continuam enviando as chamadas necessárias à operação que o cliente iniciou.
