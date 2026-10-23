# Shell isolado: apresentação nativa sem bootstrap global

## Evidência e causa

Comparação visual dos PNGs fornecidos (painel nativo e Google SMTP, versão publicada4.7.1) mostra cabeçalho textual sem marca, menu/ícones distintos e controles HTML sem classes. Inspeção DOM somente leitura confirmou que ambos já usam Inter: não se atribui o problema à família da fonte. O botão Google tinha `class=''` e estilo padrão do navegador. O botão nativo usava `btn btn-primary`, com cor `#32c977` proveniente de `style.custom_style_btn-primary` do tema; não de OAuth.

O shell carregava Bootstrap isolado em vez de `vendor-admin.css`, omitia os tokens do tema e só as páginas marcadas `data-company-workspace` recebiam parte dos componentes. A correção é compartilhada, não um patch exclusivo da tela Google.

## Alterações e limites

- Mesma sequência pública de CSS vendor/fontes/ícones nativos, sem `init_head`, `app_admin_head`, hooks, módulos ou modelos globais.
- Marca ARGWS pública de instalação e placeholder nativo de avatar. Não se copia logotipo fiscal de outra empresa nem se consulta upload global privado.
- Projeção estrita de tokens cosméticos do `theme_style` somente quando o módulo está ativo. Duas leituras allowlisted: estado do módulo e opção de tema. Apenas IDs fixos e cores hex; nenhuma regra, URL ou CSS arbitrário do banco é renderizado. Ausência de storage cosmético conserva o baseline nativo.
- Campos textuais/select/textarea sem classe recebem `form-control`; hidden/checkbox/radio e tipos especiais são preservados. Botões sem classes em **todas** as `company-settings-card` recebem classes nativas. Classes explícitas, tipo, name/value, disabled, ownership de form, actions, handlers e CSRF não são alterados. Rótulos destrutivos usam `btn-danger`.
- Tabelas sem classe recebem `table table-hover` e wrapper `table-responsive` no mesmo parent, movendo o mesmo nó, sem clonar linhas/IDs/listeners e sem inicializar DataTables ou fetch. Tabelas já estilizadas permanecem intactas.
- Busca no cabeçalho é explicitamente **no menu desta empresa**, local e sem requests. `+` abre acessos rápidos aos destinos scoped já autorizados, não o criador global. Configurações/tarefas/calendário só aparecem conforme o gate existente. Não se inventam notificações, timers nem destinos globais de Assinaturas/Webhooks/API para simular equivalência funcional.
- Classes completas dos ícones correspondentes extraídas das definições fixas nativas (incluindo fa-regular/fa-solid), sem executar menu_helper/hooks; ordem dos destinos existentes alinhada ao padrão, preservando todos os links e filtros ACL. O menu continua refletindo recursos scoped efetivamente disponíveis; não é a cópia do catálogo global.

## Testes e evidências

Executados localmente:

- `node --test tests/company_shell_controls.test.cjs`: 7/7 aprovados.
- `STATIC_ONLY=1 node tests/company_shell_browser.cjs`: aprovado (semântica seletor/CSRF/estrutura e ausência de hooks).
- `node --check assets/js/company-shell.js` e `node --check tests/company_shell_fidelity.browser.cjs`: aprovados.
- `git diff --check`: aprovado.

Implementados e **pendentes de execução no ambiente com PHP/browser**:

- `php tests/company_shell_presentation.php`: tokens reais, hover nativo, conteúdo malicioso/URL/CSS rejeitado, módulo desativado, storage ausente e leituras limitadas.
- `node tests/company_shell_fidelity.browser.cjs`: renderiza as views PHP reais Google e Microsoft sem marcador workspace e o header nativo real. Compara computed styles dos botões, dimensão do logo e geometria/posição/cores/overflow de header/sidebar/wrapper em desktop e mobile fechado/aberto, snapshot dos forms antes/depois, tabela adicional explicitamente sintética com identidade dos mesmos nós/form preservada e wrapper responsivo, ACL de link negado, busca local e ausência de overflow mobile. Captura nove PNGs sintéticos em `COMPANY_SHELL_SCREENSHOT_DIR` (padrão `/tmp/company-shell-fidelity`).
- `node tests/company_shell_browser.cjs`: regressão do seletor e menu existentes.
- `node --test tests/native_header_overflow.test.cjs` foi tentado: 4 checks puramente estáticos passaram e 3 dependentes de PHP ficaram bloqueados por `spawnSync php ENOENT`. Não é uma aprovação da suíte completa.

O fixture adapta identidade/permissões e não comprova isolamento real por si só. Nenhum PNG pós-correção foi produzido/inspecionado localmente porque PHP está ausente. Aprovação visual final e smoke HTTP continuam necessários no CI composto. Não houve mudança OAuth/SMTP, envio de mensagens, alteração de produção, publicação, bump próprio de versão ou alteração de workflow nesta etapa.


## Geometria e rolagem

A revisão da fonte nativa revelou que `#header` é relativo e `.sidebar` absoluto com overflow visível, não fixos. O shell agora segue esse modelo: header participa do fluxo, wrapper não recebe margem superior artificial de57px e o menu longo rola com o documento. O seletor mantém espaçamento abaixo do header. A abertura mobile usa deslocamento de230px do wrapper e supressão de overflow horizontal como no CSS nativo; a alternância própria conserva o botão e não inicializa app.js. A fixture compara também alturas, coordenadas, cores, bordas, padding e movimento de header/menu ao rolar, e captura referência mobile nativa fechada/aberta. Esse comparativo segue pendente de execução PHP/browser; os sete checks Node são evidência estática/DOM simulado, não aprovação de pixels.

### Mobile content scrim

The isolated shell mirrors the native `style.min.css` mobile wrapper pseudo-element
(offset -42px, translucent background, z-index 55 and opacity transition) and the
native stylesheet's wrapper `min-height: 100%`. A local capture-phase click handler
closes the open mobile menu, consumes the dismissal gesture, and returns focus to
the toggle without submitting or navigating a covered form. Closed/mobile and
desktop content keep their normal interactions. No native `app.js` is loaded;
its dynamic content-height reconciliation is not claimed by this CSS fixture.
The browser fixture compares native/isolated scrim computed styles and wrapper
minimum height, then dismisses the scrim and checks form/CSRF invariants. Scrim
height follows each page's own content rather than comparing unlike form heights.
