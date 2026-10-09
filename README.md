# ARGWS CRM

CRM da ARGWS para execução em hospedagem PHP tradicional ou em container FrankenPHP. A distribuição mantém configurações e dados de cada instalação fora da imagem.

## Requisitos

- PHP 8.1 ou superior para instalação tradicional.
- MySQL ou MariaDB.
- Extensões PHP: `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`.
- Docker Engine e Docker Compose para a distribuição em container.

O instalador fica em `install/`. Configure o domínio para servir a raiz do projeto, prepare a base de dados e confirme que as extensões estão habilitadas na mesma versão do PHP usada pelo PHP-FPM ou pelo servidor web. Consulte [Operação PHP e containers](docs/operacao-php-e-containers.md).

## Container FrankenPHP

A implantação recomendada usa os Compose por ambiente e o binário `argws-crm-deployer` documentados em [deploy/README.md](deploy/README.md). O deployer gráfico ou de terminal prepara `compose.yaml` e `.env`; a configuração, o banco, as senhas e os uploads ficam fora da imagem. A GUI é distribuída em um ZIP que inclui o CLI usado como backend; mantenha os dois executáveis extraídos na mesma pasta.

A imagem GHCR não inclui o instalador legado `install/`. Em uma instalação nova, abrir o domínio redireciona automaticamente para o assistente web `/setup`, protegido pela chave aleatória `ARGWS_SETUP_TOKEN` criada pelo deployer. A tela responsiva coleta os dados da instalação e do administrador principal; o assistente importa o schema somente em banco vazio, aplica as migrations pendentes e só então libera o CRM. Ao concluir, encaminha à tela de acesso sem exigir comandos no terminal ou reinicialização manual. Depois do setup, `/setup` e `/install` ficam inacessíveis. Não há usuário nem senha padrão.

O Compose de raiz permanece disponível para um banco MySQL/MariaDB externo. Copie `container.env.example` para `.env`, configure as credenciais locais e gere `ARGWS_SETUP_TOKEN` com `openssl rand -hex 32`. Os volumes de configuração, uploads, módulos, cache, logs e Caddy ficam em `./storage` por padrão; altere somente o caminho relativo `ARGWS_STORAGE_ROOT` no `.env` para movê-los juntos. O entrypoint do serviço web prepara as pastas dos bind mounts e inicia o processo PHP/Caddy como `www-data`, sem manter um container auxiliar encerrado no painel. Ao atualizar uma instalação antiga que usa volumes nomeados, faça backup, execute `docker compose pull`, rode `sh deploy/migrate-storage.sh .` e então `docker compose up -d`; o migrador preserva os volumes de origem. Siga [Operação PHP e containers](docs/operacao-php-e-containers.md). Para instalações PHP tradicionais, o instalador web continua em `install/`.

## Idioma, suporte e privacidade

Português do Brasil é o idioma disponível nesta versão. A política de terminologia e as configurações do widget de suporte são administradas no painel em **Configurações → Plataforma ARGWS**. O endereço do suporte e o token público são definidos na própria aplicação.

A telemetria de usuários e licenciamento e a validação remota de licença ficam desativadas. Logs operacionais permanecem locais; integrações de negócio configuradas pelo administrador continuam disponíveis para os fluxos que elas atendem.

## Releases e atualizações

O canal `develop` publica uma imagem de desenvolvimento e atualiza um único prerelease contínuo pela tag fixa `argws-crm-develop`. As promoções para `main` usam SemVer automático e publicam no GHCR as tags de versão, major.minor, major e latest, além dos ZIPs completo e incremental. A mesma release estável anexa o ZIP `ARGWS-CRM-deploy-<versão>.zip`, os binários de terminal, os executáveis e os ZIPs completos da GUI, checksums e manifesto; eles não criam releases separadas. Cada aumento de versão também avança `migration_version`. O caminho até o novo nível inclui todos os arquivos intermediários; por exemplo, 3.5.0 leva os marcadores 343–349 e a migration 350, evitando lacunas a partir do nível 342. Marcadores sem alteração de schema não executam DDL, e migrations históricas permanecem intactas.

Faça backup do banco, da configuração local e dos uploads antes de atualizar. O pacote incremental preserva os dados enviados pelos clientes e lista arquivos de código removidos. Consulte [Distribuição ARGWS](docs/distribuicao-argws.md) para os detalhes do versionamento, build, cache e publicação e [Arquitetura e dados](docs/arquitetura-e-dados.md) para as migrations e os recursos nativos.

## Deployer gráfico e terminal

A GUI em Python/Tkinter usa controles de desktop tradicionais e não depende de OpenGL, WGPU, Vulkan ou acelerador gráfico. Ela chama o CLI Rust que acompanha o ZIP e não duplica as regras de geração ou validação. Baixe e extraia o pacote inteiro argws-crm-deployer-gui-win-x64.zip ou argws-crm-deployer-gui-linux-x64.zip para manter os executáveis juntos.

O CLI avulso atende automações e servidores sem desktop. Ele oferece os comandos interactive, generate, validate e version, além do log operacional opcional --log-file; os logs registram apenas a operação e o resultado. Senhas e tokens gerados permanecem no .env protegido e não entram nos logs. O assistente web em /setup cria o primeiro administrador.
