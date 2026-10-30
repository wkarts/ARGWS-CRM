# Catálogo oficial: modal sem dependência de hooks globais

Base: 964e729df171f01716978003fefe062868679c3b. O run [40448163021](https://github.com/wkarts/ARGWS-CRM/actions/runs/40448163021), job122004286608, concluiu a interação com o filtro original de Chamados: seleção do assunto pelo link Bootstrap Select, preenchimento, POST200 e aaData. A stack avançou e falhou em GET /admin/invoice_items500. Registrou também1 erro JS em Chamados; sua causa não foi identificada nem declarada corrigida.

Artefato [12710005387](https://github.com/wkarts/ARGWS-CRM/actions/runs/40448163021/artifacts/12710005387), ZIP SHA256 informado pelo CI: 4da0f905d80b00b264d8792d9c56802129977c2f8b559547d151678dd79746f9. ZIP não extraído/conferido visualmente sem executor.

## Correção funcional

O caminho foi revisado em conjunto: Invoice_items, NativeCompanyCatalogUi/Catalog/Legacy, referência de moedas, manage/item, helpers e autoload. A view manage já evita hooks globais no escopo nativo; o modal item ainda chamava before_invoice_item_modal_form_close incondicionalmente. O autoload isolado retorna antes de carregar action_hooks. A chamada agora segue o mesmo contrato do pai: executa apenas fora do escopo nativo. Nenhum hook/provider/modelo global é carregado para contornar a dependência.

O form_close ficava dentro da div modal-footer, embora form_open viesse antes da modal-body. Foi movido depois do fechamento da modal-footer e antes de modal-content: um único formulário equilibrado, mesmas actions/fields/CSRF/revision e componentes oficiais. O retorno HTTP e os CRUD ainda dependem da próxima stack; não se atribui a falha500 conclusivamente sem essa prova.

## Prova exigida

O smoke mantém todas as assertions anteriores e acrescenta13 linhas de assertions no fluxo real de itens: DataTable POST, modal original, criação, leitura por identidade de transporte, edição com avanço de revisão, persistência somente na secundária, ausência na principal e409 para formulário antigo após troca. Sem chamadas financeiras/SMTP/IMAP externas. Não abre uma interface concorrente.

O diagnóstico de erro JS também reconhece apenas códigos de fronteira fechados e categorias conhecidas de DataTables. Mensagens, linhas de dados, URLs, tokens e credenciais não são impressos. Guard zero erros mantido, assim como timeouts, cobertura e segurança.

Parser JavaScript disponível passou no smoke;142 linhas de assertions (129 anteriores preservadas). PHP/Node/bancos/Playwright locais não executados: executor indisponível. Um único arquivo funcional de produção mudou, além de smoke/documentação. Os2 digests de inventário devem ser reconciliados pelo cálculo oficial do CI; contadores e operational_ready=false permanecem. PR52 continua em rascunho, sem aceite integral, sem merge/deploy/force-push.
