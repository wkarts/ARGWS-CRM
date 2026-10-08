# Implantação do ARGWS CRM

Este diretório contém as stacks oficiais por ambiente e fica fora dos ZIPs da aplicação PHP. Ele é distribuído em um asset separado da release: `ARGWS-CRM-deploy-X.Y.Z.zip` para releases estáveis e `ARGWS-CRM-deploy-develop.zip` para o canal develop. O ZIP inclui a pasta `deploy/`, os Compose e os `.env.example`, sem `.env` com segredos. O binário argws-crm-deployer também incorpora os modelos e gera, na máquina do operador, `compose.yaml` e `.env`.

## Deployer portátil

Baixe argws-crm-deployer-linux-x64 para CloudPanel/Linux x64 ou argws-crm-deployer-win-x64.exe para Windows na release estável ou na pré-release argws-crm-develop. O executável Linux usa musl; ambos são binários nativos e não exigem Rust, Node.js ou Python no servidor.

    ./argws-crm-deployer-linux-x64 generate --environment develop --database mysql --output ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe generate --environment develop --database mysql --output .\argws-crm-develop
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-develop

    ./argws-crm-deployer-linux-x64 generate --environment production --database mysql --output ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe generate --environment production --database mysql --output .\argws-crm-production
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-production

O comando generate preserva um .env existente. --force pode substituir compose.yaml, nunca o .env. O arquivo .env novo usa segredos aleatórios e permissão 0600 em sistemas Unix; mantenha-o fora do Git, da imagem e dos ZIPs.

## Instalar e atualizar

1. Se os packages GHCR forem privados, autentique Docker no host com uma credencial local que tenha read:packages.
2. Na pasta gerada, valide com docker compose --env-file .env -f compose.yaml config.
3. Faça pull com docker compose --env-file .env -f compose.yaml pull.
4. Inicie com docker compose --env-file .env -f compose.yaml up -d.
5. Acesse o terminal do servidor e execute `docker compose --env-file .env -f compose.yaml exec web php /opt/argws-crm-provisioner/provision.php`. O comando importa o schema somente em um banco vazio e solicita URL, nome, e-mail e senha do primeiro administrador; a senha não aparece na tela.
6. Reinicie o serviço web: `docker compose --env-file .env -f compose.yaml restart web`. Antes disso, o endereço HTTP responde 503 com instruções e não mostra um instalador web.
7. Configure o CloudPanel para encaminhar o domínio HTTPS à porta local configurada, vinculada a 127.0.0.1.
8. Em atualizações, faça backup do banco e dos volumes, preserve .env e execute pull e up -d --remove-orphans. Não use docker compose down -v. O provisionador recusa bancos com tabelas existentes e instalações já configuradas.

A configuração da instalação fica no volume installation_config. Banco, uploads, cache e logs têm volumes persistentes separados. A stack Docker fica separada da instalação PHP, PHP-FPM, Nginx e dos outros projetos do CloudPanel; escolha uma porta livre.

## GHCR e dependências

As imagens são ghcr.io/wkarts/argws-crm (aplicação), ghcr.io/wkarts/argws-crm-base:1-php8.3-bookworm (FrankenPHP), ghcr.io/wkarts/argws-crm-mysql:8.0 e ghcr.io/wkarts/argws-crm-mariadb:11.4. O sincronizador copia multiarch e preserva tags existentes; somente uma execução manual com refresh_existing=true atualiza tags já espelhadas. A agenda semanal verifica imagens sem substituí-las.

A aplicação atual declara MySQL/MariaDB. PostgreSQL e Redis não são dependências configuradas ou testadas pelo CRM e não são adicionados à stack. O database não publica porta para o host. Os Compose ficam somente no repositório e não no ZIP de aplicação.


## Primeiro provisionamento Docker

A imagem GHCR não contém o diretório web `install/`. O primeiro provisionamento usa o schema da mesma versão e o hash de senha compatível com a aplicação, mas roda somente como PHP CLI dentro do container. Não existe credencial master padrão nem formulário de instalação exposto na internet. Mantenha o proxy CloudPanel apontado para o serviço durante o procedimento; a resposta 503 some após reiniciar o serviço web.

O volume `installation_config` guarda a configuração criada. Não execute o comando contra bancos que já tenham tabelas; para uma instalação existente, preserve seu banco/configuração e siga o fluxo de atualização. Se a importação de um banco novo falhar parcialmente, pare e revise os logs antes de qualquer ação; nunca remova volumes de dados existentes para tentar novamente.
