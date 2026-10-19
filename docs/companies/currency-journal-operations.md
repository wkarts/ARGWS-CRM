# Moeda histórica e livro de eventos por empresa

A migração 426 acrescenta unidades monetárias congeladas e evidência dos novos eventos financeiros. Não reconstrói documentos ou movimentações do ambiente legado sem origem definida. Não é livro contábil fiscal, conciliação bancária ou comprovação de disponibilidade de recursos.

## Moeda

`company_currency_units` congela a relação entre ID da moeda e código ISO de três letras. Leituras de documentos de empresas nomeadas usam esse registro; renomear o catálogo global não transforma um histórico BRL em USD. Novas mutações financeiras recusam catálogo divergente. Edição/remoção do catálogo também participa do bloqueio financeiro: IDs usados por documentos nomeados não podem mudar de significado; restaurar exatamente o ISO congelado é permitido. Moedas exclusivamente legadas continuam no fluxo anterior.

A implantação captura somente os IDs efetivamente usados em documentos nomeados, vendas e recorrências com empresa registrada. O fundamento `observed_at_upgrade_not_original_proof` significa que o valor foi observado na implantação; a moeda original anterior não pode ser recuperada com certeza. Novas emissões capturam `captured_at_issue`. O ID e a unidade congelada permanecem mesmo diante de remoção externa indevida do catálogo. Não há adoção de faturas `issuer_id=NULL`.

`CompanyCurrencyUnitSchema::ensure()` precede `CompanyCurrencyUnits::captureObservedUsed()`. Nos escritores, `assertWrite(currencyId)` exige a transação de `IssuerTransaction`, com `issuers_lock`, `currencies` e `company_currency_units` entre os participantes. Não abre nem confirma outra transação. Leitores autorizam proprietário e acesso antes de `resolveRead(currencyId)`.

## Evidência dos eventos

`company_financial_events` armazena empresa, ator, fonte nativa, tipo, magnitude decimal exata, moeda e seu fundamento, data de competência explícita, snapshots estruturados anterior/posterior, chave de evidência, sequência própria da empresa, hashes e instante UTC do registro. A sequência é atribuída sob o bloqueio financeiro. Nenhuma credencial de transporte é aceita no payload.

`CompanyFinancialJournal::recordInOwned(issuer, actor, event, evidenceKey)` participa da mesma transação do escritor, recibo de idempotência e auditoria. Falhar o evento ou a auditoria desfaz a mutação. `tables(sourceType)` informa os participantes adicionais. A mesma chave e conteúdo retornam o evento existente; conteúdo diferente conflita. Reversões são eventos separados, preservando a magnitude original. A verificação dos hashes antecede a agregação e a exportação.

A implantação usa `captureObservedBaselines()` somente após congelar as unidades monetárias. Esse registro tem ator de sistema e fundamento `observed_current_not_original_proof`. Ele informa o estado observado; não inventa datas de alterações, pagamentos ou reversões anteriores. Observações ficam fora dos totais de eventos comprovados.

Para despesas novas, criar, editar e cancelar produzem eventos na transação canônica. A edição conserva os valores e datas anterior/posterior; a posição ajustada remove o valor da data anterior e acrescenta o novo na data informada. Cancelamento sem data informada registra o dia UTC efetivo da operação, preservando a data original no snapshot. Anexos e categorias não são movimentos monetários. Tarifas vinculadas a evidência de provedor não podem ser editadas por baixo do vínculo.

Faturas novas registram `invoice_issued`; editar e cancelar registram `invoice_updated` e `invoice_cancelled`, com revisão, total e datas anterior/posterior. Recebimentos registram `receipt_recorded` pelo valor nominal original e `receipt_reversed` por cada magnitude revertida. A projeção nativa pode chegar a zero sem apagar o recebimento original. Notas de crédito registram emissão/cancelamento e eventos separados para aplicação, desaplicação, reembolso e reversão de reembolso. Cancelamentos sem data de operação usam o dia UTC do registro, preservando a data original do documento no snapshot.

Esses eventos pertencem à mesma transação dos escritores canônicos, da numeração, dos snapshots e dos recibos de idempotência. Uma falha tardia desfaz também a captura de uma moeda ainda não utilizada. Repetir uma operação já confirmada devolve sua origem original e não cria outro evento. A leitura histórica continua usando o ISO congelado mesmo se o catálogo tiver sido adulterado; uma nova mutação é bloqueada até que o catálogo volte a corresponder à unidade capturada.

## Relatório e CSV

O livro usa o mesmo conjunto de permissões da posição financeira: relatórios, faturas, pagamentos, notas de crédito e despesas, todas na empresa selecionada. A origem é aplicada antes das fontes e agregações. A posição financeira atual continua separada do livro de eventos.

O filtro é por data de competência inclusive, com até 366 dias; o corte de conhecimento usa instante UTC. Links de continuação preservam o corte e uma sequência máxima própria da empresa (`watermark`). Uma página contém até 100 eventos e o período aceita até 10.000 eventos. A cobertura compara as fontes atuais da empresa com os eventos capturados e observados, sem declarar que as contagens atuais existiam no instante histórico consultado.

Totais são separados por ISO e distinguem nominal original de valores após alterações/reversões conhecidas até o corte. Uma reversão em outro dia pode produzir movimento ajustado negativo nesse dia. Isso não representa saldo líquido bancário. A cobertura mostra explicitamente domínios indisponíveis, observações e fontes sem evidência original. Histórico completo só pode ser declarado quando essas lacunas não existem.

O CSV contém a página solicitada, com empresa, fonte, sequência, datas de competência e UTC, moeda/fundamento, magnitude, snapshots de total e hash. A auditoria do arquivo é confirmada antes de retornar bytes. Falha de autorização, integridade ou auditoria bloqueia a resposta inteira. Não existe nova permissão automática para tokens de API anteriores; esta entrega expõe o livro na área administrativa autorizada.

## Verificação

Testes portáveis cobrem moeda congelada, catálogo divergente/restauração, preservação de NULL, idempotência, hashes, origem cruzada, rollback de falhas, separação nominal/reversão, data de edição/cancelamento, corte e sequência de paginação e auditoria antes de CSV. A execução SQLite valida lógica local; os testes InnoDB e HTTP/Chromium registrados na PR validam integração e concorrência. Nenhum desses testes substitui homologação de API/CNAB com o banco ou confirmação de pagamentos externos.
