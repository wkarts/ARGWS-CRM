# Campos do catálogo e linhas de fatura conservam identidades distintas

Basee4f9833f4370e5d436aac5ecb3d6278c05063d77. Na stack63 [40454320653](https://github.com/wkarts/ARGWS-CRM/actions/runs/40454320653), job122023986859, passaram GET200, DataTable200, modal pronto, validade HTML de17.50 e **criação real POST200**. Zero erros JS. A próxima leitura do mesmo item retornou500; edição/stale form/Staff não foram executados.

Artefato [12711928641](https://github.com/wkarts/ARGWS-CRM/actions/runs/40454320653/artifacts/12711928641), ZIPsha256 informado17ae8a9bce86eead00c8aad6ed3eb68c153be4e31e1726481b025e6cebdf5171; ZIP não extraído localmente.

## Correção funcional no helper existente

render_custom_fields recebia belongs_to=items/items_pr=true do controller original. A leitura dos valores já usava items_pr; a leitura de definições para recurso ainda usava items, que resolve uma linha de fatura e rejeita corretamente um ID de company_item. Isso também falhava sem campos definidos, pois a autorização antecede a seleção.

A correção escolhe items_pr para essa leitura específica de catálogo. NativeCompanyCustomFields já mapeia definitions items_pr→items e resolve o recurso pelo namespace correto. Não altera o decodificador, a ACL, o domínio, o banco, o histórico nem admite um ID de item como linha de fatura. Ramos explícitos de seleção pelo editor de fatura permanecem antes desse ramo e continuam usando a autoridade do editor.

O teste existente agora usa o helper de produção, em vez do double render_custom_fields, com PDO real. Inclui item sem campos, valor persistido/escapado, namespaces incompatíveis, ID bruto da principal, item de outra empresa e issuer revogado. Helpers puros de formulário continuam seams explícitos; não é prova de navegador.

## Fixture do controller

Focados e4 pararam no196/522: o teste usava catalogPageOwner, enquanto get_instance já era definido pelo suporte e retornava GLOBALS[CI]. O parent double passa agora a registrar o mesmo controller nos dois lugares, reproduzindo o framework. A assertion de que o loader é diferente/não autorizado permanece; não torna nativeFor permissivo. A barreira de hooks usa o objeto global real do suporte, pois declarar uma função condicional não substituía a função já existente.

Mantidas as assertions de catálogo completo contra SQL independente, revogação durante renderização, ausência de HTML parcial, binding do loader, A/B/câmbio, preço e formulário único. Não remove casos nem muda limites de memória/timeout.

Novos testes PHP/helper/navegador ainda não executados; executor local indisponível. PWA/arquivos bloqueados intactos; sem chamadas externas. Auditor atualiza somente hash do helper, permanecendo unreviewed/rollout blocked/647 itens. Inventários agregados dependem do gerador oficial para este novo código. operational_ready=false; unificação integral/CNAB400 e aceite continuam pendentes. PR52 rascunho, sem merge/deploy/force-push.
