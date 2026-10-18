# Cliente privado por emitente: fronteira de ativação

A fundação essencial permanece ativa. A criação/adoção de clientes privados e
os fluxos financeiros multiemitente são decisões distintas: nenhum deles é
liberado pelo cadastro de uma empresa, pelo vínculo de um cliente ou por esta
lista. `ClientIssuerScope::assertAdoptionReady()` continua recusando adoção.

## Incremento implementado nesta composição

- O diretório legado e contatos não incluem clientes com owner persistido;
  links compartilhados não concedem acesso ao diretório global.
- `ClientIssuerScope::forIssuer()` exige emitente/ator explícitos, consulta o
  vínculo e revalida ACL depois da leitura. A projeção contém somente `userid`,
  `company`, `vat`, `owner_issuer_id`; coluna futura não é compartilhada por acaso.
- `Invoices_model`, `Credit_notes_model`, `Estimates_model`, `Proposals_model` e
  `Contracts_model` recusam novo documento/retarget para cliente privado antes
  dos efeitos do writer. Revalidam a referência alterada por hook antes de gravar
  documento/itens. Proposta combina o patch com o `rel_type` persistido: remover
  esse campo no hook não permite trocar apenas o ID de um cliente. Valores não
  canônicos como `CUSTOMER`/`customer ` são recusados antes do writer, pois a
  collation MySQL pode considerá-los equivalentes em consultas posteriores.
- Atualização, cópia, conversão para fatura e renovação de contrato recusam fonte
  já vinculada a cliente privado antes de hidratar o modelo, quando esses métodos
  existem. Isso é uma restrição explícita do editor legado, não uma migração dos
  documentos históricos para o proprietário do cliente.
- CustomerCSVExport e ContactCSVExport filtram seus SQLs por clientes legados.
  O teste não afirma que o artefato CSV/ZIP completo foi validado.

O writer ainda executa seus hooks legados quando o destino inicial é autorizado.
A segunda validação impede persistir um novo vínculo privado injetado pelo hook;
não desfaz efeitos externos ou metadados que um hook anterior já tenha produzido.
Não há alegação de transação universal com plugins ou alteração concorrente do
owner por SQL externo. A nova adoção produtiva permanece fechada.

## Checklist antes de habilitar criação privada

Os itens abaixo são pendências concretas da integração funcional. A existência de
um acesso direto não prova divulgação em produção; requer fixture da entrada e
prova do escopo antes de marcar cobertura.

1. Concluir o serviço e os formulários próprios do módulo: proprietário explícito,
   privado por padrão, edição canônica só pelo proprietário, compartilhamento do
   mesmo `client_id`, conflito CPF/CNPJ com erro genérico e sem hooks globais.
2. Consultas globais de seleção e apresentação: modal de pagamentos, relações de
   propostas, helpers de nome de cliente e seletores de tarefas/projetos. Definir
   separadamente identidade mínima de documento legado autorizado e acesso ao
   perfil completo; não resolver com sessão/emitente padrão implícito.
3. Escritores adicionais: projetos, tarefas ligadas a cliente, despesas,
   assinaturas, conversão de leads e importadores. Cobrir destino submetido,
   relação persistida e troca por hook antes de abrir uma referência privada.
4. Importação/GDPR: `Import_customers`, `Import_expenses` e `Gdpr_contact` ainda
   exigem testes das buscas diretas, colisão de documento e exportação autorizada.
5. Módulos consumidores: `custom_links`, `ma`, `accounting`, `goals` e pesquisas
   têm consultas diretas/aggregações. Exigir predicado explícito, teste de entrada
   com X/Y/legado e versão/migration nova se houver mudança funcional. Não basta
   filtrar `Clients_model` se o consumidor usa SQL próprio.
6. Portal: a identidade autenticada do próprio contato já é validada sem logout
   indevido; mutações de perfil, documentos privados e demais rotas precisam de
   contexto e testes próprios. Um link empresarial nunca concede login/contatos,
   anexos, vault ou finanças por tabela global.
7. Concorrência MySQL: testar criação duplicada, revogação de ACL/vínculo durante
   operação e escrita legada concorrente. SQLite e o lock do módulo não provam
   coordenação de writers que não participam desse lock.
8. Rebase final e migrations: preservar 101/102 publicadas e a permissão isolada103, aplicar a evolução
   local104 e escolher core migrations acima da release atual somente após os
   merges. Executar upgrade real e CI do commit composto.

O inventário executável `scripts/audit-issuer-isolation.py` acompanha alterações,
mas não é um parser SQL nem prova cobertura completa. `--assert-ready` falha de
forma deliberada. Nenhum checkbox administrativo ou parâmetro HTTP desliga o gate.

## Evidência local desta etapa

PHP WASM 8.1/8.5, dados sintéticos, sem rede:

- `tests/client_issuer_document_writers.php`: 164 verificações com cinco models
  reais e SQLite, incluindo retarget por hook em add/update e proposta parcial.
- `tests/client_issuer_scope.php`: 27 verificações, incluindo coluna futura.
- `tests/invoice_issuer_exports.php`: 9 verificações dos SQLs reais.
- Regressão composta: 46 suítes PHP em cada runtime executaram verificações;
  o ensaio completo de upgrade MySQL de `invoice_issuer_upgrade.php` não foi
  executado localmente (apenas sua prova de schema SQLite). A suíte independente
  de recorrências MySQL também informou ausência do banco dedicado.
- Ciclo dos módulos consumidores: 133 verificações por runtime e regressão de
  tracking nos três módulos. O core agora exige marcador persistido confirmado.

Os resultados acima não substituem MySQL/CI, navegação autenticada, plugins reais
ou homologação de operação financeira. Publicação/merge/deploy não fazem parte
deste checkpoint local.
