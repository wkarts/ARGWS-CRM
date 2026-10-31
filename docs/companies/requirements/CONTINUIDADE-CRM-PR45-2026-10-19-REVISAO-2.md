# Continuidade revisada para concluir Empresas e Banco do Brasil na PR45

Atualizado em 19 de outubro de 2026 para o pedido de contexto das 15:38 UTC. Snapshot remoto verificado às 14:42 UTC, revisão dirigida concluída às 14:46 UTC e arquivos lidos no commit `7ff6a149bcb6b0a39e5ddb7be20a153a7c279632`. Não foi confirmada a ausência de commits posteriores a essa consulta; a IA executora deve reconfirmar o remoto antes de escrever.

Este é o contexto autossuficiente para outra IA continuar a implementação no ARGWS CRM. Substitui os estados funcionais dos contextos anteriores, inclusive a revisão das 11:12 UTC. Os incrementos 417–423 já estão publicados e devem ser preservados. A missão da IA executora é implementar, concluir, testar e publicar os incrementos na mesma PR45, preservando o código já entregue. O produto solicitado ainda está incompleto, embora existam avanços funcionais importantes e CI aprovado. Este pedido é somente de contexto para a IA executora; a preparação deste documento não iniciou nova implementação nem alterou o repositório.

Este documento trata somente do repositório `wkarts/ARGWS-CRM`. Trabalho em PIGE ou em qualquer outro repositório é uma frente separada e não deve ser incorporado a esta retomada.

## 1 Objetivo e limites da execução

A prioridade é concluir o escopo restante o mais breve possível, reaproveitando o que já funciona. Faça uma reconciliação inicial curta e passe à implementação por fluxo completo. Não recomece o projeto, não refaça a auditoria inteira como primeira entrega e não trate módulos já entregues como pendentes. Paralelize apenas domínios independentes, com um escritor por arquivo e pushes serializados. Não há prazo garantido: prazo e conclusão dependem do trabalho restante e dos insumos externos específicos.

Leia primeiro as seções 2 e 3 para o estado atual, a seção 11 para a sequência prática e a seção 13 para o prompt de retomada. As demais seções preservam os contratos e critérios de aceite completos.

Concluir Empresas como parte nativa do núcleo, acima de Colaboradores na navegação, com empresa principal preservada, responsável da instalação comprovado ou designado de forma segura, funcionários com acesso por empresa e recurso, empresa padrão e seletor. Cada empresa deve administrar identidade, configurações e integrações próprias por interface gráfica. Clientes são privados por padrão; o compartilhamento explícito da identidade não compartilha contatos, sessões, arquivos ou histórico financeiro.

Concluir os fluxos multiempresa positivos e seus consumidores, incluindo os domínios financeiros e operacionais ainda bloqueados. Concluir a integração Banco do Brasil por API, CNAB240 e CNAB400 conforme os contratos aplicáveis, incluindo emissão, retorno, conciliação e estados operacionais confiáveis.

A retomada deve produzir implementação utilizável, testes e evidência por requisito. Não encerrar só com planejamento, inventário, documentação, classes de fundação, barreiras de rejeição ou mais um conjunto de checks verdes. Não reduzir silenciosamente o escopo integral ao perfil restrito já entregue. Uma entrega incremental menor precisa de decisão explícita do usuário.

Limites:
- Trabalhar na PR45 e na branch `feat/banco-brasil-billing-cnab240`, com base `main`.
- Publicar commits coerentes e atualizar a descrição e a matriz da mesma PR. Não criar uma PR concorrente para substituir este trabalho.
- Não fazer merge na main, deploy, force-push, tag ou release manual, publicação de imagens nem alteração/alinhamento de develop.
- Preservar o fluxo normal de release do projeto, sem inserir `skip-release` ou bloquear a publicação normal.
- Não usar produção, credenciais reais ou transações bancárias reais nesta implementação. Homologação externa requer autorização e acesso seguro próprios.
- Não colocar senhas, tokens, chaves, certificados privados, dados bancários ou dados pessoais reais em chat, código, fixtures, screenshots públicos ou logs.
- Preservar Asaas, notificações, correção do aviso OI, dados e comportamento legado. Refatorações não relacionadas não fazem parte desta tarefa.

## 2 Referência atual e evidência disponível

### Repositório e versões

