# PR draft: Banco do Brasil 1.0.1, CNAB 240 restrito e revisão de retornos

## Objetivo

Iniciar o módulo BB com contratos explícitos, codec verificado contra manual primário, importação observacional segura e interface administrativa. API de cobrança e ciclo operacional CNAB permanecem pendentes; esta PR não representa o pedido completo nem homologação.

## Mudanças

- Módulo instalável 1.0.1 + migração 101 nativa, sete tabelas InnoDB, instalação/upgrade pelo mesmo executor.
- Escopo imutável emitente/conexão/conta/ambiente/convênio, sem herança global.
- Permissões BB e emitente combinadas, POST/CSRF próprio, cadastro autorizado e UI em oito guias.
- Codec P/Q e parser T/U restritos, centavos exatos, CNPJ alfanumérico, contadores/versões/escopo estritos.
- Retorno durável apenas para revisão, deduplicação com conflito de payload, proveniência por arquivo/evento/lote/sequência e auditoria atômica.
- Recusa de baixa operacional/cheque pendente como pagamento; nenhuma alteração de fatura/recebimento.

## Evidências e correções de revisão

A revisão independente reproduziu omissão de moeda/despesas/créditos, perda de proveniência no reempacotamento, colisão por padding de controle e cadastro de conta zerada. Todos receberam regressões e correções antes da publicação. Fontes, hashes, posições e limites estão em [banco-brasil-cnab240.md](banco-brasil-cnab240.md).

## Testes

- PHP 8.1 e 8.5: codec, store/transações, controller/view e migração nativa.
- CI configurado com MySQL 8.4, PDO e driver mysqli CI 3 real; duas conexões/processos com evidência de lock pelo Performance Schema.
- Registrar o SHA publicado e resultados reais dos jobs aqui. **MySQL ainda pendente, não chamar CI verde antes da execução.**

## Dependências e impacto

- Nenhum gateway existente é alterado.
- Capacidade `manage_integrations` depende da fase 103 de emitentes, default negado. O módulo instalado isoladamente não concede essa permissão.
- Sem endpoint operacional de geração/download/transmissão. Schema futuro de origens/lotes não equivale a integração implementada.
- OpenAPI primária atual indisponível durante pesquisa; API, OAuth, certificado e webhook não foram preenchidos com suposições.
- Somente observações; não muda política de CONFIRMED/RECEIVED do Asaas nem a contabilidade.

## Segurança e rollback

Sem credenciais reais, arquivos bancários reais, boletos, envio ou deploy. Nenhuma chamada bancária de escrita. Desativar o módulo/revogar concessão interrompe uso, sem apagar auditoria. Sem downmigration destrutiva; dados devem ser preservados e backup validado antes de manutenção.

## Checklist

- [x] Módulo/versionamento/migração nativos
- [x] Escopo e permissões explícitos
- [x] Fontes primárias e limites documentados
- [x] Fixtures sintéticas e revisão independente
- [ ] MySQL real/concorrência verde no SHA da PR
- [ ] Especificação OpenAPI atual e executor API
- [ ] Isolamento financeiro do core e orquestração de emissão
- [ ] Geração/download operacional idempotente e auditado
- [ ] Homologação BB e política de conciliação/baixa aprovadas
