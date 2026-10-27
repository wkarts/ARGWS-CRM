# Reconciliação oficial dos inventários após o bootstrap nativo

Fonte funcional congelada:9484591dbcd0914f8a7a685f8b70f05e9c040d51. Este incremento altera apenas os dois resumos JSON e este documento, fora das raízes inventariadas; os hashes calculados pelo CI continuam aplicáveis à mesma árvore funcional.

- Gerador PHP oficial em PHP8.5 e8.1: workflow focusedPR40447083482, jobs122000810770/122000811024. Ambos calcularam source_manifest_sha256=4036bebe8d0c9c512ab0f9657cd3388fd5827555e9e12b96ec20f9e028bf6a9b.
- Workflow Interface nativa40447083457/job122000810696 varreu a árvore completa e gerou o resumo no runner e comparou byte a byte. O diff foi exclusivamente o campo source_manifest_sha256. Contagens idênticas:38 módulos,4611 fontes,681 chaves literais,158 dinâmicas,255 controllers,555 hooks,70 jobs,296 reports,908 tabelas e12539 chamadas candidatas de banco.
- Gerador Python oficial: Empresas40447083709/job122000811757, após os dois testes do próprio inventário passarem. source_sha256=7b0678c832eb92d54b1d513fcbbd7a8b6801f4373675e7806f5c9e49481bd0f6. Os arquivos de produção foram modificados nos mesmos caminhos:15085 arquivos/unresolved, sem revisão aceita ou remoção fictícia.

Os jobs de preflight falharam com exit1 quando o resumo antigo divergiu; isso foi preservado, não desligado. A atualização usa o cálculo oficial da árvore inteira, não hashes presumidos de mocks ou cópia de inventário antigo. Nenhum ready foi habilitado, nenhuma contagem reduzida, auditor/censo de ações sem mudança:48 controllers paralelos/51 views com shell ainda pendentes.

PHP/Node/banco/navegador e regeneração física locais não executados porque o executor está indisponível. Novo CI do SHA publicado deve confirmar o match e executar os checks restantes; o resultado de preflight não é aprovação funcional. Os testes de interface da base pararam antes da renderização por causa da diferença do resumo; não foram alegados aprovados.

PWAa5ba730, Banco do Brasil1.3.2/132, arquivos bloqueados e migrations históricas preservados. Aplicação única/integrações/CNAB400 e demais aceites permanecem incompletos. PR em rascunho, operational_ready=false.