| Campo | Referência verificada |
|---|---|
| Repositório | [wkarts/ARGWS-CRM](https://github.com/wkarts/ARGWS-CRM) |
| PR | [PR45](https://github.com/wkarts/ARGWS-CRM/pull/45), aberta e não mesclada |
| Branch de continuidade | `feat/banco-brasil-billing-cnab240` |
| Base | `main`, SHA `aa58b832b3c5ed04a599caacba125b056c081ba7` na consulta |
| HEAD revisado | `7ff6a149bcb6b0a39e5ddb7be20a153a7c279632` |
| Núcleo | `4.2.3`, migration `423` |
| Banco do Brasil | `1.1.0`, migrations `101–110` |
| Asaas | `1.4.8`, migration `148` |
| PR46 | Já incorporada por `cdd6f6f2c312a1aaa5f0f02056ad2e8b23eb3378`; não reabrir como frente separada |

A referência é um ponto de partida, não autorização para sobrescrever um remoto mais novo. Reconfirmar branch, HEAD, base, diff local e alterações de outros executores antes de editar e antes de cada push.

O fix `44cd7c3815123883d5c3168ae045a75a3347b236` já corrigiu o gate/planner para aceitar passos nativos acumulados contíguos. Não reaplicar esse trabalho nem voltar às versões antigas 4.0.4/404 ou 4.0.5/405.

### CI confirmado no HEAD revisado

Foram verificadas 20 execuções concluídas com sucesso, cobrindo 16 workflows no HEAD 7ff6a149. Execuções adicionais/reexecuções não representam workflows distintos nem suítes funcionais extras. Os checks desse SHA já abrangem os incrementos até 423; resultados antigos não devem substituir a prova de um HEAD posterior.

Evidências representativas:
- [Empresas e concorrência em MySQL com PHP 8.1 e 8.5](https://github.com/wkarts/ARGWS-CRM/actions/runs/39269187582)
- [Banco do Brasil com MySQL e MariaDB](https://github.com/wkarts/ARGWS-CRM/actions/runs/39269187793)
- [Integridade das stacks, provisionamento, HTTP e Chromium](https://github.com/wkarts/ARGWS-CRM/actions/runs/39269187699)

O acompanhamento leu código, diff, contratos, testes, inventários e resultados remotos. A revisão dirigida do incremento 90efd001 → 7ff6a149 usou leitura estática e CI remoto: não confirmou novo P0/P1. A preparação deste contexto não repetiu localmente as alegações de testes da implementação, não executou novos testes nem utilizou credenciais ou ambientes de produção. Os resultados de CI cobrem os cenários presentes nos respectivos jobs, não todo o escopo funcional, ausência universal de vazamentos ou homologação bancária.

O texto da PR chamar um SHA de final não comprova conclusão integral. `mergeable=true` informa ausência de conflito mecânico, não aceite funcional.

### Conclusão da revisão

Não recomendar merge como conclusão do escopo integral. Há lacunas funcionais expressamente assumidas no código. Não foi confirmado novo P0/P1 na revisão estática dirigida; isso não certifica ausência universal de vazamentos, corrupção financeira ou segurança de toda a PR. A continuidade deve resolver as lacunas demonstradas sem inventar defeitos críticos nem remover controles para aparentar conclusão.

## 3 O que já existe e não deve ser refeito como pendente

### Empresas, master, funcionários e wizard

- Empresas é nativo do núcleo. `modules/issuers` permanece histórico, fora do carregamento operacional.
- A empresa principal vem de identidade persistida; não há pressuposto seguro de ID numérico 1.
- Instalação nova captura o administrador efetivamente criado, inclusive quando seu ID não é 1.
- Legado ambíguo usa designação explícita: autorização pelo console, código aleatório de 256 bits, hash persistido, validade de 15 minutos, revogação por reemissão e confirmação na GUI com a senha atual do administrador designado.
- Cinco falhas de senha bloqueiam a autorização. Estado ativo/admin e legitimidade são revalidados sob lock; consumo, identidade e auditoria são atômicos. Identidade resolvida não pode ser substituída por replay.
- O procedimento é uma designação autorizada, não prova retroativa de quem foi o administrador histórico.
- Novo funcionário sem empresa autorizada e padrão é recusado antes da criação nativa. Perfil e grants ainda são unidades separadas no editor legado; falha posterior dos grants precisa continuar explícita e retomável.
- O master ativo cobre empresas e recursos futuros, sem cópia artificial de grants, mantendo os gates dos domínios ainda não adaptados.
- Wizard persistente e retomável: identidade/endereço, preferências, funcionários, recursos, padrão, privacidade, revisão e conclusão. Finalização idempotente e transacional para empresa, configurações, grants, padrão, templates, auditoria e rascunho.
- O wizard inicia clientes privados e já oferece capacidades do diretório adaptado. Compartilhar uma identidade é uma ação explícita posterior; não presumir compartilhamento automático.
- Há provas de serviços, falhas, rollback, concorrência MySQL, formulário nativo, HTTP e navegador.

Limite atual: a autorização inicial da designação legada exige PHP CLI. A GUI confirma código e senha, mas ainda não fornece uma recuperação inteiramente gráfica.

### Contexto e configurações

- Seletor com revalidação de acesso, token HMAC por documento, nonce/CSRF e rejeição 409 de aba obsoleta antes de hidratação e mutação.
- Não reproduzir um POST antigo depois de trocar de empresa.
- Identidade, preferências e configurações adaptadas por empresa, sem fallback silencioso de segredos para principal ou outra empresa.
- Templates/SMTP e fila usam origem durável; a fila persiste `issuer_id`, faz claim atômico e monta transporte pela origem.
- Desde 418, logos/assinatura em imagem e preferências de fatura são próprios por empresa. PNG/JPEG são validados, normalizados para PNG e privados; download exige ACL e auditoria. Assinatura aqui é imagem, não certificado A1/GovBR. Novas faturas capturam o snapshot; antigas preservam o seu. Prefixo e condições próprios já estão na GUI.
- O catálogo integral de settings e integrações adicionais permanece pendente; não recriar imagens e preferências já entregues.

### Clientes e portal

- `admin/clients?source=company` invoca `CompanyClients` em renderer isolado. Criar, ler, editar, buscar, paginar, compartilhar explicitamente a identidade e revogar já têm fluxo positivo.
- Criação tem recibo durável; retry retorna a mesma identidade e chave com conteúdo/ator/empresa divergente conflita. Edição/compartilhamento usam revisão e lock.
- Somente o proprietário edita. O master não pode editar pela empresa destinatária um cliente que ela apenas recebe por compartilhamento.
- Compartilhar exige autorização de inclusão no destino; revogar pelo dono não depende de novo grant no destino.
- CPF/CNPJ duplicado globalmente, inclusive legado formatado, é recusado com conflito genérico sem revelar outro proprietário. Identidades sem documento podem ser distintas; não mudar essa política incidentalmente.
- Contatos são próprios. Login, recuperação, tokens, sessões, template, SMTP e fila têm origem por empresa. O mesmo e-mail em A e B é coberto por testes.
- O portal já lista faturas emitidas da empresa e do cliente autenticados e entrega PDF com permissão nativa explícita `contact_permissions.permission_id=1`.
- Login/reset não concedem automaticamente acesso financeiro. Compartilhar cliente não compartilha contatos, sessão ou faturas.
- O portal exclui rascunhos e estados inválidos; revalida sessão, permissão, owner e estado antes do render e antes dos bytes, com auditoria confirmada.
- Desde 422, notificação de fatura agenda mensagem com origem própria e link para o portal autenticado. Desde 423, o portal lista/baixa anexos de fatura somente com publicação explícita por arquivo, empresa/cliente/permissão e fatura emitida corretos.
- Demais documentos/operações do portal e entrega SMTP externa permanecem pendentes.

Não repetir a afirmação histórica de que CompanyClients não está roteado ou de que portal, recuperação e lista/PDF de faturas não existem.

### Faturas e recebimentos manuais

- `admin/company_invoices` tem emissão, listagem, consulta, edição elegível, cancelamento elegível, PDF e recebimento manual parcial/final.
- Criação associa itens, até dez tributos percentuais por item, desconto fixo/percentual antes ou depois dos tributos, ajuste positivo/negativo, numeração, snapshots fiscais e auditoria na mesma transação. O incremento 417 já entregou essa ampliação monetária; não reimplementá-la como ausente.
- Desconto antes dos tributos é distribuído por maior resto, com desempate pela ordem dos itens; cada tributo arredonda meio centavo para cima. Ajuste não altera bases fiscais. Total precisa permanecer positivo e dentro de DECIMAL(15,2). Isso não equivale a um motor fiscal nacional completo.
- Valores usam centavos inteiros e regras explícitas de arredondamento/overflow.
- Edição sem movimento mantém número, cliente, moeda e snapshots; cancelamento conserva histórico e é recusado quando há pagamentos/créditos ou origem BB/Asaas.
- `InvoicePaymentWriter::recordCompanyManual` exige ACL, owner, referência, saldo sob lock, idempotência e commit conjunto de pagamento, status, recibo e auditoria.
- Origem BB/Asaas não é contornada por recebimento manual.
- PDF usa DTO autorizado e TCPDF, com revalidação e auditoria antes da entrega.
- Legado `issuer_id=NULL` conserva sua semântica e não é adotado automaticamente.

Limites atuais incluem créditos, refunds/reembolsos, estornos, recorrência, relatórios, APIs públicas, outros documentos/domínios e seus consumidores. Descontos/ajustes/múltiplos tributos, envio privado de notificação de fatura e anexos privados de fatura já foram entregues no recorte abaixo.

### Notificação privada e anexos de fatura já entregues

- `CompanyInvoiceDelivery` agenda uma mensagem por solicitação explícita. Exige permissões de fatura/clientes, contato ativo próprio, cliente ativo, fatura emitida e permissão nativa de faturas. Compartilhar identidade não autoriza notificar contatos do proprietário.
- Template, SMTP, remetente e assinatura vêm da empresa da fatura. O link exige portal autenticado; não cria PDF público, bearer ou anexo de PDF no e-mail. Fila, recibo idempotente e auditoria são transacionais.
- O worker revalida origem, ator, contato, permissão e revisão antes do transporte, sem rede dentro da transação. Retry após limpeza da fila não duplica uma solicitação antiga. Nova solicitação explícita pode gerar nova mensagem.
- Agendamento, processamento, falha, aceite pelo transporte e registro preservado são estados distintos. SMTP foi simulado/capturado nos testes. Aceite SMTP não prova chegada/leitura e crash depois do aceite não permite prometer exactly-once externo.
- `CompanyInvoiceAttachments` usa BLOB privado imutável e ownership de pai/filho. PDF até 2 MiB é entregue como download com sandbox/nosniff, sem alegar sanitização ou análise antimalware; PNG/JPEG até 512 KiB são normalizados para PNG, com limites de dimensões/pixels. Preview só do PNG regravado.
- Upload multipart, listagem, download, preview, ocultação auditada e publicação explícita de cada arquivo no portal já têm fluxo positivo. Não há URL pública, adoção de fatura NULL ou permissão herdada pelo compartilhamento do cliente.
- Quotas atuais: até 20 uploads/20 MiB por fatura, incluindo ocultos. Ocultar conserva o histórico e retry antigo não ressuscita conteúdo. Não apagar recibos nem fazer purga para contornar quota.
- Conteúdo, tamanho e hash são conferidos antes da entrega; falha de auditoria, corrupção, revogação, rascunho ou IDs cruzados impedem bytes. Outros domínios de arquivo permanecem pendentes.

### Banco do Brasil já implementado

API:
- Configuração por perfil/conta/empresa/ambiente, credenciais criptografadas com `CI_Encryption` autenticado e envelope de contexto, sem devolver segredo salvo à UI.
- OAuth, registro, consulta com prova de conta e pedido/consulta de confirmação de baixa.
- Outbox/intenção durável e reserva de origem/Nosso Número antes da rede.
- Namespace de Nosso Número compartilhado entre API e CNAB, sem reiniciar faixa por troca de conexão ou canal.
- Perfil restrito carteira 17, cobrança simples, convênio de sete dígitos e BRL, sem Pix, juros, multa, desconto, protesto ou emissão com recebimento parcial.
- Desde 419/BB109, `settle_api` aplica crédito API integral restrito por ação explícita, consulta validada recente, prova de conta e estado 16 CREDITADO, passando pelo writer canônico. Estado 6 LIQUIDADO não basta. Não é aplicação automática por consulta/webhook.
- Desde 421/BB110, os vetos cruzados reconhecem a representação API de 20 dígitos (`000` + Nosso Número de 17) e CNAB de 17 dígitos, mantendo empresa/conta/ambiente/convênio. Isso bloqueia duplicidade e observações adversas entre representações; não reescreve histórico nem amplia autorização.
- Testes de protocolo com transporte simulado e composição/concorrência nas matrizes de banco. Nenhum OAuth bancário real nem homologação externa comprovados.

CNAB240:
- Codec P/Q e parser T/U restritos.
- Originação ligada à fatura, reserva durável de número/lote, snapshot, retry, geração e download auditado.
- Perfil declarado por operador, com faixa exclusiva e referência contratual. Declaração é registrada como `operator_declaration_not_bank_verification`.
- Declaração manual de envio existe; download e declaração não são transmissão autenticada nem aceite.

Conciliação assistida 240:
- Vínculo exato da observação à origem, revisão gráfica e confirmação independente do operador.
- Entrada restrita no writer financeiro nativo, com recibo canônico, deduplicação e concorrência.
- Créditos parciais sem encargos deixam saldo/status parcial.
- Importação isolada continua observacional. O arquivo mantém autenticidade não verificada.

CNAB400:
- Parser CBR643 e codec candidato CBR641 com fixtures.
- Candidato indisponível para operação. Emissão operacional e homologação não estão entregues.

Não repetir que a API não existe, que a OpenAPI está ausente, que não há reserva concorrente, que toda conciliação permanece sem efeito financeiro ou que a originação CNAB240 não está ligada à fatura. Também não repetir que descontos/ajustes/tributos, logos/assinatura, notificação/anexos de fatura ou crédito API restrito estão ausentes.

## 4 Invariantes que a continuidade deve preservar

### Isolamento e autorização

1. Resolver empresa pelo proprietário persistido do recurso. Seleção de tela, empresa padrão ou papel global não substituem ownership.
2. Validar empresa, recurso, ação e ator antes de hidratar modelos, hooks, providers, arquivos ou dados sensíveis.
3. Rotas secundárias não adaptadas continuam fechadas. `CompanyRequestScope` abre apenas caminhos revisados; `IssuerPolicy::assertOperationalScope` ainda está fechado incondicionalmente no mecanismo genérico.
4. Não resolver as lacunas removendo gates, liberando prefixos inteiros de rota ou reutilizando modelos legados que carreguem contexto global antes da autorização.
5. Abrir cada fluxo positivo somente depois de adaptar controlador, serviço, leitor, subrecursos, arquivo, API/job e testes A/B correspondentes.
6. Revogação de grant, desativação/rebaixamento e troca de contexto devem valer na próxima operação, inclusive em aba antiga e enquanto uma transação aguarda lock.
7. Origem `NULL` é histórico legado, não sinônimo automático da empresa principal. Não reatribuir documentos antigos por conveniência.
8. Compartilhar identidade não compartilha finanças, contatos, arquivos, permissões ou sessões.
9. Token de contexto não substitui ACL; CSRF não substitui ownership; ocultar menu não substitui autorização servidor.
10. Jobs, cron, CLI, APIs, webhooks, importadores, exports e transportes resolvem origem explicitamente, sem depender de sessão administrativa.

### Persistência e concorrência

- Mutação financeira tem um writer transacional responsável, locks e evidência canônica. Não adicionar SQL ad hoc que contorne esse contrato.
- Idempotência deve conferir conteúdo, ator, origem e destino; mesma chave com payload divergente é conflito.
- Não reutilizar Nosso Número depois de falha. Preservar reserva, operação, evidência e trilha para retomada.
- Erro em auditoria, tracking, proveniência, gravação ou commit não pode produzir sucesso falso.
- Não apagar observações nem marcar conciliação concluída antes do commit financeiro.
- Preservar histórico, snapshots, dados, migrations e compatibilidade. Downgrade destrutivo não é uma estratégia aceitável de rollback.

### Arquivos e entrega

- Autorizar pai e subrecurso para upload, download, preview, PDF, export e URL direta.
- Revalidar quando o processamento demorado separar autorização de entrega, especialmente PDF.
- Não afrouxar bloqueios do servidor de arquivos nem transformar caminho físico em autorização.
- Upload bancário não autentica a origem do banco. HTML, nome de arquivo ou conteúdo importado nunca conferem autoridade.

## 5 Pendências multiempresa e critérios de aceite

### Administração gráfica e master legado

Concluir a experiência gráfica desejada preservando uma autoridade confiável. O fluxo atual console + senha é uma escolha de segurança; não o trocar por autopromoção baseada em `admin`, ID 1, primeiro usuário ou parâmetro HTTP.

Investigar prova de instalação/provisionamento e mecanismos de recuperação confiáveis existentes. Se for necessário novo mecanismo, desenhar autorização, validade, uso único, revogação, rate limit, auditoria e recuperação segura antes de implementá-lo. Se não houver prova suficiente, pedir a decisão externa específica e continuar outros domínios.

Aceite:
- Instalação nova com master diferente de ID 1.
- Legado ambíguo sem autopromoção e sem falsa alegação de identidade histórica.
- Replay/concorrência não substituem responsável já resolvido.
- Revogação, expiração, cinco falhas e indisponibilidade de persistência mantêm falha fechada.
- GUI orienta claramente cada estado; funcionário, grants/default e falha parcial permanecem utilizáveis e retomáveis.
- Não afirmar administração totalmente gráfica enquanto uma etapa obrigatória continuar exigindo CLI.

### Configurações e integrações por empresa

Preservar identidade/endereço, imagens de logotipo/assinatura, preferências de fatura, templates, SMTP e arquivos de fatura já adaptados. Completar os grupos de configuração e integrações restantes que o inventário atual demonstrar. Distinguir infraestrutura expressamente global de configuração e segredo da empresa.

Aceite:
- Escrever em A não altera B/principal; ausência em B não herda segredo de A.
- DTOs, listagens, logs, exports e browser não revelam segredos persistidos.
- Cache é invalidado e 409 de aba obsoleta ocorre antes de efeitos.
- Arquivos pertencem à empresa autorizada em upload, preview e download.
- Fila preserva origem mesmo sem sessão ou após troca de empresa; retry não duplica envio.
- Transporte SMTP externo é validado somente em ambiente autorizado e separado dos testes sintéticos.
- Wizard e GUI mostram capacidades verdadeiramente habilitadas e explicam bloqueios.

### Clientes e portal restantes

Preservar o diretório e login/reset/faturas já entregues. Completar consumidores restantes sem duplicar esses serviços.

Aceite:
- CRUD, busca, contagem, compartilhamento/revogação e conflitos preservam privacidade A/B.
- Revogação não corrompe snapshots históricos nem expõe contato/finanças no destino.
- Novos documentos e operações do portal têm permissão nativa explícita e ownership do cliente e empresa.
- Casos de e-mail duplicado, token de outra empresa, sessão antiga, recuperação, login/reset concorrentes e revogação durante PDF continuam cobertos.
- Rascunhos não aparecem nem podem ser baixados por ID direto.
- Ajustar regras de CPF/CNPJ somente com decisão de produto explícita e plano compatível para o legado.

### Financeiro completo

Completar, conforme o domínio, orçamentos/propostas e suas conversões, créditos, aplicação/desaplicação de crédito, refunds/reembolsos, estornos, recorrências, relatórios, APIs e consumidores restantes. Reaproveitar descontos/ajustes/múltiplos tributos, recebimentos manuais, notificação e anexos já positivos em faturas; ampliar os demais documentos apenas onde a cobertura ainda faltar.

Para cada fluxo:
- UI real, serviço e persistência positivos em A e B, além de testes negativos.
- Lista/busca/totais antes da hidratação, sem vazamento por agregação, gráfico, contador, cache, campo relacionado ou export.
- Pai e subrecurso com origem coerente; IDs cruzados negados antes de efeitos.
- Numeração, impostos, moeda, arredondamento, saldo e snapshots consistentes.
- Aplicação/desaplicação de crédito, refund e estorno com trilha contábil e saldo, sem exclusão que apague o histórico.
- Pagamento parcial/final, excesso de saldo, repetição, mesma referência com nova chave, evento fora de ordem e concorrência resolvidos pelo writer.
- Recorrência persiste origem, configurações, identidade, destinatário e transporte; não herda a sessão corrente nem cria fatura em outra empresa.
- E-mail, anexos e callbacks não disparam antes da confirmação da transação; entregas podem ser retomadas com idempotência.
- Cancelamento local é distinto de baixa remota; fatura com vínculo de provedor exige o fluxo específico.
- Upgrade e registros antigos `NULL` continuam funcionais, sem adoção silenciosa.

### Projetos, tarefas, despesas e demais consumidores

Concluir fluxos positivos dos domínios ainda fechados: projetos, tarefas, despesas, contratos, tickets/suporte, documentos de vendas, relatórios, API, importadores/exportadores, módulos e outros consumidores identificados no inventário atual.

Revisar relações indiretas, comentários, anexos, timers, checklists, marcos, seguidores, responsáveis, Kanban, buscas, caches, limpeza/cascade, hooks e jobs. Um teste de negação legado não prova a operação positiva da empresa secundária.

Aceite:
- A/B e legado em criação, leitura, atualização, exclusão/cancelamento elegível, cópia/conversão e relacionamentos.
- Filtro de origem aplicado a predicados OR, buscas por tags, contagens e SQL customizado.
- IDs de filhos resolvem o pai persistido; declarar um pai diferente no request não concede acesso.
- Lote misto é recusado antes de escrita parcial; concorrência e falhas intermediárias preservam invariantes.
- Cleanup após exclusão do pai usa prova interna delimitada, liberada em `finally`, sem bypass público por `force`.
- API autentica e autoriza a origem efetiva; token genérico não concede empresas futuras sem autorização.
- Subir o gate de cada consumidor apenas com código, teste e revisão rastreáveis.

### Asaas e aviso OI

Asaas 1.4.8/148 deve continuar funcionando no legado. A operação positiva por empresa nomeada ainda é bloqueada e precisa adaptação por origem, não simples remoção do veto.

Preservar:
- Seis abas de configuração e separação estrita de ambientes.
- Integrações webhook locais, geração/rotação de token, hash persistido e exposição única.
- Rotação invalida token anterior; callback deriva de configuração HTTPS confiável.
- Cadastro local não registra webhook remoto automaticamente; antigos `setup/set_webhook` continuam 404.
- Autenticação, deduplicação, reconciliação canônica e writer financeiro.
- Webhook recebido não equivale a baixa. Refunds/chargebacks não ganham aplicação automática sem contrato e evidência.
- Checkout à vista não deve ser descrito como parcelamento, assinatura ou cartão salvo.
- Remoção remota resolve conta/origem sem adivinhação.

Para adaptar Asaas positivo, provar contas/empresas/ambientes, configuração, origem de cobrança, webhooks, deduplicação, estados e writer em A/B e legado. Operação bancária real continua fora desta autorização.

Preservar a correção OI: HTMLPurifier com política dedicada, fragmento normalizado, classes legítimas mantidas, aviso somente para administrador e sem execução de scripts históricos. Manter `tests/dashboard_update_notice.php`. A reprodução sintética não demonstra retroativamente a causa exata da ocorrência em produção.

## 6 Banco do Brasil e contratos confirmados

### Especificação oficial já recuperada

A OpenAPI deixou de ser bloqueio documental. A implementação registrou a obtenção da rota pública oficial em 19/10/2026:
- [OpenAPI da API de Cobranças](https://api.externo.bb.com.br/docs/v1/openapi/17395705/2)
- OpenAPI 3.1.1, `info.version=3.2.2`, 198.948 bytes.
- SHA256 registrado: `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.
- API IPA5, catálogo 17395705, versão de catálogo 2.
- OAuth `clientCredentials`, AppKey de query, servidores por sandbox/homologação/produção e webhook de baixa operacional com mTLS.
- A combinação de esquemas OAuth dos ambientes no mesmo Security Requirement foi anotada como inconsistência formal; não interpretar isso como exigência de credenciais dos três ambientes simultaneamente.
- [Especificação oficial de boleto referenciada](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc5175Bloqueto.pdf), SHA256 registrado `6da3d3f4cde0cb94b20f957001c10cd683efd6f9c5ec6dfe68193f9585e79902`.

Esses são dados documentados no SHA revisado, não uma afirmação de que este handoff baixou novamente todos os documentos. Ao expandir contratos, consultar a fonte oficial vigente, registrar data/versão/hash e resolver diferenças sem alterar silenciosamente perfis existentes.

Pontos de partida oficiais:
- [API de Cobrança BB](https://www.bb.com.br/site/developers/api-cobranca/)
- [Catálogo oficial de leiautes](https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/)

### CNAB400 e ambiguidades remanescentes

O trailer CBR641 de remessa já foi esclarecido na documentação de julho/2026: registro 9, posições 2–394 em branco, sequência 395–400. Não reabrir esse ponto como se ainda fosse uma lacuna.

Permanecem:
- Código de BRL no campo 85–86 do CBR641 sem confirmação explícita na nota consultada; os códigos 02/dólar e 14/euro não autorizam inferir BRL.
- Juros zerados/omitidos podem herdar a configuração do beneficiário; não prometer isenção sem contrato.
- Particularidades de convênio, carteira, variação e canal precisam ser comprovadas.
- No retorno CBR643, posições 26–39 somam 14 caracteres, mas o picture descrito é `9(013) v99`. Preservar bruto e revisão até resolver; não inventar conversão financeira.

O usuário informou ter remessas reais aceitas pelo banco e geradas por outra aplicação, mas elas ainda não foram recebidas/analisadas nesta revisão. Não há retornos reais disponíveis. Quando fornecidas, confirmar origem, manter os originais privados e criar fixtures anonimizadas preservando estrutura. Um exemplo aceito não homologa todos os cenários nem o novo gerador.

## 7 Banco do Brasil e implementação que falta

### Contrato comum de origem

Toda cobrança deve vincular de forma imutável empresa, fatura/snapshot, conta, ambiente, convênio, carteira, variação, perfil/versionamento, canal, Nosso Número e identidade remota.

Resolver owner da fatura, autorizar ator e recurso e reservar a origem antes da rede ou geração. Trocar conexão/conta não reclassifica cobranças antigas nem reinicia números. Tratar concorrência entre API/CNAB, duplicatas entre canais, mudança de configuração e eventos fora de ordem.

Distinguir observação, origem vinculada, número reservado, geração, download, envio declarado, envio comprovado, registro aceito, recebimento, pedido de baixa e baixa confirmada. O nome do estado exibido deve refletir a prova disponível.

### API

Preservar o executor atual e concluir:
- Alterações de títulos suportadas pelo contrato, com validação de campos, estados, permissões, idempotência e confirmação.
- Perfis além do recorte atual, incluindo encargos e demais capacidades efetivamente pedidas e contratadas. Expansões não podem ser habilitadas sem regra/documento.
- Webhook de baixa operacional com mTLS conforme contrato; não substituir prova de transporte por cabeçalho de origem, IP solto ou HMAC inventado.
- Ampliação da aplicação financeira API além do crédito integral explícito já entregue: parciais, encargos, demais perfis/eventos e estornos com semântica e evidência suficientes, sempre no writer canônico.
- Reconciliação de consultas, notificações, falhas e estados intermediários.

Regras existentes a manter:
- Configurar credenciais não realiza autenticação nem registra títulos automaticamente.
- Reserva/intenção durável precedem a rede; OAuth ocorre fora da transação.
- Autorização é revalidada antes do marcador de envio.
- Timeout, 5xx ou resposta divergente depois do POST deixam resultado incerto, sem reenvio cego. Falha apenas de autenticação pode retomar a mesma reserva.
- Consulta detalhada é cruzada com listagem oficial filtrada por conta/agência/pagador/vencimento; paginação limitada ou ausência do título não provam inexistência.
- Pedido de baixa exige consulta NORMAL confirmada há menos de cinco minutos e usa a origem antiga.
- Resposta do pedido não confirma baixa. A confirmação atual depende de consulta coerente com código 7.
- Consulta isolada de códigos 6/16 não cria pagamento automaticamente nem cancela fatura local. Código 6 LIQUIDADO não basta; código 16 CREDITADO permite somente a ação explícita e restrita abaixo.
- Número de 20 dígitos, agência/conta, carteira/variação, código de barras 44, linha 47, DVs, fator de vencimento e campo livre são validados conforme contrato.
- Tokens permanecem em memória; diagnósticos não armazenam corpo bruto de erro, credenciais ou URL com segredo.

Aceite da ampliação:
- Sucesso, erro, 401/403, expiração/rotação, timeout antes/depois do POST, resposta malformada/divergente, retry, paginação, evento fora de ordem e concorrência.
- Revogação durante operação e conta/empresa/ambiente trocados não produzem envio indevido.
- Um vencedor por intenção, inclusive API versus CNAB.
- Não declarar emissão/pagamento/baixa final com base em estado local ou resposta insuficiente.
- Teste real em sandbox somente com autorização; registrar separadamente do transporte simulado.

### Crédito API restrito que deve ser preservado

`settle_api` exige contexto, POST/CSRF, BB Visualizar/Conciliar, faturas Visualizar e pagamentos Criar. Usa origem imutável e a última consulta validada com prova de conta, de até cinco minutos no primeiro lançamento. Requer estado 16 CREDITADO, natureza normal/apresentação, liquidação elegível, data de crédito ocorrida, nominal/pago/líquido integrais e todos os ajustes zerados.

`ApiCreditedReceipt` é uma fonte final expressamente autorizada do writer; implementar a interface não permite criar uma fonte arbitrária. Recibo bancário estável independe de ator, chave da UI ou nova consulta idêntica. Pagamento, status, recibo e auditorias confirmam na mesma transação.

Parcial, cheque, cartório, ajustes, prova/valores/datas ausentes e histórico adverso ficam em revisão. Uma consulta posterior não apaga conflito materializado nem um evento adverso estorna automaticamente o pagamento. API20 e CNAB17 equivalentes são comparados nos vetos de recibo e observação adversa, sempre preservando empresa/conta/ambiente/convênio. Manter testes positivos e negativos cruzados, concorrência e revogação. Os testes cruzados atuais usam evidência sintética persistida e não comprovam homologação nem uma corrida financeira API/CNAB completa; ampliar essa prova sem confundi-la com a concorrência de originação já testada.

### CNAB240

Preservar geração/download/retry e conciliação assistida entregues. Completar perfis, operações e transporte conforme o canal realmente contratado.

Aceite:
- Layout por versão e contrato; comprimento, encoding, posições, campos, registros, sequência, moeda, datas, valores, quantidade e totalizadores.
- Emitente/pagador, grafia ASCII revisada, documento bancário e snapshot reconciliados sem truncar ou mudar silenciosamente identidade fiscal.
- Faixas exclusivas, reservas concorrentes, lote, recuperação após falha e download auditado.
- Arquivos antigos continuam vinculados ao contrato original.
- Estados `reserved`, `generation_failed`, `generated`, `downloaded` e `reported_submitted` mantêm seu significado local.
- Se houver transporte autorizado e documentado, implementar autenticação, envio, recibo/aceite, consulta e retomada. Se o contrato é envio manual, completar uma operação manual auditável sem inventar transmissão automática.
- Download não é prova de envio/aceite; declaração do operador não vira homologação.

### CNAB400

Concluir um canal independente. Não usar o perfil CNAB240 como fallback silencioso.

Sequência:
1. Resolver documentalmente BRL, juros/beneficiário, contrato/carteira/variação e divergência CBR643.
2. Definir perfil explícito, fingerprint, proveniência e regras operacionais.
3. Integrar emissão à fatura, reserva compartilhada, lote, snapshot, geração/download e trilha.
4. Completar retorno/revisão/vínculo/efeito financeiro conforme evidência canônica.
5. Validar layout e casos positivos/negativos próprios, com fixtures sintéticas ou anonimizadas.
6. Homologar separadamente no ambiente/conta/convênio/carteira autorizados.

Enquanto uma ambiguidade impedir escrever um arquivo correto, manter o canal indisponível, indicar o campo/documento necessário e continuar trabalhos independentes. Não rotular o codec candidato como operação completa.

### Retornos e conciliação financeira

Preservar importação observacional e o crédito CNAB240 assistido restrito. No recorte atual, vínculo exige empresa, conexão, conta/ambiente/convênio, canal, Nosso Número, controle, documento, vencimento, perfil e snapshot íntegros.

A confirmação do operador é explícita, baseada em fonte independente do upload, com referência/motivo. Ela não autentica tecnicamente o arquivo. O writer exige permissões de BB Conciliar, faturas Visualizar e pagamentos Criar. Ocorrência 06 isolada nunca basta.

Concluir juros, multa, descontos, abatimentos, tarifas, pagamentos parciais, rejeições, estornos contábeis e demais eventos aplicáveis. Diferenciar baixa operacional do título de recebimento/pagamento da fatura.

Aceite:
- Mesmo arquivo e mesmo evento não duplicam pagamento; mesma identidade com conteúdo divergente gera conflito.
- Concorrência e replay entre arquivos/canais respeitam uma evidência/recibo canônicos.
- Conta, ambiente, empresa, título, valor ou snapshot divergentes impedem efeito.
- Eventos desconhecidos/ambíguos ficam em revisão com motivo útil, sem classificação inventada.
- Encargos e líquido/bruto obedecem contrato e escrituração; divergências não são ignoradas para “fechar” a fatura.
- Evento adverso posterior/anterior, estorno e reimportação não produzem saldo incorreto.
- Falha intermediária preserva observação e permite retomada, sem pagamento parcial não auditado.
- Estorno de revisão não é apresentado como estorno financeiro executado.
- Não criar um botão de exceção que contorne origem, saldo, evento adverso ou writer.

### Homologação externa

Nenhum canal possui evidência de homologação real nesta referência. Isso é distinto das pendências de software.

Para cada API/240/400, registrar:
- Canal, ambiente, empresa/conta, convênio, carteira/variação, perfil e versão.
- Autorização de acesso e operação.
- Documento/contrato usado e casos testados.
- Evidência de registro/aceite/retorno/consulta e comportamento de cancelamento/erro/repetição.
- Diferenças entre esperado e observado, sem expor segredos ou dados reais.

Não dizer “integração pronta/homologada” com mocks, CI, configuração visual, download ou remessa de outra aplicação. Ausência de credencial real não justifica abandonar código/testes internos que possam ser concluídos sem ela.

## 8 Documentação e inventários precisam ser reconciliados

A matriz `docs/companies/pr45-status.md` e o código do SHA revisado são as referências atuais. `docs/companies/consumer-coverage.md` já foi marcado como histórico e `docs/companies/invoice-operations.md` já foi atualizado para as ampliações monetárias, portal, notificação e anexos. Não repetir a correção documental antiga como se ainda não tivesse ocorrido.

Há blocos históricos de checkpoints nos documentos, especialmente no handoff BB. Interpretar cada bloco pela versão e pelo SHA; o estado vigente inclui os incrementos 417–423. Ao implementar o próximo domínio, atualizar matriz, guia operacional e capacidade da GUI juntos, sem apagar o histórico útil nem deixar uma pendência antiga contradizer a seção atual.

Reconciliar também `docs/banco-brasil-handoff.md`, `docs/banco-brasil-cnab240.md`, `docs/banco-brasil-cnab400.md` e `docs/banco-brasil-pull-request.md` com cada mudança efetiva. Inventários continuam orientando revisão e não autorizam rollout global. Quantidades lexicais de árvores anteriores não são números atuais de bugs, cobertura ou esforço.

Regenerar inventários para o HEAD efetivo. Atualizar hash não torna consumidor testado. Mudança que invalide uma evidência devolve a entrada a pendente até revisão/reexecução. Os gates de inventário que falharam em incrementos anteriores não são falhas atuais do HEAD 7ff6a149, cujo CI foi aprovado.

Matriz mínima por requisito:
- Domínio/canal e ação.
- Rota/serviço/escritor/leitor/arquivo/job envolvidos.
- Owner/origem e autorização.
- Código/commit.
- Teste implementado e comando realmente executado.
- Ambiente, versão de banco/runtime, SHA e resultado.
- Casos positivos A/B/legado, negativos, concorrência e falha.
- Gate/capacidade habilitado ou bloqueado.
- Pendência técnica ou dependência externa específica.
- Homologação externa quando aplicável.

## 9 Validação obrigatória por incremento e no HEAD final

### Banco de dados e migrations

Usar bancos descartáveis, nunca produção. Reproduzir instalação limpa, upgrade desde a base efetiva e versões intermediárias publicadas, replay, tracking, falha de gravação/readback e recuperação.

Cobrir:
- MySQL e MariaDB nas versões suportadas pela matriz vigente.
- InnoDB, PK integral, índices, FK, collation, tipos e defaults.
- Diferença de metadata DEFAULT NULL: null do driver e expressão SQL não citada NULL são aceitos; DEFAULT 0, literal textual e metadado ausente não.
- Rollback e falhas antecipadas/tardias, incluindo auditoria e tracking.
- Dois processos/conexões independentes realmente disputando o lock, com evidência observável.
- Revogação enquanto o escritor aguarda, perda de conexão, retry e inexistência de escrita parcial.
- Cache de metadata invalidado após DDL; não apagar asserts para fazer MariaDB passar.

SQLite é útil para contrato, mas não comprova locks, engine ou concorrência real. Os ajustes já feitos para observação de locks MariaDB e metadata NULL precisam permanecer.

### HTTP e navegador reais

Usar o CRM real da stack descartável, com banco e servidor HTTP; não substituir tudo por mocks de DOM.

Cobrir login, CSRF, grants/default, wizard, clientes, contatos, portal/reset, faturas, PDF binário, pagamentos, BB e cada domínio novo. Incluir:
- A/B/principal/legado, usuário sem grant e master revogado.
- URL direta, ID cruzado, subrecurso com pai forjado e POST com contexto obsoleto.
- Back/Forward, duas abas, submit repetido, refresh após falha e retomada.
- Upload multipart, nome/tipo/tamanho de arquivo, preview e download.
- Navegação do renderer selado sem carregar módulos/hooks/overrides legados não revisados.
- Fluxos positivos completos, além das rejeições de segurança.
- Acessibilidade básica, foco, teclado, mensagens de estado e ações desabilitadas com motivo.

### Segurança e regressão transversal

Testar servidor, UI, API, serviços, SQL, jobs, callbacks, cron, CLI, filas, arquivos, PDFs, relatórios, exports e transportes. Verificar fuga por OR, JOIN, cache, contador, pesquisa, anexos e relações indiretas.

Preservar suites Asaas, callbacks, origem, writer, notificações, OI, recorrências, barreiras de clientes/faturas, instalação/upgrade, renderer e release. Testes de segurança devem comprovar também que os casos legados autorizados continuam funcionando.

### Suites existentes para orientar a execução

Confirmar comandos, dependências e variáveis nos workflows do HEAD atual antes de rodar. Estes caminhos existem na referência revisada; a lista é um mapa inicial, não substitui a matriz inteira.

Núcleo e operação:
- `tests/issuers_core.php`, `tests/issuers_master_resolution.php`
- `tests/issuers_onboarding.php`, `tests/issuers_onboarding_concurrency.php`
- `tests/issuers_staff_native.php`, `tests/company_context.php`
- `tests/companies/clients.php`, `tests/companies/client_concurrency.php`
- `tests/companies/portal_positive.php`, `tests/companies/portal_concurrency.php`
- `tests/companies/portal_invoices.php`, `tests/companies/portal_invoice_concurrency.php`
- `tests/companies/invoices.php`, `tests/companies/invoice_concurrency.php`
- `tests/companies/invoice_amounts.php`
- `tests/settings/company_assets.php`, `tests/companies/asset_concurrency.php`
- `tests/companies/invoice_delivery.php`, `tests/companies/invoice_delivery_worker.php`, `tests/companies/invoice_delivery_concurrency.php`
- `tests/companies/invoice_attachments.php`, `tests/companies/invoice_attachment_concurrency.php`
- `tests/test_invoice_attachment_storage.py`
- Suites `tests/settings/` e consumidores em `tests/companies/`

Banco do Brasil:
- `tests/banco_brasil_admin.php`
- `tests/banco_brasil_cnab240.php`, `tests/banco_brasil_cnab400.php`
- `tests/banco_brasil_store.php`, `tests/banco_brasil_layouts.php`
- `tests/banco_brasil_layout_migration.php`, `tests/banco_brasil_module_version.php`
- `tests/banco_brasil_mysql.php`, `tests/banco_brasil_layout_mysql.php`, `tests/banco_brasil_mysql_driver.php`
- `tests/banco_brasil_origination_mysql.php`, `tests/banco_brasil_origination_concurrency.php`
- `tests/banco_brasil_reconciliation_mysql.php`, `tests/banco_brasil_reconciliation_concurrency.php`
- `tests/banco_brasil_api_client.php`, `tests/banco_brasil_api_mysql.php`, `tests/banco_brasil_api_concurrency.php`
- `tests/banco_brasil_api_receipt_mysql.php`, `tests/banco_brasil_api_receipt_concurrency.php`

Navegador:
- `tests/company_context_tabs_browser.cjs`
- `tests/companies_onboarding_browser.cjs`
- `tests/companies_portal_browser.cjs`
- `tests/companies_invoices_browser.cjs`
- `tests/banco_brasil_origination_browser.cjs`

Inventário e gates:
- `python3 -m unittest tests/test_company_boundary_inventory.py`
- `python3 scripts/inventory-company-boundaries.py --check`
- `python3 scripts/inventory-company-boundaries.py --full --output /tmp/company-boundaries-full.json`
- `python3 -m unittest tests/test_release_version.py tests/test_package_release.py -v`
- Lint PHP/JS dos arquivos alterados e `git diff --check`.

A matriz revisada usa, entre outros, Empresas em MySQL 8.0/PHP 8.1 e 8.5, BB em MySQL 8.4/PHP 8.3 e MariaDB 11.4/PHP 8.3. Conferir o workflow antes de reproduzir. Variáveis `ISSUERS_TEST_DSN`, `MYSQL_DSN`, usuários/senhas de teste e demais parâmetros devem vir da configuração de teste vigente; não copiar credenciais reais.

Fontes de comandos:
- `.github/workflows/issuer-foundation.yml`
- `.github/workflows/banco-brasil.yml`
- `.github/workflows/deployment-integrity.yml`
- Demais workflows afetados pelo diff, incluindo gates de núcleo/módulos.

### Prova final

Consultar todos os checks do HEAD remoto final depois do push. Distinguir HEAD da branch de merge sintético. Não reutilizar CI do SHA anterior para uma correção posterior, nem contar job pulado como cenário executado.

Separar no relatório: testes implementados, testes executados, resultados observados, validação manual e testes não executados. Quando não rodar uma suite, registrar o bloqueio e o ambiente necessário. Corrigir falhas recuperáveis sem remover asserts, reduzir isolamento ou adulterar evidência.

## 10 Versionamento e publicação

Ler `AGENTS.md` e `CONTRIBUTING.md` atuais antes de alterar. A política nativa vigente prevalece sobre sugestões genéricas de SemVer.

Na referência revisada:
- Toda PR distribuível, inclusive módulos/docs/CI, avança o núcleo com passos nativos contíguos.
- Usar `scripts/apply-release-version.py`; `VERSION`, `ARGWS_VERSION`, configuração, migration e imagens precisam concordar.
- O gerador avança um ID por execução. A PR pode acumular passos completos e contíguos.
- Mudança funcional em módulo, incluindo JS/CSS/views/traduções/runtime, exige nova versão do módulo e nova migration nativa. Sem DDL, usar NOOP documentada.
- Não editar, renomear, remover ou renumerar migrations históricas. Não inventar migration 100.
- Preservar a cadeia do núcleo existente até 423, BB 101–110, Asaas 148 e as migrations dos outros módulos já alterados. Reconfirmar os valores reais se o remoto avançar.
- Os incrementos 417–423 já existem: 417 monetário, 418 imagens/preferências, 419 crédito API/BB109, 420 teste HTTP, 421 equivalência bancária/BB110, 422 notificações e 423 anexos. Não duplicar IDs, renumerar nem reaplicar suas migrations.
- Não fixar a próxima versão usando os números antigos deste ou de outro contexto. Calcular a próxima sobre o HEAD real e executar o gerador/política do repositório.
- O gate estático não substitui instalação, upgrade, tracking nem homologação.
- Não copiar só metadados de versão para outra branch nem alterar produção para satisfazer gate.

Antes de cada publicação:
1. Conferir status, diff e HEAD remoto.
2. Preservar trabalho alheio e serializar pushes.
3. Atualizar versão/migration segundo o escopo real.
4. Executar os testes afetados e o gate contra a base efetiva.
5. Publicar commit e conferir que o remoto aponta para o SHA esperado.
6. Acompanhar CI até estado terminal e corrigir falhas do incremento.
7. Atualizar matriz/PR e continuar o próximo requisito ainda viável.

## 11 Sequência de continuidade para concluir sem recomeçar

1. Reconciliar uma vez o HEAD/base e o diff local. Ler instruções do repositório e somente os commits posteriores a 7ff6a149, se existirem. Confirmar o CI já conhecido, preservar 417–423 e passar ao próximo requisito. ZIPs não substituem o remoto por data/nome e não devem ser extraídos sobre checkout.
2. Fechar o financeiro restante usando os writers e componentes já entregues: créditos e aplicação/desaplicação, refunds/reembolsos, estornos, recorrência e documentos/conversões faltantes. Para cada fluxo, implementar persistência, serviço, GUI/API/job, arquivos e prova A/B/legado/concorrência antes de abrir seu gate.
3. Adaptar Asaas positivo por origem e os relatórios/APIs/consumidores dependentes desses fluxos. Não criar um segundo writer nem contornar o bloqueio com sessão global. Preservar o legado e os perfis de BB atuais.
4. Trabalhar em paralelo, com donos de arquivos distintos, nos domínios independentes: projetos/tarefas/despesas/contratos/tickets e demais leitores/jobs/arquivos; configuração restante e administração gráfica segura. Cobertura transversal precisa acompanhar cada liberação, não ficar para uma etapa indefinida depois.
5. Continuar BB a partir do crédito API explícito e da conciliação240 já prontos: alterações de títulos, webhook mTLS, perfis/encargos/parciais/estornos e transporte quando contratado. Resolver os insumos CNAB400 em paralelo; uma lacuna documental específica do400 não deve parar os domínios internos nem autorizar inventar layout.
6. Integrar cada incremento, executar os testes afetados e publicar na mesma PR com versão/migration sequencial. Serializar pushes e reconferir remoto para não perder trabalho de outro executor.
7. Acompanhar CI do SHA publicado até estado terminal, corrigir falhas recuperáveis e continuar o próximo requisito viável. Reutilizar ambiente/fixtures confiáveis; não reexecutar uma auditoria completa a cada pequena edição, mas executar os gates e a regressão pertinentes e a matriz completa no HEAD final.
8. Fechar matriz, guias operacionais e aceites transversais. Solicitar somente documentos/arquivos/acesso/decisões externos realmente necessários. Distinguir software concluído no escopo acordado de homologação externa ainda não realizada.

Manter um escritor por arquivo/domínio, integrar de forma serializada e usar worktree próprio quando houver outro executor ativo. Não duplicar funcionalidades entregues. Dividir em incrementos serve para concluir com segurança; não é motivo para encerrar após o primeiro checkpoint verde.

Continuar enquanto houver trabalho autorizado viável. Se parar, registrar o bloqueio real, requisito afetado, tentativas, insumo/decisão mínima necessária e trabalho independente ainda possível. Não prometer prazo sem evidência nem reduzir o aceite integral para declarar uma entrega rápida. Se o prazo desejado exigir mudança de escopo, explicitar a decisão ao usuário antes de reduzi-lo.

## 12 Entrega exigida e critério de conclusão

Entregar:
- Resumo dos fluxos realmente concluídos e das diferenças desde o HEAD inicial.
- Commits e HEAD remoto final confirmado na mesma PR45.
- Matriz requisito → código → teste → resultado → limitação.
- Evidência de instalação/upgrade/replay, MySQL/MariaDB, HTTP/Chromium, concorrência e regressões.
- Documentação operacional atual, configuração segura e estados visíveis na GUI.
- Procedimento de implantação e rollback para futura execução autorizada, sem efetuar deploy agora.
- Pendências técnicas explícitas e dependências externas separadas por canal.
- Critérios de homologação externa e resultados reais somente quando existirem.

A PR deve manter branch, título e descrição completos, com contexto, escopo, decisões, arquivos, banco/migrations, APIs/contratos, configuração, segurança, testes implementados/executados/recomendados, validação manual, compatibilidade, riscos, deploy/rollback futuros, documentação e checklist. Usar “não se aplica” quando correto, sem inventar publicação, versão, cobertura ou evidência.

Conclusão integral exige GUI utilizável, administração e acessos corretos, clientes privados/opt-in, operação financeira e demais consumidores isolados, Asaas por origem e BB operacional nos canais e perfis acordados, com testes e documentação consistentes. Falta de homologação deve permanecer explicitamente externa; código incompleto continua sendo pendência técnica. Nenhuma dessas evidências autoriza merge ou deploy.

## 13 Prompt curto para colar na outra IA

> Leia este contexto e continue a implementação na PR45 do wkarts/ARGWS-CRM, branch feat/banco-brasil-billing-cnab240, base main. Parta do HEAD 7ff6a149bcb6b0a39e5ddb7be20a153a7c279632, core 4.2.3/423 e BB 1.1.0/110, reconfirmando o remoto. Não recomece nem refaça o que os incrementos 417–423 já entregaram. Conclua o mais breve possível o financeiro restante, demais domínios/relatórios/APIs, Asaas por origem e ampliações BB/API/CNAB240/CNAB400, reaproveitando os fluxos positivos atuais. Implemente cada fluxo com GUI, serviços, jobs/arquivos e testes A/B/legado, MySQL/MariaDB, HTTP/Chromium e concorrência; preserve os gates até a adaptação correspondente. Paralelize trabalho independente com um escritor por arquivo e pushes serializados. Publique e continue na mesma PR até atender o escopo ou encontrar bloqueio real, sem parar em plano/checkpoint/CI verde. Separe homologação externa de código faltante e peça apenas o insumo externo específico. Preserve histórico, migrations, Asaas, OI e release normal. Sem merge, deploy, force-push, transações reais ou mudanças em develop. Não misture PIGE.

## 14 Fontes de código e estado

Links fixados no SHA revisado para evitar confusão com alterações futuras:
- [Árvore revisada](https://github.com/wkarts/ARGWS-CRM/tree/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632)
- [Matriz funcional atual](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/docs/companies/pr45-status.md)
- [Handoff BB com histórico e incrementos](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/docs/banco-brasil-handoff.md)
- [CompanyRequestScope](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/application/services/CompanyRequestScope.php)
- [IssuerPolicy](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/application/services/issuers/IssuerPolicy.php)
- [IssuerMasterResolution](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/application/services/issuers/IssuerMasterResolution.php)
- [Capacidades BB](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/modules/banco_brasil/libraries/BancoBrasil/CapabilityPolicy.php)
- [Diretório de clientes com histórico identificado](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/docs/companies/private-customers.md)
- [Operações atuais de faturas com portal envio e anexos](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/docs/companies/invoice-operations.md)
- [Cobertura de consumidores identificada como histórico](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/docs/companies/consumer-coverage.md)
- [Instruções do repositório](https://github.com/wkarts/ARGWS-CRM/blob/7ff6a149bcb6b0a39e5ddb7be20a153a7c279632/AGENTS.md)

Em conflito entre texto histórico e comportamento atual, conferir código, teste e execução do mesmo SHA. Não escolher automaticamente a descrição mais otimista nem reapresentar uma limitação antiga como se fosse atual.

