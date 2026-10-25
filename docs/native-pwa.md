# PWA nativo do núcleo

Instalação do frontend existente, sem SPA paralela, tabelas, dependências de runtime
ou migração própria. Composição da versão/migration do núcleo pertence ao integrador.

## Contrato e segurança

- `pwa.php?resource=manifest|worker|icon-192|icon-512|icon-maskable-512` é um
  endpoint público mínimo: não inicializa framework, sessão, empresa ou banco.
  GET/HEAD somente, `no-store`, MIME explícito e `nosniff`; recurso desconhecido = 404,
  POST = 405. Não usa Host, proxy headers, cookies ou parâmetros de empresa.
- Manifest em modo standalone, ID/scope derivados do diretório de instalação;
  entrada `/admin` relativa ao diretório, sujeita à autenticação normal. Instalação
  de `/crm/` não controla outro prefixo. Configure `base_url` com HTTPS e a barra final.
- `APP_DISPLAY_NAME` e `application/config/branding.php` preservam a identidade
  pública já configurada. Ícones opcionais PNG 192/512 e maskable 512 devem ficar
  em `assets/`; dimensões, tipo e realpath são verificados. Uploads de empresas,
  SVG, URL externa e caminhos fora de assets não são aceitos. Fallback é um glifo
  neutro de aplicativo. Não usar dados privados nos nomes/ícones públicos.
- A identidade visual das telas e seus logotipos existentes não é alterada.
  O ícone instalado é do produto/deployment, não da empresa selecionada; evita
  persistir a marca privada de uma empresa ao trocar de contexto/logout.
- Worker **não armazena nada** em CacheStorage, IndexedDB ou localStorage. Não
  intercepta POST, APIs, assets, certificados ou outros escopos/origens. Navegação
  GET usa rede com `cache: no-store`; falha retorna apenas tela genérica 503.
  Sem HTML autenticado offline, sync, fila, replay ou operações financeiras offline.
- Logout/autorização/isolamento continuam sendo os mecanismos nativos do servidor.
  O PWA não muda sessões nem limpa caches de outros aplicativos. Esta alteração
  não pretende corrigir políticas preexistentes de cache HTTP/bfcache do CRM.

## Instalação e atualização

HTTPS obrigatório em produção; localhost é permitido pelo navegador para testes.
Chrome/Edge/Android mostram “Instalar aplicativo” quando o navegador emite
`beforeinstallprompt`. Em Safari/iOS use Compartilhar → Adicionar à Tela de Início.
Instalação nunca dispara sem gesto do usuário; indisponibilidade do worker não
bloqueia o CRM. Nome/ícones já instalados podem exigir reinstalação conforme o OS.

Atualização avisa o usuário para salvar e fechar todas as abas/janelas do CRM antes
de reabrir. Sem `skipWaiting`, reload forçado ou interrupção de formulários.
O endpoint do worker deve ser servido como JavaScript, não redirecionado ao login.
Nginx/CloudPanel precisa permitir o PHP público `pwa.php` como permite `index.php`;
verificar antes da ativação. Não ampliar allowlist global de scripts PHP.

## Validação

Executado localmente: `node --test tests/native_pwa.test.cjs` (5 testes),
`node --check assets/pwa/worker.js`, `node --check assets/pwa/install.js`,
`git diff --check`. Também passaram 18 regressões em
`tests/crm_ui_policy.test.cjs`, `tests/argws_platform_contract.test.cjs` e
`tests/crm_ptbr_resources_contract.test.cjs`. Testes cobrem navegação rede/offline, proibições, scope,
PNG192/512/maskable, instalação por gesto, HTTPS e views nativas.

Implementado: `python3 tests/test_native_pwa_http.py` inicia PHP real em servidor
local descartável; valida MIME/headers, métodos, raiz/subpath e ícones HTTP.
**Não executado aqui: runtime PHP ausente (3 skips explícitos).** Workflow
`native-pwa.yml` prepara lint/HTTP em PHP 8.1/8.5; CI ainda não publicado/executado.

Homologação pendente em navegador real sobre HTTPS: instalar Android/desktop/iOS;
abrir standalone; alternar duas empresas e logout online; desconectar e verificar
503 sem conteúdo privado; POST falha sem replay; confirmar zero CacheStorage;
atualizar worker com formulário aberto e verificar aviso sem reload. Repetir em
`/` e `/crm/`. Os mocks Node não substituem essa homologação.

## Integração e rollback

Nenhum merge, deploy ou publicação realizado. Aplicar commit local na composição
central somente após revisão/autorização; manter versão/migration central. Validar
configuração do servidor e testes acima antes de liberar. Reverter views remove
novas instalações, mas clientes já instalados mantêm worker até atualização:
para desativação controlada, servir no MESMO URL um worker de retirada revisado
que desregistre a própria registration, manter o endpoint acessível até migrar
clientes. Não apagar arbitrariamente registros/caches de outros apps. Nada a
reverter no banco.

## Gate Chromium com HTTP real descartável

`node tests/native_pwa.browser.cjs` exige PHP e Playwright 1.55.1: ausência é erro,
não skip. O job `browser` usa a base de validação PHP 8.1 por digest, com Chromium
pareado já instalado; não instala pacotes adicionais. O router sintético escuta
somente `127.0.0.1`, copia apenas arquivos públicos PWA/branding para diretório
temporário e renderiza o include real. Não carrega banco, sessão nem credenciais.

A suíte verifica raiz e `/crm/` em contextos separados: MIME e manifesto reais,
decodificação das dimensões PNG e propósito maskable, registro/scope, atualização
que aguarda os clientes fecharem, preservação de formulário não salvo, mensagem
offline genérica sem dados, ausência de CacheStorage/IndexedDB/web storage e
falha de POST offline sem replay ao reconectar. A mudança do worker ocorre apenas
na cópia descartável. O escopo `/crm/` não controla uma página fora dele.

Isso não comprova instalação pelo menu do sistema, `beforeinstallprompt` real,
empacotamento Android/iOS, homologação em Safari nem o CRM autenticado completo.
Headless verifica o contrato HTTP/service worker com dados sintéticos, não uma
instalação em dispositivo. Nesta preparação, somente contratos Node e sintaxe JS/
YAML foram executados localmente; execução PHP/Chromium continua pendente no CI.

Evidências visuais: a matriz raiz/subpath × 1280/390 px salva PNGs identificados
como `synthetic-*` de manifesto real, UI de instalação acionada por **evento
sintético**, atualização aguardando fechamento e página offline. O evento não é
um prompt de instalação do navegador/OS; testa somente a UI real da aplicação.
Controles são mascarados. Em falha, JSON registra apenas fase, tipo de erro e
geometria, sem valores/HTML/respostas. O artifact `native-pwa-synthetic-browser`
é recolhido com `always()`; ausência por falha anterior não é prova visual.
O workflow também revalida pushes em main/develop nos caminhos relevantes.
