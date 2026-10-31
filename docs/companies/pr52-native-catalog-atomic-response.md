# Catálogo nativo: resposta atômica e diagnóstico seguro

Baseee915658f2512944268a972f95759a95e18e7377. A stack [40449870960](https://github.com/wkarts/ARGWS-CRM/actions/runs/40449870960), job122009824174, confirmou o filtro original de Chamados: limpar POST200/aaData, aplicar Assunto POST200/aaData e **zero erros JS** até o estágio seguinte. A stack19cb9204 havia identificado a falha como DataTables Ajax error; o mesmo fluxo em ee não a registrou. O guard zero erros permanece.

A stackee ainda falhou em GET /admin/invoice_items500. A restrição do hook no modal é uma dependência corrigida, mas **não resolveu esse500**. Não se declara causa/correção antes de nova prova. Artefato [12710725821](https://github.com/wkarts/ARGWS-CRM/actions/runs/40449870960/artifacts/12710725821), ZIP SHA256 informado pelo CI b8cd18d86d1be7651848267940d92d084c4611342ad6e40b286518190cf2ff56; não extraído/conferido visualmente.

## Controller oficial

O caminho nativo de Invoice_items/index agora bufferiza a view oficial, revalida items.view antes da emissão e usa no-store. Falha ou revogação não emite o HTML privado parcial; buffers abertos pela renderização são descartados até a profundidade do chamador. A exceção real é propagada, sem converter500 em sucesso.

Construção e resposta registram apenas classe da exceção, basename do arquivo e linha via error_log. Sem mensagens, SQL, credenciais, valores, payloads, contextos ou tokens. Isso permite ao próximo CI localizar a falha que a configuração de produção não revela, sem habilitar debug público ou hooks globais.

O teste existente catalog_client_currencies mantém suas verificações e passa a executar o controller original com catálogo/ACL PDO reais. Somente renderer/output CodeIgniter são doubles explícitos: sucesso confirmado, desligamento real do issuer durante renderização, falha com buffers aninhados e recusa de emissão. Não se apresenta isso como navegador.

## Falha da matriz Empresas

No SHA73cb61f, [Empresas40447533708](https://github.com/wkarts/ARGWS-CRM/actions/runs/40447533708), job122002816988/index386, sales_mutations_readback esgotou128MiB no tape de queries de EssentialTestDatabase. O wrapper guarda cópias dos parâmetros JSON de revisões grandes; o teste de readback não faz assertions desse tape.

O fixture limpa apenas essa instrumentação entre fases concluídas e operações grandes. Preserva payload100x20000 caracteres, as duas revisões, o orçamento8MiB, triggers, rollbacks, assertions e limite128MiB. Acrescenta duas verificações de que ambas as revisões completas persistem antes/depois da recusa. Não remove linhas, arquivos nem histórico do banco; não muda o runtime de produção, cobertura ou timeout. Novos testes PHP ainda não executados.

## Inventários e limites

Atualizados por diferença o hash/linhas do controller no inventário operacional e censo, além do hash individual do modal no auditor (permanece unreviewed; rollout blocked). SHA256 do controller anterior foi conferido contra o registro existente; não se alteraram classificações/prontidão. Métodos públicos existentes mantidos; sem nova rota.

Os2 hashes agregados ainda precisam do cálculo oficial deste novo código. Os digests oficiais de ee (732b66... e3630e8...) **não podem ser usados após esta alteração de produção**. Preflights permanecem fechados até reconciliação. Auditor continua647 itens/613unreviewed/34partial;48 controllers paralelos/51 views e operational_ready=false.

Parser JS do smoke disponível; PHP/Node/bancos/navegador locais indisponíveis. Sem PWA/arquivos bloqueados, operações externas, merge/deploy/force-push. PR52 rascunho, unificação integral/CNAB400/aceite ainda incompletos.
