# Reajustes futuros — incremento 492

A rota `invoices/recurring?reprice=1` oferece regras por item, filtros de item
revisado/meio, linhas explicitamente selecionadas, quantidade local e inclusão
somente na série alvo. Busca usa o catálogo autorizado existente. Um vínculo
legado exige identificação explícita por ID, revisão CAS e auditoria; descrição
não estabelece equivalência. Incluir item exige que todas as linhas antigas do
alvo estejam revisadas, para não assumir ausência de um item desconhecido.

A proposta guarda entrada e simulação com hashes/revisão e prova de auditoria.
O relatório preserva valores anteriores/futuros e pode ser impresso. Confirmar
exige a revisão exata; alterar qualquer alvo ou catálogo invalida a campanha
inteira, sem aplicação parcial. Repetir a chave retorna o mesmo resultado.

Aplicar adiciona journal separado, sem modificar payload/hash originais,
configuração, datas, ciclos, documentos emitidos ou catálogo. Cada ocorrência
consome o snapshot efetivo na sua data; as anteriores continuam verificáveis.
O leitor confere cadeia, hashes, auditoria e proposta aplicada. Histórico ausente
ou corrompido bloqueia a operação. Emissão em processamento impede reajuste.

Escolhas de implementação explicitadas: campanha atômica; fuso IANA escolhido
expressamente no formulário e gravado por proposta; aplicação na próxima
ocorrência não emitida no momento da execução. Emissões normais podem avançar o
calendário entre simulação e execução. Esse avanço sozinho não invalida preço e
vínculos revisados. Horário inexistente/ambíguo não recebe offset inferido.
O worker executa propostas vencidas antes de descobrir novas emissões, revalida
ACL do autor e não afirma aplicação quando a transação falha. Proposta bloqueada
permanece agendada; o contador do worker informa bloqueio, sem falso sucesso.

## Evidência local

- `tests/companies/invoice_repricing.php`: 52 verificações novas em SQLite e
  MySQL 8.4, além de pré-requisitos reais (120/122 respectivamente). Inclui
  totais, identidade estrangeira, legado sem revisão, dois alvos do mesmo
  cliente, quantidade local, inclusão local, original/calendar/catalogue
  preservados, emissão futura, idempotência, concorrência lógica, agendamento,
  horário ambíguo/inexistente, rejeição atômica e corrupção histórica.
- `tests/companies/invoice_repricing_concurrency.php`: dois processos PHP com
  sessões SQL observadas aguardando o mutex financeiro real, para confirmação
  duplicada e confirmação contra emissão. Um journal no máximo, uma ocorrência,
  preço antigo ou novo coerente com a ordem de aplicação.
- `tests/browser/invoice_repricing.cjs`: Chromium renderiza a view PHP real,
  com HTTP/dados sintéticos. Regras múltiplas, seleção explícita, qty local,
  inclusão local, vínculo não revisado bloqueado, campos de contexto/CSRF
  preservados e viewport móvel. Não é sessão autenticada/homologação.

Faltam teste HTTP autenticado, revogação concorrente específica, instalação e
tracking pelo runner completo, aprovação visual e validação da instalação alvo.
Sem chamada externa, cobrança, transmissão bancária ou uso de produção.


## Bloqueio de agendamentos após revisão

O worker registra `blocked` com auditoria quando a base/vínculo ou autorização
mudou. A simulação original permanece verificável, nenhum alvo é aplicado
parcialmente e a proposta sai da fila automática. A tela mostra o motivo, permite
imprimir e cancelar; a aplicação exige uma nova simulação/revisão. Restaurar uma
permissão não reativa silenciosamente a proposta bloqueada. A observação técnica
do worker pode registrar a rejeição após revogação, mas não aplica preço nem
concede acesso. Somente motivos enumerados são persistidos, sem mensagens SQL.
Falhas transitórias de storage e séries momentaneamente em processamento continuam
agendadas para nova execução. Não houve alteração de DDL ou da migration492.
Código antigo que não reconheça `blocked` não deve substituir esta versão após uso.

Testes adicionais: MySQL/SQLite verificam base alterada, preservação do journal,
retirada da fila, cancelamento e revogação/restauração de permissão; Chromium mostra
motivo, impressão e cancelamento sem botão de aplicação da revisão bloqueada.
