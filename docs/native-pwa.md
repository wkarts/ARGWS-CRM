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
A instalação usa exclusivamente os mecanismos normais do navegador (menu/ícone
de instalação no Chrome/Edge/Android; Compartilhar → Adicionar à Tela de Início no
Safari/iOS). O CRM não captura nem cancela `beforeinstallprompt`, não chama
`prompt()` e não cria banner, modal, aviso de atualização ou convite por rota.
Elegibilidade, dispensa e execução instalada/standalone ficam com o navegador.
Não há preferência de instalação armazenada pelo CRM nem convite próprio a repetir.

O registro é idempotente por URL/escopo. Uma promessa por documento impede repetir
o registro quando o mesmo asset aparece duas vezes no layout. Falha de registro
não interrompe o CRM. Atualizações aguardam o ciclo normal do service worker e o
fechamento dos clientes; sem `skipWaiting` ou recarga de formulários não salvos.
O endpoint do worker deve ser servido como JavaScript, não redirecionado ao login.
Nginx/CloudPanel precisa permitir o PHP público `pwa.php` como permite `index.php`;
verificar antes da ativação. Não ampliar allowlist global de scripts PHP.

## Validação desta correção

Executados localmente sobre o código da PR 52 reconciliado com
`77f8a081bf664b20adee88d58af0640e93b04d31`:

- `node --test tests/native_pwa.test.cjs`: 8 testes aprovados. Cobrem rede/offline,
  escopo, ausência de persistência/replay, ícones, HTTPS, nenhum listener de convite,
  estado standalone simulado e registro único mesmo com include repetido.
- Verificação de sintaxe Node dos cinco scripts PWA/testes e `git diff --check`.
- PHP/HTTP/manifest real não executados localmente: PHP não está instalado.
- Chromium local não executou os casos: o processo foi encerrado na inicialização
  porque `socket()` foi recusado pelo sandbox. Não foram usados flags ou permissões
  para contornar essa recusa. Isso é limitação do executor, não falha da aplicação.

`native-pwa.yml` mantém o gate PHP 8.1/8.5 e o browser PHP/Chromium. Além disso roda
`node tests/native_pwa_navigation.browser.cjs`: servidor HTTP Node descartável,
JS/worker publicados sem interceptar requisições, seis navegações em raiz/subpath
nas larguras 1280/768/390, include duplicado, evento sintético sem cancelamento,
nenhum painel, atualização sem recarga e POST offline sem replay. Esse gate é
específico dos assets; nomes de telas na fixture não provam autenticação, logout ou
troca de empresa do CRM real. `PWA_CHROMIUM_EXECUTABLE` permite usar um Chromium já
instalado, sem download; o CI usa o navegador pareado da imagem fixada por digest.

Permanecem pendentes: instalação real pelo menu do sistema, standalone em dispositivo,
Safari/iOS e navegação autenticada com empresa principal → secundária → principal,
login/logout e formulários anteriores à troca. Resultados do SHA final devem ser
consultados nos runs da PR; um teste de assets não satisfaz o aceite integral.

## Integração e rollback

A alteração integra a mesma branch da PR 52, que permanece em rascunho. Merge e
deploy não estão autorizados. Validar configuração do servidor e os testes acima
antes de liberar; a versão/migration é a da composição central da PR. Reverter views remove
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
como `synthetic-*` de manifesto real, ausência de UI de instalação após **evento
sintético**, atualização aguardando fechamento e página offline. O evento não é
um prompt de instalação do navegador/OS; verifica que a aplicação deixa o evento com o navegador.
Controles são mascarados. Em falha, JSON registra apenas fase, tipo de erro e
geometria, sem valores/HTML/respostas. O artifact `native-pwa-synthetic-browser`
é recolhido com `always()`; ausência por falha anterior não é prova visual.
O workflow também revalida pushes em main/develop nos caminhos relevantes.
