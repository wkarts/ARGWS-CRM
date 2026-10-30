# Reconciliação447 — tarifa240 e evidência LOCAL sem caixa

Pai publicado b47d1666f7bb788bf29ead9c9aa03ff3f36221d1, árvore5a49b5b731abbce3b18581badd19cc6aac9c3d90. Preserva439–446, incluindo os reparos445 dos dois bloqueiosCI444, inventário e comparação completa de metadados do runnerBB corrigida446. Não refaz os reparos deCI já resolvidos.

Núcleo4.4.7/447: migration nova cria exatamente company_local_noncash_declarations, company_local_noncash_restorations e company_local_noncash_settlements. Schema, colunas, índices, nulabilidade, collation e FKs são verificadas; a reversão preserva evidência financeira. BB1.2.3/123 exige núcleo4.4.7; migration123 éNOOP de validação dos quatro participantes anteriores. Asaas1.5.3/153 e todos os seus bytes permanecem iguais ao446. Nenhuma migration publicada é editada.

## Proveniência

TarifaCNAB240 foi reconstruída e revisada em pacote independente. A revisão1 permitia alterações no hash do lote, remoção da declaração manual de remessa e remoção da auditoria de componentes antes do recibo. Os REDs reais foram preservados. A revisão2 sela e reconfirma todo o frame: lote, membros, bytes regenerados, origens, contratos, snapshots, ações e vínculos; após o último audit valida pagamento, componentes e journal. Replays antigos não adotam uma origem alterada.

Os67paths de NoncashV2 não foram recuperados integralmente neste ambiente. Logs/scripts/hashes anteriores e o snapshot recuperável do candidate445 foram preservados; duas buscas de recuperação não localizaram o pacote. NEWV3 é uma reimplementação explícita, não uma alegação de equivalência aoV2 perdido. O pacote original46paths recebeu rótulosGUI e correção de leitura bearer após lock; o manifesto do autor revisão2 permite sua reconstrução, mas a integração conserva as alterações446 de calendário e447 de tarifa nos arquivos compartilhados.

## Comportamento

| Fluxo | Resultado | Limite |
|---|---|---|
| CNAB240 tarifa integral | Ocorrência06/carteira17simples; principal105,53, tarifa2,53, líquido103,00; tarifa observacional própria em componentes, journal, PDF/portal/report/CSV | Novo grant e consentimento separados, defaultOFF; juros/desconto/abatimento/IOF/outros zero, pagamento integral. Não cria despesa fiscal |
| API16 LOCAL sem caixa | Observação final validada com pago/líquido zero e desconto+abatimento=nominal; declaração integral própria extingue principal, sem pagamento/crédito fabricados | Exige origem, conta, consulta e consentimentos independentes; não autentica baixa ou quitação pelo banco |
| Restauração LOCAL | Reabre dívida preservando declaração original, sem caixa | Append-only, com origem/fingerprint/atores atuais e last-audit revalidados |
| Fundos após restauração | Recebimento manual independente, crédito nativo real, desaplicação e reversão pelos writers canônicos | Não reutiliza o próprio hash de declaração/restauração como prova de fundos; correlação fechada e revisão financeira atual |
| GUI/API/leitores | Sete scopes próprios, ACL atual, A/B, PDF, portal, relatórioCSV e ledger datado | GUI exige CSRF/contexto; bearer exige credencial própria, nunca sessão de navegador |
| Recorrência | Conserva predicate principalPaid existente: status2 e recebível0. LOCAL ativo satisfaz, restauração bloqueia sem avançar claim; fundos independentes reabilitam | Não inventa nova regra de recorrência; day/week/month/year e ciclos já publicados439/440 permanecem |

## Falhas encontradas durante a integração

A leitura bearer resolvia o token antes da espera pela transação. A correção vincula o segredo à conexão/prefixo exatos, inclui tabelas token no owner boundary e revalida após adquirir lock, após audit de uso e antes de devolver DTO. Dois casos com dois processos novos esperando cada um comprovam revogação/expiração; falha após audit faz rollback exato. GUI sem bearer conserva o caminho próprio.

A primeira execuçãoHTTP revelou duas falhas de integração distintas: o CJS de tarifa consultava paid_amount/net_amount como colunas, mas elas são campos de observation_json; a consulta foi corrigida mantendo valores exatos. O POST da API LOCAL não constava na lista nativa CSRF, resultando403HTML antes do controller. O RED foi capturado com bearer inválido e sem executar writer. A correção se limita à rota numérica exata e exige provas de que GUI, IDs inválidos e sufixos continuam protegidos. Os bancos/objetos criados antes dos REDs são preservados; novas fixtures usam writers nativos com chaves novas, sem zerar dadosSQL.

## Revisão final de proveniência da tarifa

O gate da composição f843 confirmou seis REDs entre requisições: alteração de created_at em recibo/revisão/auditoria BB e remoção de auditorias nativas de pagamento/componentes/journal. O replay devolvia o mesmo pagamento, sem duplicar caixa, mas aceitava a proveniência adulterada. A correção fixa um instante próprio antes do writer, inclui-o no evidence_hash e usa-o nos três registros BB. O review_key sela o frame financeiro nativo; auditorias também são verificadas semanticamente antes do primeiro selo. Os 23 casos finais cobrem oito origens, três timestamps e doze mutações de auditoria core; não curam dados alterados nem criam novo pagamento.

Esse protocolo ainda era somente local, sem recibos dessa base publicados. Não há fallback para o candidato sem selo. Os bancos HTTP f843 e seu código foram preservados como evidência anterior; o gate final usa novos bancos, instalação fresca447 e upgrade real446→447. As migrations e as bases publicadas anteriores permanecem intactas.

## Pendências reais

Reversão da nova base tarifária exige contrato próprio de movimento, principal devolvido, tarifa devolvida/retida e débito líquido; fica bloqueada. IOF/outros, parciaisAPI18/19, nominal/protesto/negativação e vertical400 operacional não são anunciados como prontos. Campos/documentos bancários indispensáveis constam em banco-brasil-remaining-contracts.md. Prioridade/autofollow/regras extras de timers e demais consumidores/settings sem adaptação permanecem escopo técnico aberto, não são chamados de mera homologação.

Validação completa, SHA publicado e resultados de CI estão no relatório447 e naPR45. Nenhum merge/deploy/force/tag/release ou ação bancária real é autorizado ou executado.
