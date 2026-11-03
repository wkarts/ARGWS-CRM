# Catálogo original: JSON e edição sem perda de texto

Incremento sobre b88a96cdd4358685db577e819bad99eeefdf1895; preserva PWA a5ba730 e todos os ancestrais. PR52 permanece em rascunho, operational_ready=false.

O endpoint nativo de itens agora declara application/json e Cache-Control: no-store nas leituras e gravações. O mesmo controller, ACL, namespace, CSRF, revisão e escopo continuam responsáveis pela operação. O ramo legado permanece compatível. JSON_HEX_TAG/AMP/APOS/QUOT mantém o transporte seguro mesmo ao navegar diretamente ao endpoint.

A prévia long_description continua escapada para HTML. long_description_text fornece o texto original para o textarea; ambos os pontos de abertura do editor oficial usam esse campo quando presente, com fallback legado. O callback de gravação aceita tanto JSON já decodificado pelo jQuery quanto string legada. Não há novo painel ou editor.

O fixture usa o controller e helper reais com PDO/ACL; declara explicitamente os doubles de framework. Verifica JSON não armazenável, texto literal e prévia escapada, ausência de delimitador HTML na transmissão e recusa sem payload para item de outra empresa. O smoke autenticado mantém as verificações anteriores e acrescenta duas comparações exatas de descrição com <tag>, &, aspas e quebra de linha durante leitura e edição.

Verificações disponíveis antes de publicar: sintaxe CJS e script real do modal compilados em V8; JSONs de inventário parseados; hashes individuais calculados e conferidos. Não houve execução local PHP/Node/banco/navegador porque o executor está indisponível. Lint, PDO, matriz PHP8.1/8.5 e browser aguardam os workflows existentes. O digest agregado será reconciliado com o gerador oficial completo, sem alterar classificações ou readiness.

Evidência anterior, não aprovação automática deste incremento: [Stack0a40455956261](https://github.com/wkarts/ARGWS-CRM/actions/runs/40455956261), job122029205588, passou pelas fases de criar/ler/editar item e formulário antigo após troca; chegou ao fluxo Staff/upgrade, falhando no aviso de sucesso da atualização. Cinco erros DataTable foram registrados depois da página de Staff; diagnóstico continua aberto. Artefato [12712750914](https://github.com/wkarts/ARGWS-CRM/actions/runs/40455956261/artifacts/12712750914), digest informado pelo CI df9c06cb65102f16395edfd7cfe22a080ee0ad56d9f9bd821d1b3a21b5081f38, sem extração local alegada.

48 controllers paralelos/51 views de shell ainda precisam de migração e remoção verificadas. CNAB400 completo/homologação, integrações restantes e aceite integral continuam pendentes. Nenhuma operação financeira, SMTP ou webhook externo executada; sem merge/deploy/force-push ou edição dos arquivos bloqueados.
