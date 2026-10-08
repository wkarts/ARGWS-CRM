# Implantação do ARGWS CRM

Este diretório contém as stacks oficiais por ambiente e fica fora dos ZIPs da aplicação. O binário argws-crm-deployer incorpora os Compose e os exemplos de ambiente para gerar, na máquina do operador, apenas compose.yaml e .env.

## Deployer portátil

Baixe argws-crm-deployer-linux-x64 para CloudPanel/Linux x64 ou argws-crm-deployer-win-x64.exe para Windows na release estável ou na pré-release argws-crm-develop. O executável Linux usa musl; ambos são binários nativos e não exigem Rust, Node.js ou Python no servidor.

    ./argws-crm-deployer-linux-x64 generate --environment develop --database mysql --output ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe generate --environment develop --database mysql --output .\argws-crm-develop
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-develop

    ./argws-crm-deployer-linux-x64 generate --environment production --version 3.4.2 --database mysql --output ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe generate --environment production --version 3.4.2 --database mysql --output .\argws-crm-production
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-production

O comando generate preserva um .env existente. --force pode substituir compose.yaml, nunca o .env. O arquivo .env novo usa segredos aleatórios e permissão 0600 em sistemas Unix; mantenha-o fora do Git, da imagem e dos ZIPs.

## Instalar e atualizar

1. Se os packages GHCR forem privados, autentique Docker no host com uma credencial local que tenha read:packages.
2. Na pasta gerada, valide com docker compose --env-file .env -f compose.yaml config.
3. Faça pull com docker compose --env-file .env -f compose.yaml pull.
4. Inicie com docker compose --env-file .env -f compose.yaml up -d.
5. No instalador ARGWS CRM, use database como host MySQL e obtenha nome, usuário e senha do .env local.
6. Configure o CloudPanel para encaminhar o domínio à porta local configurada, vinculada a 127.0.0.1.
7. Em atualizações, faça backup do banco e dos volumes, preserve .env e execute pull e up -d --remove-orphans. Não use docker compose down -v.

A configuração da instalação fica no volume installation_config. Banco, uploads, cache e logs têm volumes persistentes separados. A stack Docker fica separada da instalação PHP, PHP-FPM, Nginx e dos outros projetos do CloudPanel; escolha uma porta livre.

## GHCR e dependências

As imagens são ghcr.io/wkarts/argws-crm (aplicação), ghcr.io/wkarts/argws-crm-base:1-php8.3-bookworm (FrankenPHP), ghcr.io/wkarts/argws-crm-mysql:8.0 e ghcr.io/wkarts/argws-crm-mariadb:11.4. O sincronizador copia multiarch e preserva tags existentes; somente uma execução manual com refresh_existing=true atualiza tags já espelhadas. A agenda semanal verifica imagens sem substituí-las.

A aplicação atual declara MySQL/MariaDB. PostgreSQL e Redis não são dependências configuradas ou testadas pelo CRM e não são adicionados à stack. O database não publica porta para o host. Os Compose ficam somente no repositório e não no ZIP de aplicação.
