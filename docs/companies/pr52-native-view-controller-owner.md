# Proprietário real das views nativas

Base5d226ede1c69ad2af6d9bdbaacf3227f43032062. Revisão por conteúdo das159 views PHP alteradas existentes na PR (aplicação e módulo BB), sem erros de leitura, encontrou7 arquivos com checagens estritas de selo/CSRF passando $this.

## Contrato do framework

application/third_party/MX/Loader.php::_ci_load inclui o template dentro do loader. Portanto $this na view é o loader; get_instance() é o controller. CompanyRequestScope::sealedFor/nativeFor/nativeCsrfToken exigem exatamente o proprietário autenticado. O guard não deve aceitar um loader/DTO arbitrário.

As7 views oficiais agora passam get_instance(): announcements/announcement, currencies/manage, invoice_items/item/manage, tables/currencies, taxes/manage, todos/_todo. Isso permite emitir revisões/tokens corretos, escolher a referência de moedas por empresa e evitar caminhos legados indevidos. Não muda o selo, ACL, grants, CSRF, escopo de modelos nem resolve o problema aceitando qualquer objeto. Chamadas adminUrl que só leem config não são checagens de identidade e não foram alteradas.

No modal de itens, o teste errado selecionava o ramo de total_rows(clients) em vez do NativeCompanyCurrencyReference da empresa ativa; a restrição anterior do hook também usava o objeto errado. Esses caminhos foram corrigidos juntos. A atribuição final do HTTP500 depende da stack seguinte; não se declara o catálogo funcionando sem essa prova.

## Testes com binding correto

O fixture de apresentação agora inclui os templates num host distinto que encaminha propriedades ao controller, reproduzindo o binding relevante do MX_Loader. Antes ele incluía em método do próprio controller e não detectava esse contrato. Acrescenta assertion de que o host não tem o selo; mantém os guards de hooks/modelos/opções e as verificações anteriores.

O teste existente catalog_client_currencies conserva seus casos PDO/controller e renderiza o modal oficial num host distinto para duas empresas. Referência de moedas é real/PDO; helpers puros de formulário são doubles explícitos. Exige revisão, formulário único e moeda secundária somente para os clientes autorizados da empresa. total_rows global/hooks são proibidos e lançam erro se chamados. Isso complementa o navegador, sem alegar substituí-lo.

## Evidência e diagnóstico

[Stack5d40451634137](https://github.com/wkarts/ARGWS-CRM/actions/runs/40451634137), job122015465519, manteve zero erros JS e falhou ainda em invoice_items500. Artefato12710932879, ZIPsha256 informado pelo CI339c7a7b7433834f0c0070d32aad05b334ca9032c63bc9f44bd961c500243292; não extraído.

O script do smoke imprimia só categorias anteriores de logs. Acrescentou-se exclusivamente o formato fechado do catálogo introduzido em5d (classe, basename, linha, sem mensagens/SQL/valores/tokens). Não despeja logs privados nem altera resposta/erro/timeout.

Parser JS disponível passou; novos testes PHP, lint, shell e navegador dependem do CI existente, sem executor local. Auditor atualiza somente hash do modal e mantém unreviewed/647 itens/rollout blocked. Os hashes agregados operacional/fronteiras precisam ser calculados para este novo código; não usar digests de ee/5d. Censo/contadores permanecem, operational_ready=false e PR52 em rascunho. Sem PWA/arquivos bloqueados, cobranças/SMTP/webhooks reais, merge/deploy/force-push.
