# PR 52 — PWA convencional e estado verificado

Repositório exclusivo: `wkarts/ARGWS-CRM`; branch `fix/native-company-experience`.
Base desta correção: `77f8a081bf664b20adee88d58af0640e93b04d31`.
A PR permanece em rascunho. O objetivo integral de uma única aplicação não está concluído.

## Implementação deste incremento

- Removidos de `assets/pwa/install.js` o painel flutuante, seus botões Instalar/Fechar,
  a interceptação de `beforeinstallprompt`/`appinstalled` e os avisos automáticos de
  atualização. A instalação usa o navegador, sem preferência persistida pelo CRM.
- Registro idempotente com guarda por documento; o mesmo include duplicado não
  registra o worker duas vezes. Sem recarga forçada ou ativação antecipada.
- Mantidos manifest, ícones, escopo e worker atual. O worker não persiste respostas,
  não intercepta POST/API e usa rede/no-store nas navegações; offline é apenas 503 genérico.
- Testes existentes atualizados para a ausência de convite próprio, mantendo
  cobertura de rede/offline, identidade, escopo e ausência de persistência/replay.
- Novo gate Chromium/HTTP Node para assets reais em seis rotas sintéticas por
  contexto, raiz/subpath e desktop/tablet/celular. Não substitui CRM autenticado.

## Reconciliação e preservação

O ZIP `ARGWS-CRM-fix-native-company-experience.zip` foi extraído em área separada.
736 arquivos divergentes foram lidos novamente pelo conector no SHA atual; os
demais foram aceitos apenas por igualdade de blob Git. Depois da correção dos
metadados de modo do snapshot, a árvore base conferiu integralmente com a árvore
remota `9bce58ecafe6bb5542805c4b30963e728e063369`.
O objeto do commit base também foi reconstruído e validado pelo seu SHA exato;
isso não é recuperação de alterações não commitadas do executor anterior.

O checkpoint de 25/10 foi inspecionado separadamente, sem aplicar seus patches.
Seu manifesto registra remoto `e1ef382d`, componente central `bbe2a669` e outros
componentes anteriores ao HEAD atual. A restauração integral não foi usada.
`/tmp/argws-pr52-recovery-20261026` não existe neste executor. As 11 alterações
relatadas no ambiente anterior não foram lidas nem recuperadas. Nenhum arquivo
ou processo daquele ambiente foi removido.

## Evidência local

- 42 testes Node aprovados: PWA (8), seletor (6), dashboard (7), assets compilados (3)
  e políticas de UI/plataforma/PT-BR (18). São contratos/doubles; não são aceite HTTP.
- Sintaxe Node de `install.js`, `worker.js` e dos três arquivos de testes PWA aprovada.
- `inventory-company-boundaries.py --check`: 15.075 arquivos, todos unresolved;
  `operational_ready=false`. O resumo do HEAD estava atualizado; nenhuma marca
  de prontidão foi promovida.
- `company_ui_surface_inventory.py`: 101 views e 56 controllers; 51 páginas ainda
  usam o shell paralelo. O censo registra 49 controllers paralelos.
- `audit-issuer-isolation.py`: 645 arquivos, 611 unreviewed, 34 partial, zero erros
  de inventário. Não prova isolamento em execução.
- Dois testes Python do inventário de fronteiras aprovados; `git diff --check` passou.
- PHP/Composer/Docker/MySQL/MariaDB não estão instalados. `apt-get update` falhou
  em operações de identidade/permissões; não foram usados parâmetros para contornar.
- Chromium local não executou os casos: `socket()` foi recusado na inicialização.
  O teste permaneceu obrigatório no CI, sem skip, timeout maior ou flags de evasão.
- Nenhum arquivo PHP funcional ou migration foi alterado neste incremento. A versão
  4.7.4 e os passos já existentes da mesma PR foram preservados.

## Falhas e trabalho restante

O SHA base tem focused PR/push e Empresas reprovados em
`tests/company_context.php:214`. O fixture `init_head()` só emite um header
sintético e não renderiza a sidebar/seletor; o contrato conta dois formulários.
É preciso verificar a composição real e completar o fixture sem reduzir a assertion.

Stack `40355147624`: helper `get_system_favourite_colors` ausente no caminho do
calendário. Ele está em `misc_helper.php`. O dashboard renderiza o modal até para
quem perdeu a permissão e não compõe os dados `native_calendar_*` do endpoint
nativo. Não publicar isso como corrigido: nenhum PHP foi alterado neste incremento.

`NativeCompanyCalendarFeed::read()` ainda recusa a principal explicitamente. Sua
correção precisa preservar eventos e documentos históricos, além dos dados próprios;
remover apenas a exceção omitiria a linhagem histórica e não satisfaz o contrato.
O fixture Staff citado precisa ser reproduzido no runtime PHP/banco efetivo.

Banco do Brasil no SHA base passou no run `40355147575`; não é falha ativa dessa rodada.
Ainda faltam remoção dos 49 controllers/telas paralelos, administração nativa sem
resolução legada, fluxos financeiros/configuração no mesmo CRM e provas autenticadas
principal → secundária → principal, IDs, anexos, ACL revogada e formulário antigo.
Asaas/BB/Efí/PFX/P12/bridge não foram homologados por esta correção de PWA.

## Limites, distribuição e rollback

Sem merge, deploy, force-push, cobrança, SMTP ou chamada de integração real.
`install/database.sql` e `.github/workflows/asaas-payment-origin.yml` intactos.
Sem alterações de schema, credenciais, dependências ou histórico de migrations.
O CI do SHA publicado deve ser consultado na PR, inclusive focused push e PR.
Para rollback desta correção, reverter o commit de assets/testes; não há dado a
reverter no banco. Reverter o JS também reintroduz o convite invasivo anterior.
Instalação real/standalone e Safari/iOS continuam dependências de homologação manual.
