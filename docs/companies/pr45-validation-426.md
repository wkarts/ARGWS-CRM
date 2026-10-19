# Validação do incremento426

Alvo: núcleo4.2.6/426, BB1.1.2/112, Asaas1.5.0/150, sobre o HEAD425
`1369969f6c27a5e9528870709d57e203cf7f9ef2`. Migrations históricas preservadas.
A árvore de produção foi exportada do índice antes dos testes HTTP; mudanças do
incremento seguinte não entram nesse fonte nem nesta evidência.

## Fluxos e provas locais

PHP8.3.6, MariaDB10.11.14/InnoDB; dados sintéticos e transportes de provedores
simulados. Contagens são cumulativas por suíte e não devem ser somadas.

| Requisito | Código | Execução observada |
|---|---|---|
| API operacional A/B/principal e tokens próprios | CompanyOperationsApi/Scope, storages com guard antes/depois, rotas/autoload mínimo | API122, concorrência147 MariaDB; bootstrap45 SQLite |
| Importação/exportação de despesas | CompanyImports, mesmo writer CompanyExpenses, preview/revisão/commit em lote | Importação103 e concorrência111 MariaDB; falha intermediária sem lote parcial |
| Anexos/notificação de vendas | CompanySalesFiles/Delivery/ExtrasHttp, worker App_Email e portal privado | Concorrência247 MariaDB; worker241 SQLite, SMTP capturado |
| Moeda e histórico local | CompanyCurrencyUnits, writers, PDF/listas/portal, guard de catálogo | Units112, concorrência122 e writer/journal140 MariaDB; catálogo divergente veta nova mutação |
| Diário por período/corte UTC | CompanyFinancialJournal/LedgerReports, GUI e CSV privado com watermark | Ledger/controller162 MariaDB após microfix; emissão/original/estornos separados, observações do upgrade explícitas |
| Upgrade/replay426 | Migration426 e schemas exatos; baselines só por proprietário persistido | Native426128 MariaDB; upgrade composto395→40411 checks; tracking12 SQLite |
| Asaas remoção própria/ambiente | CompanyRemoteOperations/Schema150 e GUI | Remoção166, concorrência179, moeda127 e ambientes127 MariaDB; native15039/37; nenhum DELETE bancário real |
| BB juros diários e movimentos | ApiDailyInterest/Movements, perfil/capacidade explícitos, outbox/GUI | Movimentos200 MariaDB, inclui juros179 e baseAPI; resposta observacional sem recebimento automático |
| Regressões financeiras | Writers, créditos, portal, Asaas, API e BB existentes | API/locks, créditos, portal96, jobs/tarifa177, APIBB344 e conciliação240131 MariaDB |
| Legado e fronteiras | invoice_issuer_* e gate de consumidores não adaptados | Scope146, exports9, reports14, statement24, maintenance24, queue100, arquivos42 SQLite |

## HTTP e Chromium

Rodada limpa integral: **21 de21 execuções aprovadas**, com instalação nova,
upgrade HTTP pelo runner nativo até426, replay2x e validação dos4schemas. Aplicação
real mysqli, MariaDB e Chromium140.0.7339.186. Fluxos A/B/principal, portal,
documentos, anexos/envios, importação, diário/CSV, API financeira e operacional,
BB112 e Asaas150 passaram; settings teve verificação adicional separada.

Manifest inicial do fonte completo:
`9e44a267d857b4c9e285034b0bbdc036a0df65bff800803ffe83508e60cf613e`.
Durante a execução somente os3 inventáriosJSON documentais foram regenerados pelo
root. Nenhuma diferença de produção em application/modules/assets e demais bytes
de execução. Os hashes inicial/final e o delta documental são registrados no JSON
de QA. Não houve Docker: processos isolados; adaptação estrita só de comandos de
fixture/SQL. A aplicação, banco e navegador foram executados de fato.

## Correções reproduzidas durante QA

- Sem empresa selecionada, relatório retorna403 antes de um helper tipado receberNULL.
- Seletor sem empresa renderiza sem chamar uma classe de runtime ainda não carregada;
  nenhum contexto, grant ou dado financeiro é inventado para preencher a tela.
- Coverage conta todas as fontes próprias, inclusive uma linha sem snapshot/recibo,
  para apresentar lacuna de evidência; não adota registrosNULL nem hidrata o órfão.
- Testes Chromium abrem o details correto e usam os elementos reais do formulário;
  asserts monetários, idempotência, autorização e ausência de pagamento foram mantidos.

## Limites

ISO capturado no upgrade é observação atual, sem alegar recuperação de moeda original
já alterada antes da atualização. O diário contém mutações locais verificáveis e
lacunas explícitas; não é fechamento fiscal, caixa bancário autenticado ou escrituração
contábil completa. SMTP, BB e Asaas foram simulados; TLS local não é homologação.

Encargos/descontos/abatimentos na aplicação financeira BB, checkout Asaas e anexos
operacionais estão preparados para o427 separado, ainda não publicados/testados
nesta evidência. CNAB400 conserva seus bloqueios documentais específicos. Recuperação
gráfica legada terá autoridade por prova de provisionamento escolhida pelo usuário;
o registro confiável e sua cadeia de verificação ainda não foram fornecidos.

Sem merge, deploy, force-push, tag, release ou publicação manual de imagens.
