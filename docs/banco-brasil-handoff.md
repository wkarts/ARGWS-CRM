# PR45 — estado da integração BB e Empresas

## Branch e ancestralidade

- PR45: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch única de escrita: `feat/banco-brasil-billing-cnab240`; destino `main`.
- Base publicada verificada: `aa58b832b3c5ed04a599caacba125b056c081ba7`, 4.0.2/402.
- PR46 já incorporada por merge `cdd6f6f2c312a1aaa5f0f02056ad2e8b23eb3378`.
  Está encerrada; não reabrir nem tratá-la como trabalho independente.
- HEAD conferido antes deste incremento: `44cd7c3815123883d5c3168ae045a75a3347b236`.
- Incremento publicado: `30fb0f10a3ab5eac598d795ef9c712a1187b8364`, núcleo 4.0.5/405,
  responsável da instalação e wizard atômico. Conferir o HEAD remoto antes de escrever;
  correções e evidências posteriores não são certificadas pelo CI de um SHA anterior.
- BB 1.0.4/101–104 e Asaas 1.4.8/148 preservados. Núcleo402→403→404→405;
  nenhuma migration histórica renumerada, removida ou reescrita.

## Implementação ligada ao CRM

Empresas é um recurso do núcleo. `modules/issuers` continua fora da carga operacional.
A empresa principal é a identidade persistida da instalação; não se presume ID1.
O instalador captura o administrador realmente criado. O legado ambíguo possui agora
uma designação explícita com autorização de console, confirmação gráfica por senha,
expiração, limite de tentativas, vínculo ao colaborador e auditoria atômica. Isso não
é apresentado como prova do administrador histórico. Ver [onboarding](companies/onboarding.md).

O wizard salva rascunhos por responsável e revisões, permite retomada e aplica empresa,
identidade canônica, preferências, acessos, padrão e auditoria em uma transação.
O master ativo cobre empresas/recursos futuros sem copiar grants, mas continua sujeito
às barreiras de funcionalidades não concluídas. O editor nativo de colaboradores
recusa criação sem empresa/padrão e mantém a mensagem de falha se perfil e grants
não forem confirmados juntos no fluxo legado.

BB usa `IssuerCore`/`IssuerRegistry` e o contexto nativo, com ACL por recurso/empresa.
As quatro rotas permitidas continuam `index`, `register_connection`, `register_layout`
e `import_return`. O cadastro preserva empresa, conta, ambiente, convênio, carteira,
variação e identidade legal; perfis 240/400 são explícitos e imutáveis. Guias funcionam
no shell isolado; tokens não são duplicados. A importação guarda observações,
deduplicação, conflito e proveniência com auditoria. Nenhum pagamento é criado.

## O que continua faltando implementar

- Clientes privados: adoção e edição pela interface, compartilhamento explícito apenas
  da identidade, portal e recuperação por empresa; validar leitores e consumidores
  antes de abrir as rotas. As barreiras atuais permanecem.
- Domínios financeiros e operacionais por empresa: faturas, créditos, pagamentos,
  recorrências, projetos/tarefas, despesas, relatórios, exportações, arquivos, APIs,
  módulos, cron e filas. As adaptações existentes não equivalem a rollout completo.
- BB API: OAuth, configuração protegida por conexão, emissão/consulta/alteração/baixa
  e notificações conforme a OpenAPI oficial vigente, sem inventar endpoints/scopes/mTLS.
- Origem de cobrança ligada à fatura, reserva concorrente do Nosso Número, lotes,
  geração e download auditados, reenvio idempotente e política de transmissão.
  As tabelas reservadas ainda não são essas operações implementadas.
- Retorno além da observação: associação comprovada à origem e título, divergências,
  política de baixa parcial, juros/descontos/tarifas, estorno/rejeição/ordenação,
  deduplicação entre canais e escrita no gravador financeiro aprovado.

Esses itens são **pendências técnicas**, não somente homologação externa.

## Contratos externos ainda necessários

Em 19/10/2026 os PDFs canônicos foram baixados e extraídos novamente; os hashes
correspondem aos documentos240/400. CBR641 julho/2026, página 10, define claramente
seu trailer de remessa: 9, brancos 2–394, sequência 395–400. A divergência de picture
é do trailer de retorno CBR643 (26–39 tem 14 posições; picture `9(013) v99`).
BRL no campo 85–86 do CBR641 continua sem código explícito na nota 30; juros podem
herdar a configuração do beneficiário. O candidato 400 permanece indisponível para envio.

A página oficial aponta para `https://apoio.developers.bb.com.br/apis/5?versaoApi=2`,
sem OpenAPI utilizável nesta consulta. `https://api.bb.com.br/cobrancas/v2/swagger?`
retornou 403 sem AppKey. É necessária uma exportação oficial atual, sem segredos,
ou acesso público funcional à especificação. Não usar contratos de terceiros como prova.

O usuário informou ter remessas aceitas de outra aplicação, mas elas não estão anexadas.
Quando fornecidas, tratar os originais como privados, anonimizar estrutura antes dos
fixtures e não publicar dados bancários ou pessoais. Não há retornos reais disponíveis.

Homologação é separada por API, CNAB240 e CNAB400 e por empresa/conta/convênio/carteira.
Nenhum canal foi homologado nesta sessão; nenhuma transação bancária real foi autorizada.

## Evidências executadas

No commit `30fb0f1`, todos os 16 workflows terminaram com sucesso. Em particular:

- [Empresas, MySQL 8/PHP 8.1 e 8.5](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107754).
- [Instalação/upgrade, HTTP, Chromium e wizard real](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107803).
- [BB codecs, módulo, MySQL e driver concorrente](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107753).
- Asaas origem/callback, notificações, privacidade, recorrências, versões/migrations e
  builds de PR também passaram. Isso não valida todos os consumidores do CRM.

Localmente: PHP 8.3, fixtures SQLite, lint e testes de contrato; MariaDB foi preparado,
mas o ambiente recusou socket Unix e a permissão para iniciá-lo. Portanto a evidência
MySQL/HTTP completa vem do CI descartável. Chromium local executou a regressão de abas;
essa regressão sintética não substitui o cenário CRM/HTTP do CI.

Consultar [matriz objetiva](companies/pr45-status.md) e o CI do HEAD exato na entrega.
Inventários lexicais são localizadores de revisão, não contagem de bugs ou cobertura.
Não elevar evidência de consumidor só atualizando hash. As entradas antigas com hashes
divergentes deixam de aparecer como testadas no registro regenerado.

## Release

Não fazer merge na main, deploy, tags, publicação de imagens ou alinhamento de develop
sem autorização correspondente. O caminho normal de release permanece intacto. Uma
PR aberta, CI verde ou cadastro/configuração visual não representa aprovação de merge
nem uma operação bancária homologada.
