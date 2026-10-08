# Implantação do ARGWS CRM

Este diretório contém as stacks oficiais por ambiente e fica fora dos ZIPs da aplicação PHP. Ele é distribuído em um asset separado da release: `ARGWS-CRM-deploy-X.Y.Z.zip` para releases estáveis e `ARGWS-CRM-deploy-develop.zip` para o canal develop. O ZIP inclui a pasta `deploy/`, os Compose e os `.env.example`, sem `.env` com segredos. O binário argws-crm-deployer também incorpora os modelos e gera, na máquina do operador, `compose.yaml` e `.env`.

## Deployer portátil

Baixe argws-crm-deployer-linux-x64 para CloudPanel/Linux x64 ou argws-crm-deployer-win-x64.exe para Windows na release estável ou na pré-release argws-crm-develop. O executável Linux usa musl; ambos são binários nativos e não exigem Rust, Node.js ou Python no servidor. O comando `generate` também entrega `migrate-storage.sh` na pasta da stack.

    ./argws-crm-deployer-linux-x64 generate --environment develop --database mysql --output ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe generate --environment develop --database mysql --output .\argws-crm-develop
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-develop
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-develop

    ./argws-crm-deployer-linux-x64 generate --environment production --database mysql --output ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe generate --environment production --database mysql --output .\argws-crm-production
    ./argws-crm-deployer-linux-x64 validate --directory ./argws-crm-production
    .\argws-crm-deployer-win-x64.exe validate --directory .\argws-crm-production

O comando generate preserva os valores de um .env existente. Ao atualizar uma stack antiga cujo Compose ainda não conhecia o assistente web, acrescenta uma chave aleatória `ARGWS_SETUP_TOKEN`; em stacks novas, gera a chave junto com as senhas. Após o primeiro provisionamento, remova a chave: o Compose aceita que esteja ausente, e novas execuções do deployer preservam essa remoção quando o modelo atual já contém o campo. `--force` pode substituir `compose.yaml`, nunca as demais configurações do `.env`. O arquivo `.env` novo define `ARGWS_STORAGE_ROOT=./storage`, usa senhas de banco e chave de setup aleatórias, com permissão 0600 em sistemas Unix; mantenha-o fora do Git, da imagem e dos ZIPs. Todos os dados persistentes ficam em subpastas desse caminho relativo à stack.

## Instalar e atualizar

1. Se os packages GHCR forem privados, autentique Docker no host com uma credencial local que tenha read:packages.
2. Na pasta gerada, valide com `docker compose --env-file .env -f compose.yaml config` e faça pull com `docker compose --env-file .env -f compose.yaml pull`.
3. Inicie com `docker compose --env-file .env -f compose.yaml up -d`. O deployer já terá criado no `.env` a chave aleatória `ARGWS_SETUP_TOKEN`.
4. Abra `https://seu-dominio/setup`, copie essa chave do `.env` e informe URL pública, nome, e-mail, senha e fuso horário do primeiro administrador. O formulário cria schema apenas em banco vazio, configura a instalação e cria o usuário; ao final, redireciona ao CRM automaticamente. Não é necessário executar PHP no terminal nem reiniciar o serviço.
5. Se a chave estiver ausente ou inválida antes do setup, `/setup` não mostra o formulário e a aplicação retorna 503. A rota `/install` sempre permanece inacessível.
6. Após confirmar o acesso, remova `ARGWS_SETUP_TOKEN` do `.env` e recrie o serviço web com `docker compose --env-file .env -f compose.yaml up -d --force-recreate web`. Os dados ficam em `./storage/` (ou no caminho relativo definido em `ARGWS_STORAGE_ROOT`).
7. Configure o CloudPanel para encaminhar o domínio HTTPS à porta local configurada, vinculada a `127.0.0.1`.
8. Em stacks antigas que usam volumes nomeados, faça backup e execute uma vez `sh ./migrate-storage.sh .` (deployer) ou `sh ../migrate-storage.sh .` (ZIP `deploy/`) antes do primeiro `up` com o Compose atualizado. A rotina para a stack, copia os dados existentes para `./storage/`, não sobrescreve destinos ocupados e nunca remove os volumes Docker antigos. Depois, preserve `.env`, execute `docker compose pull` e `docker compose up -d --remove-orphans`. Não use `docker compose down -v`. O provisionador recusa bancos com tabelas existentes e instalações já configuradas.

Toda persistência usa bind mounts relativos a `./storage`, controlados por `ARGWS_STORAGE_ROOT` no `.env`: configuração da instalação, banco, uploads, arquivos de módulos, temporários, cache, logs e dados/configuração do Caddy. Os diretórios dos arquivos da aplicação são preparados pelo serviço `storage-init`; o banco mantém seu diretório separado. A stack Docker fica separada da instalação PHP, PHP-FPM, Nginx e dos outros projetos do CloudPanel; escolha uma porta livre.

## GHCR e dependências

As imagens são ghcr.io/wkarts/argws-crm (aplicação), ghcr.io/wkarts/argws-crm-base:1-php8.3-bookworm (FrankenPHP), ghcr.io/wkarts/argws-crm-mysql:8.0 e ghcr.io/wkarts/argws-crm-mariadb:11.4. O sincronizador copia multiarch e preserva tags existentes; somente uma execução manual com refresh_existing=true atualiza tags já espelhadas. A agenda semanal verifica imagens sem substituí-las.

A aplicação atual declara MySQL/MariaDB. PostgreSQL e Redis não são dependências configuradas ou testadas pelo CRM e não são adicionados à stack. O database não publica porta para o host. Os Compose ficam somente no repositório e não no ZIP de aplicação.


## Primeiro provisionamento Docker

A imagem GHCR não contém o instalador web legado `install/`. O assistente `/setup` é um endpoint separado, protegido por `ARGWS_SETUP_TOKEN` aleatório; não cria usuário ou senha padrão e não é carregado nas instalações PHP tradicionais. Caddy detecta a configuração persistente e troca o roteamento para o CRM no pedido seguinte, ocultando `/setup` sem reiniciar o processo.

O volume `installation_config` guarda a configuração criada. O provisionador só aceita banco vazio e recusa instalações já configuradas; para uma instalação existente, preserve banco e configuração e siga o fluxo normal de atualização. Se uma importação falhar parcialmente, pare e revise os logs antes de qualquer ação; nunca remova volumes de dados existentes para tentar novamente.
