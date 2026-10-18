> Transferência em andamento: consulte [banco-brasil-handoff.md](banco-brasil-handoff.md). Core 4.0.3/403; módulo 1.0.2/102; sem conclusão operacional.

# PR draft: Banco do Brasil 1.0.2, perfis CNAB 240/400 e revisão de retornos

## Objetivo

Iniciar o módulo BB com contratos explícitos, codecs restritos conferidos contra manuais primários, importação observacional segura e interface administrativa. API de cobrança e ciclo operacional CNAB permanecem pendentes; esta PR não representa o pedido completo nem homologação.

## Mudanças

- Módulo instalável 1.0.2 + migrações nativas 101 e 102, oito tabelas InnoDB, instalação/upgrade pelo mesmo executor. A migração101 publicada não é reescrita.
- Escopo imutável emitente/conexão/conta/ambiente/convênio, sem herança global.
- Permissões BB e emitente combinadas, POST/CSRF próprio, cadastro autorizado e UI em oito guias.
- Codec240 P/Q e parser T/U restritos, centavos exatos, CNPJ alfanumérico, contadores/versões/escopo estritos.
- Retorno400 CBR643 (0/7/9), candidato CBR641 indisponível operacionalmente, campos monetários separados e datas DDMMAA com intervalo explícito.
- Perfis imutáveis escolhidos pela interface por conexão: não há fallback entre formatos nem inferência por região. Históricos101 permanecem sem vínculo, sem reclassificação retroativa.
- Retorno durável apenas para revisão, deduplicação com conflito de payload, proveniência por arquivo/evento/lote/sequência e auditoria atômica.
- Recusa de baixa operacional/cheque pendente como pagamento; nenhuma alteração de fatura/recebimento.

## Evidências e correções de revisão

A revisão independente reproduziu omissão de moeda/despesas/créditos, perda de proveniência no reempacotamento, colisão por padding de controle e cadastro de conta zerada. Todos receberam regressões e correções antes da publicação. Fontes, hashes, posições e limites estão em [banco-brasil-cnab240.md](banco-brasil-cnab240.md) e [banco-brasil-cnab400.md](banco-brasil-cnab400.md). No400, o código BRL da remessa e o picture divergente do total do trailer permanecem pendências explícitas de confirmação com o banco, sem valores inventados.

## Testes

- PHP 8.1 e 8.5: 412 verificações distintas, incluindo codecs, store/transações, controller/view e migrações nativas. Lint33 arquivos PHP e diff-check verdes.
- CI configurado com MySQL 8.4, PDO e driver mysqli CI 3 real; duas conexões/processos com evidência de lock pelo Performance Schema.
- Checkpoint101: head `ccd5eb5063a2f230f40f26292728a8eea8efb613`, [CI específico verde](https://github.com/wkarts/ARGWS-CRM/actions/runs/39103085114), inclusive26 testes PDO e35 testes mysqli/concorrência.
- **Versão102: CI/MySQL ainda pendente. O verde101 não comprova102.** Registrar o novo SHA e os resultados antes de encerrar a revisão.

## Dependências e impacto

- Nenhum gateway existente é alterado.
- Novas importações240/400 exigem o perfil registrado e selecionado explicitamente. Históricos anteriores continuam legíveis sem perfil. Ambiente declarado não é prova de origem remota; o CBR643 não fornece marcador inequívoco.
- A configuração funcional fica no menu nativo; credenciais futuras da API também deverão ser configuradas visualmente com proteção e auditoria.
- Permissões nativas por recurso/empresa dependem de `issuer_resource_can`, da adaptação pendente ao núcleo Empresas. Capabilities `view/manage/import` são verificadas separadamente como autorização contextual completa, sem veto global; não há fallback `manage_integrations` ou bypass de admin comum. Master depende da identidade explícita de instalação no núcleo. O checkpoint ainda referencia o consumidor histórico issuers; sua substituição está pendente e não é liberada por fallback.
- Sem endpoint operacional de geração/download/transmissão. Schema futuro de origens/lotes não equivale a integração implementada.
- OpenAPI primária atual indisponível durante pesquisa; API, OAuth, certificado e webhook não foram preenchidos com suposições.
- Somente observações; não muda política de CONFIRMED/RECEIVED do Asaas nem a contabilidade.

## Segurança e rollback

Sem credenciais reais, arquivos bancários reais, boletos, envio ou deploy. Nenhuma chamada bancária de escrita. Desativar o módulo/revogar concessão interrompe uso, sem apagar auditoria. Sem downmigration destrutiva; dados devem ser preservados e backup validado antes de manutenção.

## Checklist

- [x] Módulo/versionamento/migração nativos
- [x] Escopo e permissões explícitos
- [x] Fontes primárias e limites documentados
- [x] Fixtures sintéticas; revisão independente concluída para101
- [ ] Revisão independente da evolução102
- [ ] MySQL real/concorrência verde no SHA da PR
- [ ] Especificação OpenAPI atual e executor API
- [ ] Isolamento financeiro do core e orquestração de emissão
- [ ] Geração/download operacional idempotente e auditado
- [ ] Homologação BB e política de conciliação/baixa aprovadas
