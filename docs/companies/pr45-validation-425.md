# Validação do incremento425

Branch PR45: `feat/banco-brasil-billing-cnab240`. Base preservada8078d910.
Núcleo4.2.5/425; Asaas1.4.9/149; Banco do Brasil1.1.1/111. Não houve merge,
deploy, uso de credenciais reais ou operações financeiras externas.

## Código e migrações

425 acrescenta schemas próprios de contratos/tickets, documentos/conversões,
recorrências, tokens de API, extras de projetos e tarefas avulsas. Mantém424e
históricos anteriores byte a byte. 149 acrescenta contas Asaas por empresa/ambiente,
provas, inbox, recibos, jobs e vínculo de tarifa com despesa. 111 acrescenta
receptores/notificações BB; instruções de vencimento usam o ledger API existente.
Os headers dos dois módulos exigem núcleo4.2.5. Instalação, upgrade, replay,
metadata e falha de tracking fazem parte dos testes nativos.

## Provas locais

PHP8.3.6, aplicação CodeIgniter/mysqli, MariaDB10.11.14 InnoDB e
Chromium140.0.7339.186. Os testes de serviço usam também PDO/SQLite; SQLite não
substitui prova de locks nem de metadata MySQL.

O manifesto SHA256 dos arquivos de produção testados é
`63413ae4005346a140f47c1a896f4f8420973cd66e39826089c6b4cff504edd7`.
A cópia executada tinha **zero diferenças nos bytes de produção** em relação ao
índice congelado. A instalação HTTP criou o administrador17; o upgrade partiu do
SQL antigo real e alcançou425 pelo runner nativo. O corpo425 foi repetido duas
vezes, com readback dos cinco schemas, sem reduzir ou falsificar tracking.

Foram16 execuções com exit0:14 suítes distintas do repositório (a suíte nativa foi
executada em instalação e upgrade) e uma prova adicional das configurações.
Cobrem wizard/master/staff, portal/reset, faturas/PDF/recebimentos/anexos,
créditos/estornos, projetos/tarefas/despesas, contratos/tickets,
marcos/seguidores/dependências/kanban/anexos/avulsas, documentos/conversão,
recorrências e portal de vendas, relatório/CSV/API, BB e Asaas. A/B, principal,
legadoNULL, contexto obsoleto e ID cruzado permanecem delimitados pelos casos de
cada suíte, não por alegação de ausência universal de vazamentos.

Testes de serviço/concorrência adicionais incluem consultas bancárias simuladas,
dois workers observados esperando locks reais, replay, saldo, revisão, rotação e
revogação. Asaas opt-in/lease/política são revalidados dentro do writer; tarifa
usa o mesmo writer de despesas. Três handshakes TLS locais com certificados
sintéticos verificam cliente aceito, cliente ausente e certificado fora da CA.

Reexecução final em MariaDB diretamente dos arquivos congelados, todos exit0:
API145, Asaas/jobs/tarifas177, BB/instruções240, extras77, tarefas avulsas68,
contratos/controller81, vendas/recorrência158 e portal de vendas158 verificações
cumulativas. Esses números incluem fixtures reutilizadas e não devem ser somados.

A execução local usou processos nativos isolados, **sem Docker**. Um adaptador
restrito substituiu apenas subprocessos de fixtures e SQL; não substituiu o CRM,
o HTTP, o banco ou o navegador. Fluxos bancários e transporte SMTP foram simulados.
Isso não certifica ingresso TLS implantado, aceitação bancária, conta real ou
entrega de e-mail externo.

## Correções verificadas

- Teste de desaplicação abre deterministicamente o formulário recolhido, mantendo
  os asserts de saldo/histórico. Corrige a falha de stack do CI8078d910.
- Cópia de contrato normaliza IDs retornados como strings pelo mysqli antes de
  chamar métodos estritos; regressão explícita com stringify-fetches.
- Despesa inexistente/estrangeira retorna404 antes de consultar arquivos.
- Fixtures Asaas usam CPFs sintéticos independentes e não disputam identidade com BB.
- Relatório recusa moeda ausente e nominal inválido em vez de omitir faturas.

## Limites e continuação

O CI deste incremento deve ser consultado no SHA remoto publicado; os checks de
8078d910 não substituem seus resultados. Homologação bancária, configuração real
do ingresso mTLS e SMTP externo seguem separados da prova de código. CNAB400
continua bloqueado pelos insumos BRL/juros/contrato/CBR643 descritos no guia próprio.

A continuação426 trata anexos/notificações de vendas, importação/exportação,
API operacional, operações remotas Asaas, encargos BB e preservação da moeda/ledger
histórico. O inventário transversal mantém rollout bloqueado e
`operational_ready=false`; não foi promovido por varredura automática.
