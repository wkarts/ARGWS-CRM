# Reconciliação completa dos manifests — fonte4707c08

Fonte 4707c088ec8ce928510f057def30e5cf6875567d (BB1.3.4/migration134). Este filho altera somente documentos e a frase final do teste SQL; nenhum código de produção ou raiz inventariada muda.

[NativeUI40462534842](https://github.com/wkarts/ARGWS-CRM/actions/runs/40462534842), job122050244139, gerou o inventário operacional completo e confirmou diff apenas de source_manifest_sha256: d511a4e3c87662cc31f8dcbd26d60bf86963162b4b73aa930112f47700c4e28e. Os registros individuais, versão e contadores anteriores já coincidiram:38 módulos,4616 fontes,255 controllers,909 candidatos de tabela; nenhuma classificação promovida.

[Empresas40462534668](https://github.com/wkarts/ARGWS-CRM/actions/runs/40462534668), job122050244024, executou os testes Python e informou source_sha256 fe2c109cb7da55cdf1d7995127940663f91cfb0711a49e2baeee38661868f2ac. A comparação58eeadf→4707c08 confirmou exatamente5 arquivos novos nas raízes:3 bibliotecas e migrations133/134. Pela classificação do gerador:15090 arquivos/unresolved,725 schema_history e84 module:banco_brasil. Demais categorias inalteradas. O novo check completo ainda confere o resumo serializado.

[BB40462534634](https://github.com/wkarts/ARGWS-CRM/actions/runs/40462534634): unidades PHP8.5/job122050243564 e8.1/job122050243595 aprovadas, incluindo lint do módulo, store/diagnóstico, admin/ACL e contratos históricos; checkout-presentation/job122050243437 aprovado. MySQL122050243600 e MariaDB122050243860 ainda em execução/fila no momento desta nota; não há aprovação do novo teste de rejeições declarada aqui. Banco usaPHP8.3 nesses jobs; não é prova de aplicação completa em8.1/8.5.

[Stack58 40460385818](https://github.com/wkarts/ARGWS-CRM/actions/runs/40460385818) concluiu os browsers nativos descritos em pr52-bb134-native-flow-review.md, mas falhou na expectativa antiga de Staff403 durante onboarding. Essa expectativa foi corrigida em4707 mantendo a negativa para invoices não adaptado e ampliando as positivas de Staff. A stack de4707 ainda precisa confirmar o fluxo posterior.

Focused58 passou423/522 antes do double sem output;4707 corrige o double e acrescenta exigência de JSON/no-store. Focados do novo filho, push e pull_request, ainda precisam executar com os manifests reconciliados. Não se considera o preflight anterior aprovado por editar somente o hash: NativeUI completo precisa passar também censo, assets e contratos, e Empresas precisa executar seus shards.

Executor local indisponível. V8 usado para sintaxe JavaScript e hashes individuais, sem alegação de PHP/Node/banco/browser locais ou extração de artefatos. PWAa5ba730 e arquivos protegidos preservados. Sem operações bancárias/SMTP/webhooks, merge/deploy/force-push. Nenhum cancelamento manual de workflows foi realizado por ferramenta indisponível.

operational_ready=false; PR52 em rascunho.48 controllers paralelos/51 views, regra automática de primeiro admin legado, CNAB400 integral, homologação/contratos e demais integrações continuam abertos. Os sucessos parciais acima não fecham esses requisitos.
