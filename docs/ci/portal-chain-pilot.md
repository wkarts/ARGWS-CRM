# Experimento temporário de particionamento do smoke

O piloto da cadeia de seis testes portal/projetos foi introduzido no SHA
`56a99618`, run Stacks `40065855624`, para medir um ramo isolado sem retirar
qualquer cenário serial. Esta revisão remove seus jobs, agregador e helpers
temporários: restaura o job obrigatório `validate` e as 100 invocações originais,
na mesma ordem, preservando a correção/preflight Asaas e o balanceamento Empresas.

## Evidência e limites

Rodada terminal: [Stacks 40065855624](https://github.com/wkarts/ARGWS-CRM/actions/runs/40065855624),
SHA `56a99618`. O job piloto `120788840262` passou. O serial `120788840375`
passou pela cadeia de seis, mas falhou depois na navegação mobile Asaas; portanto
não houve sucesso integral de Stacks e o agregador não executou a comparação.

A comparação oficial foi executada **localmente sobre os dois artifacts** com
`tests/ci/portal_evidence.py` idêntico ao publicado: exit 0, `FOOTPRINT_MATCH`,
`semantic_equivalence_proven=false`, `serial_removal_authorized=false`.
PHP 8.3.35/MySQL 8.0.46 em ambos. Sentinelas iniciais: uma identidade de instalação,
122 faturas, sete notas de crédito e seis recebimentos nativos, preservados em cada
execução. Os seis cenários concluíram em ambas as execuções:

| Cenário | Serial (s) | Piloto (s) |
|---|---:|---:|
| Portal de tarefas |89|144|
| Escrita de tarefas no portal |46|73|
| Marcos |51|81|
| Apontamentos |67|108|
| Gantt |83|136|
| Arquivos de contato |36|58|
| **Cadeia completa** |**372 (6m12s)**|**600 (10m00s)**|

São medições em runners distintos, não uma comparação causal nem demonstração de
ganho: o ramo piloto foi mais lento nesse trecho. Não houve teste diferencial dos
62 cenários posteriores com/sem os efeitos da cadeia; a política B impede afirmar
um corte transparente.

Recibos preservados para reprodução antes de remover os helpers:
- ZIP serial, digest verificado pelo integrador:
  `33192ae48802a8d0ed282e8e3e534246090cab83d28efde4aa1403585a4b0f9a`.
- ZIP piloto, digest verificado pelo integrador:
  `add2680c6c3ded611c07e272e74450adccf4a5b7f8d5f0f05268e99c0d5d6955`.
- `proof.json` serial, SHA256 conferido localmente:
  `5a7bad6263f0a647f685dbdab099b39b636b55cd0906a18d5bcb404bff0018f5`.
- `proof.json` piloto, SHA256 conferido localmente:
  `d7928100d55ed9c5b70d694fe1083ce4a785806ca401f81d926f6f7dac3fc4a3`.

Separadamente, [Empresas 40065855623](https://github.com/wkarts/ARGWS-CRM/actions/runs/40065855623)
passou em 24m43s com 944 registros e os gates completos. O balanceamento é preservado,
mas não se atribui toda a redução observada a ele: runtimes/VMs variam e o
contrafactual da própria rodada não demonstrou vantagem do plano balanceado.
Essa medição não transforma a falha Stacks em sucesso.

`FOOTPRINT_MATCH` verificava preservação intrarun e correspondência estrutural,
não equivalência semântica. IDs, timestamps, chaves e salts não foram normalizados
para fabricar igualdade. A investigação identificou acoplamento concreto:

- Portal altera a política de arquivos da empresa B de 0 para 1, além de revisões
  e auditoria; calendário e recorrência posteriores preservam configurações.
- BB reutiliza conexões, reservas e perfis entre originação, componentes,
  alterações 439, CNAB400, tarifas e numeração.
- Asaas remote/checkout dependem das contas anteriores; outras rotinas reutilizam
  contas/origens, e revisão pós-estorno participa da cadeia TLS compartilhada.

A cauda de cinco testes compras/RH tem preparação própria e nenhum consumidor
posterior, mas os três testes de compras compartilham `purchases-proof.json`.
É apenas um candidato de menor acoplamento, ainda sem corte seguro demonstrado.

## Trabalho separado, autorizado e pendente

A otimização estrutural do smoke continua pendente em frente própria, não como
piloto permanente nesta PR. Exige medir o caminho crítico, provar pré-requisitos
e sentinelas populadas, validar relações/ACL/finanças/arquivos e executar teste
diferencial do downstream antes de retirar qualquer dependência. Não usar cache
mutável compartilhado, snapshot apenas de banco ou redução de assertions/esperas.
Nenhuma mudança de produção ou homologação bancária decorre deste experimento.
