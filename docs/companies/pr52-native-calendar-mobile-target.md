# Interação no cartão nativo em viewport móvel

Base d20bd6a282c7b2e1d414f2f06cc100e040160b0c. Stack40441967291/job121984173347: criação/feed passaram; antes do clique, event_found=true, click_handlers=1, has_href=false, native_body=true, small_body=true, sidebar_shown=false, view_handler=function, transport=function. Nenhuma requisição de calendário foi observada durante a espera e nenhum erro JS não capturado ocorreu. Artefato12707405971, ZIP SHA25691a77772fc56014095571d984bf9b48c5d36b306b66bcfb8ccaae45c3c4ad798, informado pelo CI; não extraído localmente.

O smoke manteve390px após o cenário de upgrade móvel e tentava clicar especificamente em .fc-event-title. O CSS distribuído FullCalendar5.6.0 define, em .fc-daygrid-dot-event .fc-event-title, flex-shrink:1, min-width:0 e overflow:hidden: o título pode não possuir área clicável embora o cartão com hora/ponto exista. A inferência será confirmada pelas medidas e pelo próximo clique HTTP real, não tratada como aprovação antecipada.

O alvo passa a ser o cartão .fc-event que contém exatamente o título sintético, com unicidade e visibilidade exigidas. Mantém viewport móvel, clique sem force, mesma rota/evento/empresa, HTTP200, revision1→2, filtro, exclusão, formulário antigo e demais checks. Registra só largura do cartão/título e viewport, sem dados privados. Se clique e espera por resposta falharem juntos, propaga o erro real de actionability em vez de escondê-lo pelo timer de resposta. Nenhum timeout ampliado.

O evento sintético é criado às10h do dia local do navegador, na visualização corrente, em vez da data fixa26/10/2026; testes específicos de histórico/empresa principal permanecem intactos. Isso evita que este smoke de criação recém-feita dependa do mês em que for executado.

Parser JS disponível passou;125 assertions (123 preservadas +2 para o cartão). PHP/Node/Playwright/bancos locais indisponíveis, nova stack ainda necessária. Correções funcionais2c/75 e PWAa5ba730 preservadas, nenhuma rota foi liberada por este ajuste. Retirada física restante,48 controllers paralelos e CNAB400 seguem incompletos. Browser de cancelamento também indisponível, portanto nenhum novo cancelamento manual alegado.
