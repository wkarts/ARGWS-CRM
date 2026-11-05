# PWA e identidade V2 sobre a 4.0.2

## Referências e escopo

Base imutável: tag `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`,
branch `reference/4.0.2-pwa-visual`. Trabalho: `feat/4.0.2-pwa-visual`.
Fonte dos assets/contrato PWA: 4.8.7, `1e5a4a1e64ef230f3f996ebe871dc3c20943829d`.
A PR deve ter a branch de referência como base, nunca main. Não é um downgrade
nem um pacote de atualização da 4.8.7. Não aplicar esta árvore sobre uma instalação
4.8.7. Versão global e migrations permanecem exatamente na 4.0.2 por instrução
expressa: esta etapa não autoriza versão, tag, release, merge ou deploy.
O gate legado `core-release-policy` exige incremento e pode reprovar este rascunho;
não foi desativado ou adulterado para simular uma release.

Portados logos, símbolos, favicons, ícones instaláveis V2 e service worker.
As três views de head receberam somente o include PWA. Telas, ações, handlers,
controllers, models, módulos, integrações, rotas e migrations da 4.0.2 são preservados.
Não há portabilidade de multiempresa, SaaS, Banco do Brasil ou projeção dinâmica
`NativePwaIdentity`. O módulo SaaS futuro permanece fora desta etapa.

## Contrato real

- PWA integrado ao frontend existente: instalação pelo menu do navegador,
  HTTPS em produção, localhost para testes. Não há banner/modal próprio.
- `pwa.php` público aceita GET/HEAD e recursos explicitamente permitidos; responde
  `no-store`, MIME explícito e `nosniff`. Não inicializa sessão/framework/banco.
  Host, cookies e parâmetros de empresa não determinam nome, escopo ou arquivos.
- Manifest usa `display: standalone`, entrada `admin` e escopo do diretório de
  instalação. Nome público vem de `pwa_installation_name`, depois
  `APP_DISPLAY_NAME`/`app_display_name`; o padrão CRM recebe ARGWS CRM no manifesto.
  Tema configurável em `branding.php`. Nenhum nome fiscal/privado é consultado.
- Ícones públicos V2 são empacotados. Sem uploads dinâmicos ou projeção de empresa.
  Logos/favicons personalizados nas telas continuam pela precedência da 4.0.2;
  o include não acrescenta favicons que substituam os configurados.
- Worker não usa CacheStorage, IndexedDB, web storage, sync, fila ou replay.
  Navegação GET: rede sem cache; falha: tela genérica 503. APIs, POSTs, uploads,
  certificados e assets não são interceptados. Não existem operações offline.
- Atualização espera o fechamento dos clientes: sem skipWaiting ou recarga de
  formulários. Autenticação e autorização continuam no servidor da 4.0.2.
  Políticas HTTP/bfcache preexistentes do CRM não são corrigidas por este porte.

## Validação reproduzível

```
node --test tests/native_pwa.test.cjs
python3 tests/test_native_pwa_http.py
php tests/brand_assets_smoke.php
php tests/crm_ui_branding.php
node tests/native_pwa_navigation.browser.cjs
node tests/native_pwa.browser.cjs
```

O workflow `native-pwa.yml` cobre contratos Node, PHP 8.1/8.5 e Chromium/Playwright
1.55.1 em imagem fixada por digest. As fixtures não usam dados ou credenciais reais.
Para Chromium já instalado, `PWA_CHROMIUM_EXECUTABLE` seleciona o executável.

Executados localmente: 11 contratos Node; navegação sintética Chromium em raiz e
`/crm/`, larguras 1280/768/390; PHP 8.1.34 com lint, HTTP, branding padrão/customizado,
69 checks de branding; navegador com endpoint PHP real em raiz/subpath e 1280/390.
Esses testes cobrem atualização aguardando clientes, formulário não salvo,
offline sem dados e POST sem replay. Não comprovam instalação pelo menu do sistema,
Safari/iOS, standalone em dispositivo nem CRM autenticado com banco completo.

## Homologação, operação e rollback

Antes de qualquer autorização futura de distribuição: homologar uma cópia
DESCARTÁVEL da 4.0.2 em HTTPS, login/logout, cada menu/ação original, permissões,
formulários e integrações em sandbox. Instalar pelo menu em Android/desktop e
Adicionar à Tela de Início no iOS; testar reconexão e atualização com formulário
aberto. Nginx/CloudPanel deve servir especificamente `pwa.php` como PHP público,
sem redirecionar ao login e sem ampliar allowlist genérica de scripts.

Não há DDL, dependência nova de runtime ou alteração de dados. Nenhum deploy foi
executado. Reverter código/identidade é possível; clientes com worker instalado
exigem retirada controlada: servir worker revisado no MESMO URL para desregistrar
somente a própria registration, mantendo endpoint até migração dos clientes.
Não apagar registros/caches de outros aplicativos. Nada a reverter no banco.
