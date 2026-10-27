# PR52 — um responsável pelos headers do documento nativo

Base e0f0ef657a4024590d62eca4bf04502f96108018; branch/PR52 preservadas, PWA inalterado. Rascunho e prontidão false.

## Evidência e causa reproduzida

Stack [40433128248](https://github.com/wkarts/ARGWS-CRM/actions/runs/40433128248), job121955368259: GET do calendário foi recusado409 antes do controller, com company_context_mismatch. Artefato12702294071, ZIP SHA256 `641ff39f8dd565401a682f5237fd09a75f071b91caf9b3b5e61fb8153c01f01a`, baixado/conferido. O diagnóstico real informa contexto alterado e header presente, sem expor seu valor. O JSON original com proveniência está em evidence/native-calendar-context-e0.json.

O head oficial inclui company_context_script.php também nos documentos nativos. Esse script interceptava XMLHttpRequest.send e acrescentava X-Company-Context/X-Native-Company-CSRF. NativeCompanyTransport já vinculava esses headers no próprio pedido; duas chamadas setRequestHeader acumulam valores. Reproduzido com os dois scripts reais: o cenário anterior falha com native_context_header_count, enquanto a correção mantém exatamente um header vinculado ao documento. Essa reprodução de composição não equivale ainda à prova HTTP da nova versão.

## Alteração funcional

A decisão é feita no servidor pela atestação nativeFor do documento. O script compartilhado continua marcando formulários, inclusive dinâmicos, e preservando os tokens existentes. Em documento nativo encerra sua participação antes dos interceptores de rede: NativeCompanyTransport mantém os headers. Nas páginas legadas o interceptor continua ativo. Não se aceita header concatenado, não se troca token de formulário antigo pelo atual e não se altera a validação do backend.

Revisados25 scripts native*.js: sem fetch/XHR direto adicional, exceto Dropzone do formulário de comentário de tarefa, cuja marcação de campos permanece ativa. O probe também exige formulário novo marcado, tokens antigos intactos, ausência de tokens em GET/formulário externo e rejeição de header conflitante. A revisão não é declarada como teste HTTP desses outros fluxos.

Após exclusão confirmada no calendário, o handler compartilhado navega por GET para o mesmo documento sem eventid, em vez de reload de um documento que pode ter vindo do POST de filtros. Resposta de exclusão não confirmada não navega. É consequência de ação voluntária; nenhuma recarga automática ou convite PWA foi introduzido.

## Provas acrescentadas

- tests/company_context.php usa o bootstrap real para renderizar o script em contexto legado e nativo, e passa o HTML/configuração capturados a tests/support/NativeContextNetworkProbe.cjs.
- O probe executa o JS renderizado junto com o asset publicado NativeCompanyTransport, com DOM/rede explicitamente simulados; confere GET/POST, propósito CSRF, uma vinculação, conflitos e formulários. O fixture não imprime tokens ou corpos.
- O smoke autenticado conserva a assertion HTTP200 e passa a exigir que o header observado corresponda exatamente ao contexto do documento, sem repetir valores secretos no erro.
- O teste do calendário exige GET após exclusão confirmada e ausência de navegação na recusa. Todos os guards, revisão e casos de datas/DateEnv permanecem.

## Executado e pendente

Local: reprodução antes/depois com os scripts reais passou (falha esperada antes, sucesso depois), usando fronteira PHP sintética; PHP **não executado localmente**. Também executados Node/DateEnv/callback, sintaxe JS/bash, dois testes Python e reconciliação de25 fontes existentes +migrations131/132 contra artefato completo76d9ba4.15084 fronteiras,4610 fontes operacionais e647 itens no auditor; nenhum encerramento artificial. O asset de transporte foi conferido pelo blob f39991ca54ff26eddba9479af38931805d994f5f. O calendário compartilhado é a única alteração de asset neste incremento; main.js e FullCalendar permanecem intactos.

Na base e0, Focused PR40433128449 passou PHP8.1/8.5; BB40433128257 unitários8.1/8.5 e checkout passaram, bancos ainda executavam na última consulta. Stack falhou conforme acima. Não são resultados do incremento novo. A renderização PHP nova e o fluxo autenticado precisam do CI. Revisadas279 classes NativeCompany/CompanyCalendar/CompanySettings: nenhuma outra comparação row_array()===[]/!==[] encontrada. Revisão de Staff não identificou a leitura tardia do ID nesse conjunto; isso não substitui seu aceite em navegador.

Retirada integral da aplicação paralela, Staff/calendário completos, demais integrações, CNAB400 vinculado às faturas, matriz de instalação/upgrade e PWA instalado/standalone permanecem sem aceite integral.48 controllers paralelos/51 views com shell. BB continua1.3.2/132, canais independentes e gates preservados. Sem alteração de migrations antigas, arquivos protegidos, PIGE360, serviços externos, merge, deploy ou force-push.
