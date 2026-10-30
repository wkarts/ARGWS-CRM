# Respostas em voo após revogação — correção do preflight

O guard antes do despacho continua obrigatório. Entretanto, revogar token/actor/módulo depois de um POST não desfaz o efeito remoto nem deve apagar a evidência técnica retornada. A revisão detectou esse ponto nos primeiros checkpoints157/125; a correção é local e separável, sem novo DDL.

- **BB125:** conserva HTTP, hash e projeção normalizada sob o mesmo claim_token/actor/operação/origem imutáveis. Estado da operação permanece unknown, com wrapper technical_only; não validated. Origin de issue fica api_unknown. Estado terminal anterior não é apagado por query tardia. Nenhum payload bruto, token ou pagador bruto é gravado.
- **Asaas157/158:** conserva apenas projeção validada contra o snapshot do despacho e hash da resposta (ou hash+marcação unverified se não validável), em proof state=quarantined. Origin fica uncertain, preservando barreiras anteriores de revisão/remoção. A compensação verifica origem/meta/policy imutáveis, mas não exige uma autorização atual para guardar resultado do despacho anterior. Não produz prova received_observed, job ou pagamento. Segredos não entram no journal.
- **Ponte:** status permanece somente leitura; IDs de provas em quarentena ou operações BB não validadas não são anunciados como proof_id elegível. Nova emissão/query/reconciliação continua exigindo consentimento e ACL vigentes. Repetir issue não repete POST. Evidência técnica não vira recebimento pela mera reativação do módulo.

Fixtures novas cobrem revogação entre resposta e persistência, inclusive actor/módulo Asaas desativados, retenção do identificador/hash, ausência de pagamento/fila e impossibilidade de usar a prova em quarentena. PHP/MySQL **não executados localmente**; testes estruturais não substituem execução. Nenhuma chamada financeira real.

A observação e obtenção de instrumentos Asaas revalidam consentimento/ACL/snapshot entre leituras HTTP; revogação durante identificação bloqueia o GET posterior de pagamento (fixture com contador zero).
