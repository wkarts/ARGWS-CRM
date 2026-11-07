# ARGWS CRM — contexto consolidado para continuidade

Revisão consolidada 4: 6 de novembro de 2026 (UTC). Inclui Banco do Brasil, ponte CRM–PIGE360 e seus documentos recuperados.

Este arquivo reúne as especificações recuperadas e os esclarecimentos posteriores da conversa. É um contrato de requisitos e de continuidade, **não um comprovante de implementação, CI, publicação de PR, homologação ou implantação**. As seções de atualização abaixo prevalecem sobre títulos, destinos, restrições temporárias e interpretações ultrapassadas nos anexos históricos. Os originais foram preservados integralmente para não perder detalhes.

## Sumário

0. Banco do Brasil e ponte CRM–PIGE360: requisitos atuais, atualização e limites históricos
1. Prioridades, escopos e decisões vigentes
2. Asaas: interface, repositórios e cartão
3. Asaas: ciclo completo das faturas e conciliação
4. Reajustes: grid de recorrências e alterações futuras
5. Execução, validação e entrega
6. Evidências e lacunas
7. Anexos integrais A–H: CRM geral, Reajustes, Asaas, dois documentos Banco do Brasil, checkpoint PIGE e transcrições das chamadas

## 0. Correção desta revisão: Banco do Brasil incluído

A revisão anterior omitiu a frente Banco do Brasil. Esta revisão corrige a omissão preservando os três documentos já reunidos, acrescentando **os dois documentos bancários integrais** e registrando os requisitos atuais da primeira parte da chamada de 6/11/2026. O conteúdo bancário abaixo não é substituído pelo resumo de Asaas nem reduzido à remoção de cards.

### 0.1 Banco do Brasil: requisitos atuais confirmados na chamada

**BB01 — Configuração no local correto.** Configurações e parâmetros devem ficar em **Configurações > Meios de pagamento > Banco do Brasil**, com componentes, listas, modais, guias, botões e navegação nativos do CRM. O módulo de referência Asaas orienta o padrão da configuração. Não criar outra aplicação/view que retire menus ou mude o contexto.

**BB02 — API e CNAB separados funcionalmente.** Banco do Brasil via API e Banco do Brasil via remessa CNAB devem ter meios/fluxos identificáveis e parametrização apropriada. O usuário aceita um único módulo com guias próprias para API, CNAB 240 e CNAB 400; não é obrigatório criar módulos independentes. Não tratar um switch “CNAB habilitado” como configuração completa.

**BB03 — Parametrização bancária completa.** Apresentar os campos reais exigidos pelo contrato aplicável: conta, convênio, carteira, Nosso Número e sua composição/posições, número de contrato, código do cliente no banco e outros campos necessários ao leiaute escolhido. Permitir contexto de homologação/produção e configurações apropriadas de conta/convênio. Verificar documentação oficial e contrato da conta; não inventar formatos, dígitos ou valores. Proteger credenciais e segregar empresa/ambiente.

**BB04 — Menu operacional.** No menu principal Banco do Brasil, manter a operação de **remessa e retorno**, incluindo acesso aos arquivos e lotes. A exigência de mover a configuração não significa eliminar esse menu operacional. O pedido posterior de remover cards redundantes também não elimina remessa/retorno.

**BB05 — Integração com faturas e boleto imprimível.** Ao emitir cobrança pelo meio Banco do Brasil/remessa, ela deve entrar automaticamente no lote elegível e disponibilizar boleto imprimível para envio ao cliente, **independentemente de o lote ter sido transmitido**. Distinguir boleto gerado de título registrado/aceito pelo banco; não afirmar registro, validade operacional ou pagamento sem o retorno correspondente. Implementar o documento segundo o convênio/leiaute e validar os requisitos bancários antes de uso real.

**BB06 — Agrupamento automático de cobranças.** Cobranças emitidas em momentos diferentes, inclusive por recorrências, devem se agrupar enquanto houver lote aberto compatível. O agrupamento aceito é por **empresa, conta, convênio e padrão CNAB**; respeitar ainda ambiente e outras incompatibilidades obrigatórias do contrato. Não agrupar pela proximidade dos números de fatura nem criar um arquivo para cada título por padrão. O exemplo discutido foi a cobrança número 1 agora e a 50 depois: ambas podem entrar no mesmo lote ainda não baixado.

**BB07 — Primeiro download fecha o lote.** O primeiro download congela o arquivo/lote. Títulos emitidos depois alimentam um novo lote compatível. O lote já baixado não deve receber novos títulos ou ter bytes alterados silenciosamente. Testar concorrência entre geração de títulos e primeiro download.

**BB08 — Novo download idêntico e estados distintos.** Baixar novamente entrega exatamente o arquivo congelado, não uma regeneração diferente. **Baixado não significa transmitido, aceito, registrado ou liquidado.** Registrar estados e auditoria próprios; envio manual ao banco e retorno real continuam separados.

**BB09 — Retorno e conciliação reais.** Preservar os requisitos integrais dos documentos históricos: deduplicação, vínculo por origem imutável, eventos elegíveis, pagamentos parciais, tarifas, estornos, rejeições, eventos fora de ordem e conciliação assistida. Importar um arquivo observacional não pode ser apresentado como baixa financeira concluída. Não executar baixas reais apenas para demonstrar o recurso.

**BB10 — Aplicativo auxiliar posterior.** Aplicativo multiplataforma em **Tauri + Rust**, baseado no template que o usuário informou possuir, pode vir por último. Deve comunicar pela API do CRM, consultar/obter remessas e apoiar remessa/retorno, permitindo operação pelo aplicativo sem abrir a interface web. Compartilhar a mesma lógica de lotes e estados do CRM, sem inventar transmissão direta ao banco. Disponibilidade do template, autenticação, plataformas e alcance do envio exigem inspeção/definição. Não criar credenciais persistentes nem supor acesso bancário autorizado por este requisito.

**BB11 — Navegação estável, transversal.** Reproduzir o defeito relatado: depois de acessar Banco do Brasil e Definições, o menu Recursos e outros submenus desaparecem, reaparecendo após passar pelo Painel. A fala posterior esclarece que o problema é geral, não só Banco do Brasil. Corrigir o shell/navegação nativos com preservação de menus e contexto, sem atribuir causa técnica não comprovada. A base recente multiempresa continua sendo a frente atual, separada da linha 4.0.2/4.0.3.

### 0.2 Critérios de aceite complementares de Banco do Brasil

- Configuração API/CNAB 240/400 acessível pelo local solicitado, sem depender de editar arquivos internos; menu principal reservado à operação de remessa/retorno.
- Emissão de teste cria vínculo com lote aberto compatível e boleto imprimível sem marcar transmissão/aceite inexistentes.
- Títulos 1 e 50 emitidos em momentos distintos entram no mesmo lote aberto compatível; empresas, contas, convênios, padrões e ambientes incompatíveis não se misturam.
- Primeiro download congela lote e bytes; downloads seguintes produzem o mesmo hash/conteúdo; títulos posteriores abrem/alimentam outro lote.
- Corrida entre emissão e download não duplica título nem altera arquivo congelado. Retry não repete emissão ou baixa.
- Retorno repetido/conflitante e eventos fora de ordem são tratados sem pagamento duplicado, perda de origem ou falso sucesso.
- Boleto, CNAB240, CNAB400 e API têm evidências separadas; homologação de uma capacidade não valida automaticamente as outras.
- Testar troca de empresa, acesso direto por ID, filas, arquivos e navegação sem menus desaparecendo.
- Aplicativo Tauri permanece etapa posterior; não condiciona a entrega da configuração, boleto e lotes no CRM.

### 0.3 Atualidade e conflitos dos documentos bancários históricos

Os anexos D/E registram checkpoints e estado de **18/10/2026** (ou associados àquele checkpoint). PR45/PR46, SHAs, versões 4.0.2/4.0.3/4.0.4, migrations 402/403/404, estado de CI, contagens de testes e lacunas são **evidência histórica**, não fotografia atual do repositório. Não aplicar esses números sobre a base recente, recriar migrations já publicadas nem reabrir uma arquitetura antiga por ler o anexo. Inspecionar o código e a ordem de releases atuais antes de implementar.

A instrução antiga de levar BB à main e alinhar develop é histórico de outra etapa, não autorização atual de merge ou deploy. A linha posterior 4.0.2/4.0.3 de PWA/identidade e a proposta SaaS discutida separadamente não devem ser misturadas à correção da main multiempresa. As falas de 6/11 deixam explícita a correção do CRM recente com multiempresa e navegação nativa. Preservar os requisitos funcionais bancários, revalidando sua implementação real.

Nenhuma inspeção atual de código, CI, banco, arquivo bancário ou homologação foi realizada para esta revisão documental. Uma lacuna histórica pode já ter sido resolvida; uma afirmação histórica de teste aprovado não prova o HEAD atual. Verificar ambos, sem apagar requisitos nem prometer conclusão.

### 0.4 Fonte original da primeira parte da chamada

A transcrição integral da mensagem de voz de 6/11/2026 às 00:17:16 UTC aparece no Anexo F, com seus rótulos de falante. Ela comprova os requisitos e esclarecimentos, não resultados de implementação. Foi consultada diretamente, assim como as falas posteriores sobre navegação e remoção dos cards. Os dois documentos bancários completos estão nos Anexos D/E.


## 0.5 Ponte CRM–PIGE360: inclusão e requisitos atuais

A ponte CRM–PIGE360 é uma frente própria desta conversa e foi omitida nas revisões anteriores. Esta seção corrige a omissão sem substituir Banco do Brasil, Asaas, CRM geral ou Reajustes. O documento recuperado **PIGE_Configuracao_Nativa_107_Recuperado.md** está preservado integralmente no Anexo G. As duas transcrições de origem estão no Anexo H.

### Requisitos confirmados na chamada de 6/11/2026

- **PIGE01 — Localização:** a configuração da ponte deve ficar em **Configurações**, seguindo o padrão nativo do CRM, e não em um menu principal de integrações desconectado da navegação. A reorganização não autoriza apagar outras integrações ou seus históricos.
- **PIGE02 — Várias integrações:** apresentar uma grid/lista nativa que comporte várias integrações PIGE na mesma empresa, com identificação clara e possibilidade de editar e desativar/cancelar uma integração específica. Não reduzir o requisito a uma configuração global única nem confundir escola, empresa e conexão.
- **PIGE03 — Modais com guias:** incluir e editar por modal nativo, com guias e componentes coerentes com o CRM. Não criar views paralelas que desfigurem a aplicação ou façam menus desaparecer.
- **PIGE04 — Conexão simplificada:** reduzir a configuração manual e a cópia de parâmetros entre as pontas. A intenção é informar URL e credencial adequada, testar a conexão e obter/preencher o que a API realmente disponibilizar. A configuração automática completa não deve ser prometida se os contratos ainda exigirem informações prévias.
- **PIGE05 — Evolução bilateral:** após ser informado de que a API atual não preparava tudo apenas com URL e token e de que seria necessário ampliar a integração entre CRM e ponte, o usuário respondeu “Pode fazer”. Isso aprova desenvolver a ampliação descrita; não autoriza inventar contratos, expor segredos, criar acesso persistente ou ativar consentimentos financeiros por inferência.
- **PIGE06 — Multiempresa e navegação:** lista, configurações e ações devem respeitar empresas autorizadas e os contratos efetivos. Corrigir o desaparecimento de menus como problema transversal da navegação. Não presumir que a configuração administrativa do checkpoint já esteja disponível em todos os contextos secundários.
- **PIGE07 — Preservação:** editar/desativar não significa apagar histórico, emitir/baixar fatura ou executar efeito acadêmico/financeiro. Manter origem, revisão, ACL, CSRF, proteção de credenciais e revalidação de consentimentos conforme o contrato real.

### Checkpoint 1.0.7: o que foi relatado e o que falta

O Anexo G descreve um checkpoint de configuração nativa: grid, modais com guias Conexão/Fatura local/Cobrança externa/Como conectar e desativação protegida. Também registra escopo e origem imutáveis, revisão de formulários e revogação/obsolescência de consentimentos ao salvar. **São afirmações do documento recuperado a verificar no commit atual**, não testes realizados nesta consolidação.

O próprio checkpoint explicita que **autoconfiguração por URL/token não foi implementada**. O teste de conexão existente exige escopo completo e não descobre IDs nem cadastra a outra ponta. Portanto, aparência da nova grid, existência de endpoint de diagnóstico ou resposta positiva ao teste não satisfazem, por si só, PIGE04/PIGE05.

O contrato histórico distingue JWT administrativo, permissões escolares e segredo HMAC da ponte. Não reutilizar segredo HMAC financeiro como Bearer administrativo nem ampliar diagnóstico para aceitar escopo incompleto. O documento sugere descoberta limitada a uma conexão, resposta mínima, HTTPS e bloqueio de SSRF/redirecionamentos; isso é proposta técnica a confrontar com a base real, não funcionalidade pronta.

Permanecem pendentes no checkpoint: seletores para IDs administrativos (funcionário, moeda e conta financeira), autoconfiguração bilateral, testes PHP/MySQL/HTTP/navegador, validação de isolamento e homologação dos fluxos de revisão/rotação/desativação. Resolver o que existe hoje por inspeção antes de declarar essas pendências atuais ou encerradas.

### Critérios de aceite adicionais

1. Encontrar a ponte em Configurações; navegar entre Banco do Brasil, ponte, Painel e Definições sem desaparecimento indevido de menus.
2. Criar/consultar/editar/desativar conexões de teste distintas na mesma empresa sem atingir outra conexão ou empresa.
3. Novo → Cancelar → Editar outra conexão não conserva token, dados ou estado do formulário anterior. Segredo salvo não retorna ao navegador.
4. Formulário com revisão obsoleta e permissões revogadas é bloqueado no servidor. Mudanças de configuração não deixam consentimento antigo aparentando estar habilitado.
5. Documentar cada campo descoberto/preenchido pela autoconfiguração e os que continuam manuais; testar credencial inválida, permissão insuficiente, timeout, resposta parcial e destino não autorizado.
6. Ampliar descoberta/configuração somente com contrato bilateral verificável, escopo mínimo e proteção contra SSRF. Não confundir conexão técnica com consentimento financeiro ou autorização de emissão/baixa.
7. Rodar testes reais do fluxo no ambiente apropriado, além de inventário estático e sintaxe. Distinguir resultados por CRM e PIGE360 e registrar commits compatíveis.
8. Preservar migrations existentes e identificar versão atual antes de propor incrementos. Não reaplicar o número 107 automaticamente sobre uma base posterior.

### Ressalvas de atualidade e de escopo

O SHA PIGE360-self, rotas, versão 1.0.7, migration 107 e integração prevista na PR61 são dados do checkpoint recuperado. Este trabalho não consultou o HEAD nem confirmou publicação, merge, CI ou implantação. Revalidar antes de executar. A instrução de nova PR dada especificamente ao Asaas não deve ser aplicada automaticamente à ponte sem reconciliar a frente e o histórico correspondentes.

A autorização de desenvolvimento bilateral não permite usar credenciais de produção, gerar tokens/OAuth ou transmitir informações fora do escopo sem as confirmações cabíveis. Nenhum segredo foi copiado para o consolidado. Produtos, discutido nas mesmas falas, permanece separado; não inferir autorização para unir Produtos aos Itens de vendas ou alterar faturas por causa da ponte.


## 1. Prioridades, escopos e decisões vigentes

- **Banco do Brasil:** frente completa de configuração API/CNAB, emissão, boleto, lotes, remessa/retorno e conciliação, com aplicativo auxiliar posterior. Ver seção 0 e anexos D/E/F.
- **Ponte CRM–PIGE360:** configuração nativa, múltiplas integrações por empresa, modais e evolução bilateral para facilitar a conexão. Autoconfiguração não comprovada pelo checkpoint; ver seção 0.5 e anexos G/H.
- **CRM geral:** aplicar os requisitos R01–R11 do documento histórico à base recente, preservando multiempresa, identidade visual e PWA recentes e tomando a 4.0.2 como referência funcional e de navegação. A versão 4.8.7 é referência informada, não confirmação da versão atual. A frente 4.0.3 permanece separada.
- **Reajustes:** recurso próprio de Faturas recorrentes; atualização das bases que gerarão cobranças futuras. Não é função exclusiva do Asaas.
- **Asaas:** consolidar a integração no CRM, utilizando o módulo 1.4.8 como base de comportamento e apresentação, sem copiar cegamente o ZIP nem perder multiempresa e recursos atuais.
- **PR61:** preservar seu histórico e conferir o estado real antes de integrar dependências. A instrução posterior é fazer o trabalho novo em **nova PR**, não acrescentá-lo indiscriminadamente à PR61. Não executar merge ou implantação por inferência.
- A autorização posterior para começar Reajustes e continuar a frente Asaas supera a anotação antiga de mera coleta para essas frentes específicas. Não transforma o documento geral, sozinho, em autorização irrestrita para todas as áreas do CRM.
- Nenhum documento, print de tarefa concluída ou relato antigo de CI substitui a inspeção do commit e a validação da entrega atual. A conversa ter atingido o limite não prova conclusão nem falha do código.

## 2. Asaas: interface, repositórios e cartão

### 2.1 Resultado solicitado

Manter toda a experiência em **Configurações > Meios de pagamento > Asaas**, com subguias nativas e sem sair para outro painel para configurar, consultar webhook/eventos, logs, operações, transações e conciliação. Remover os grandes cards redundantes Asaas/Banco do Brasil e o agregador visual de meios manuais; preservar os gateways, os meios manuais e seus recursos.

Mascarar credenciais de verdade: não entregar o segredo completo ao navegador e apenas escondê-lo visualmente. Não substituir segredo salvo pela máscara ao salvar outros campos. Preservar empresa/ambiente em configurações, consultas e ações. Reaproveitar o que funciona, sem refatoração bruta. O Asaas continua **módulo de pagamento ligado às Faturas nativas**, jamais um faturamento paralelo.

A fala posterior especifica monitoramento de eventos, logs, transações, conciliação e possibilidade de reconciliar informações relacionadas aos webhooks recebidos. Isso esclarece a intenção de “consolidações” como tratamento da integração, não consolidação contábil entre empresas. Inventariar as ações reais e esclarecer apenas decisões financeiras ainda ambíguas.

### 2.2 Limites por repositório

| Repositório | Papel e limite vigente |
|---|---|
| https://github.com/wkarts/ARGWS-CRM | Destino principal da implementação e da nova PR; adaptar o Asaas 1.4.8 ao multiempresa existente. |
| https://github.com/wkarts/asaas-payment-module-perfex-crm | Referência do módulo Perfex. **Não modificar este repositório nesta frente.** A adaptação posterior por outra IA é etapa distinta. |
| https://github.com/wkarts/asaas-sdk-argws | Referência e dependência. O usuário permitiu alteração **somente se necessária para o suporte a cartão de crédito**. Primeiro verificar o que já existe; não atualizar por rotina nem abrir escopo genérico de SDK. |

Uma resposta intermediária do assistente restringiu toda alteração ao CRM, mas o usuário estabeleceu explicitamente a exceção do SDK, depois reconhecida. Não eliminar essa exceção nem usá-la para alterar o módulo Perfex.

Depois de comprovar o cartão no CRM, preparar um contexto técnico para outra IA adaptar o módulo separado. A preparação desse contexto não equivale a enviar mensagens para qualquer agente externo ou a publicar mudanças naquele módulo.

### 2.3 Cartão de crédito: requisito obrigatório e não comprovado

O usuário informou que a opção atual exibe informação inadequada e **rejeitou expressamente o checkout hospedado atual como atendimento suficiente**. Não concluir a tarefa apenas por encontrar uma opção de cartão à vista ou um link de checkout.

Procedimento exigido:

1. Reproduzir com dados de teste a experiência atual que não atende e registrar precisamente o resultado apresentado.
2. Inventariar a integração atual no CRM, a referência 1.4.8 e os métodos reais do SDK; identificar o que suporta cartão e o que falta.
3. Definir o fluxo adequado sem supor que o usuário pediu captura direta de cartão, parcelamento específico, recorrência de cartão, tokenização ou determinada tela. Se a escolha mudar o produto ou a segurança, apresentar uma decisão objetiva antes de implementá-la.
4. Corrigir no CRM e atualizar o SDK somente se a necessidade for demonstrada. Registrar versões e dependências reproduzíveis.
5. Testar aprovação/recusa simuladas ou sandbox, erro HTTP, timeout, repetição, concorrência com webhook/baixa manual, saldo e isolamento empresa/ambiente.
6. Não armazenar PAN completo ou CVV em banco, logs, fixtures, capturas ou arquivos. Usar mecanismos seguros/documentados do provedor e dados de teste. Nenhuma cobrança real é necessária para comprovar o desenvolvimento.
7. Só declarar suporte comprovado com evidência do fluxo completo. Preparar então o contexto de adaptação para o módulo Perfex, sem modificá-lo nesta frente.

**Estado nesta consolidação:** cartão não foi validado por este trabalho documental; não há confirmação aqui de nova PR publicada ou de implementação concluída.

## 3. Asaas: ciclo completo das faturas e conciliação

O usuário pediu preservar os avanços já descritos para o módulo 1.4.8. A conversa anterior “Atualizar Webhook Asaas” documenta os requisitos abaixo e um relato de implementação na PR31 do módulo separado. Tratar esse relato como histórico a conferir no código, não como teste atual do CRM.

### 3.1 Requisitos explícitos a preservar

- Excluir fatura no CRM deve excluir a cobrança vinculada no Asaas.
- Cancelar fatura deve remover a cobrança correspondente.
- Reativar deve recriar a cobrança, sem duplicar uma já ativa.
- Recebimento manual deve permitir configurar confirmação em dinheiro no Asaas ou remoção da cobrança, preservando o significado de cada ação.
- Manter os critérios existentes de envio de telefone e email ao cadastrar/atualizar cliente. O usuário informou que já funcionavam e pediu não alterar.
- Preservar o fluxo de atualização de fatura que já funcionava.
- Não salvar criação/edição como sucesso quando o Asaas rejeita a operação. O caso concreto era envio de R$ 1,00 rejeitado com HTTP 400 por mínimo de R$ 5,00 líquido de desconto. Validar a regra vigente e o payload efetivo; não extrapolar cegamente esse mínimo para todo produto ou operação futura.
- Aplicar a proteção aos fluxos vinculados ao meio Asaas, sem bloquear indevidamente faturas de outros meios.

### 3.2 Contratos relatados na PR31 que precisam de regressão no CRM

- Na quitação integral manual, executar a ação configurada; recebimento parcial mantém cobrança aberta, sem baixa integral indevida.
- Repetição de webhook não pode duplicar recebimento; concorrência entre checkout, webhook e baixa manual deve ser testada.
- Cobrança já recebida/confirmada não deve ser removida como se estivesse em aberto. Remoção não é estorno ou devolução.
- API rejeitada: preservar/restaurar total, itens, ajustes, vencimento, status, campos personalizados e etiquetas conforme o fluxo transacional real.
- API aceita e banco falha: verificar o resultado local antes de compensação; resultado inconclusivo deve gerar conciliação, não nova criação automática cega.
- Webhook autenticado, URL, método HTTP e dependências existentes devem permanecer compatíveis. Revalidar eventos, autenticação, deduplicação e resposta a entradas inválidas.
- InnoDB, limites de transações/hooks e chamadas diretas ao model foram apontados no histórico. Testar MySQL/MariaDB real quando necessário; SQLite ou fixtures não provam todas essas garantias.
- Não reenviar em lote divergências históricas. Primeiro conferir cobrança existente e vínculo; evitar duplicação.

### 3.3 Observabilidade e reconciliação

Exibir eventos, estados, identificadores de referência sanitizados, resultado de processamento, erro útil e situação de conciliação dentro da guia Asaas. Filtrar tudo por empresa/ambiente autorizado. Uma ação de reprocessar/reconciliar deve mostrar o que fará, respeitar permissões e manter histórico; não transformar reconciliação em autorização genérica para emitir, remover ou receber cobranças reais.

Logs não podem falhar de forma a transformar sucesso financeiro em falsa falha, nem expor credenciais/dados de cartão. Distinguir claramente rejeição do provedor, falha local, resultado desconhecido e compensação pendente. Contadores, detalhes e jobs também devem respeitar o isolamento, não apenas a lista principal.

## 4. Reajustes: grid de recorrências e alterações futuras

Estas decisões posteriores complementam a especificação histórica de Reajustes. Não limitar a seleção a cobranças recentes ou não pagas: o objeto a alterar é a **base recorrente**, cuja primeira fatura pode já estar paga. Não alterar cobranças passadas ou emitir nova cobrança no ato.

### 4.1 Seleção e edição na mesma tela

- Filtrar recorrências por **item** e **meio de pagamento**, no contexto da empresa ativa.
- Na área superior, mostrar item, valor unitário atual de referência e novo valor unitário proposto. O valor atual é consulta, não deve ser sobrescrito antes da confirmação.
- Permitir várias linhas de reajuste, cada uma para um item e seu novo valor. As recorrências selecionadas que contêm aquele item recebem a proposta correspondente.
- Na mesma grid, mostrar hierarquia cliente/recorrência/itens, valores e quantidades atuais e propostas.
- Preservar quantidades de cada cliente por padrão; permitir nova quantidade individual para clientes específicos (inclusive valores fracionários, conforme regras reais do sistema).
- Alterar somente os itens selecionados; os demais permanecem intactos.
- Permitir expandir a linha de um cliente com “+” para adicionar opcionalmente um item só àquela recorrência, exibindo-o na hierarquia da mesma grid.
- Não adicionar automaticamente um item ausente a todos os clientes porque foi incluída nova linha de reajuste geral.
- Diferenciar clientes de suas recorrências: se houver várias para um cliente, identificar exatamente qual será alterada, sem agrupar de forma ambígua.

### 4.2 Simulação, aplicação e registro

Dry run antes da aplicação, com valores/quantidades atuais e novos, totais e impacto por recorrência. Revisar e confirmar a operação em massa. Registrar autor, data, seleção, antes/depois e resultado de cada alteração. A proposta aceita inclui relatório imprimível e possibilidade de aplicar agora ou agendar data; verificar os detalhes no documento histórico e no código.

O padrão vigente é **somente próximas emissões**. Alteração de faturas já emitidas foi discutida como opção futura separada, com cautela especial em remessa bancária; não executá-la nesta entrega por inferência. Não filtrar a base recorrente pelo pagamento da primeira fatura como condição automática de elegibilidade.

A fala “Não, quero que cê comece, por favor. Verifica se a outra IA ainda tá fazendo alguma coisa” autoriza o desenvolvimento pedido e exige cuidado com trabalho concorrente. Usar checkout isolado, reconciliar commits antes de publicar e não sobrescrever a outra frente. Declarações antigas de implementação iniciada não comprovam o estado atual.

### 4.3 Aceite adicional

Testar múltiplos itens, item ausente, inclusão individual, quantidade fracionária, várias recorrências por cliente, filtros combinados, empresa não autorizada, alteração concorrente após dry run, repetição da aplicação e agendamento. A simulação não pode mutar a base; a aplicação não pode alterar faturas históricas nem disparar cobrança imediatamente. Detalhes de arredondamento, conflito de versão e agendamento devem seguir os contratos reais e ser documentados.

## 4.4 Conferência com as falas originais: alteração em massa de recorrências

Revisão documental conferida com as falas de 6/11/2026, de 00:30 a 00:45 UTC. Os requisitos já estavam registrados nesta seção e na **especificação integral do Anexo B**, com 18 seções e 32 critérios de aceite. O quadro abaixo explicita a cobertura; não constitui validação de implementação.

| Decisão da conversa | Onde está detalhada no Anexo B |
|---|---|
| Usar Itens de vendas e deixar Produtos de fora; não alterar cadastro/lista/fluxo existentes | Seções 2, 3 e 18; AC02 e AC32 |
| Ação separada em Faturas recorrentes, com interface nativa | Seções 1, 3, 5 e 9; AC01 |
| Alterar bases recorrentes para futuras emissões, sem emitir nada no ato ou alterar calendário | Seções 2, 9 e 10; AC22–AC24 |
| Não filtrar recorrência pelo pagamento da primeira fatura | Seções 2 e 5.1; AC04 |
| Filtrar por empresa ativa, meio de pagamento e item | Seção 5.1; AC03 e AC29 |
| Item, preço atual somente leitura e novo preço unitário no topo | Seção 5.2; AC05–AC06 |
| Várias regras de itens; preservar itens não selecionados | Seções 5.2 e 6; AC05, AC12 e AC17 |
| Grid na mesma tela, hierarquia cliente/recorrência/itens | Seção 5.3; AC07–AC08 |
| Preservar quantidade por padrão e permitir mudança individual | Seções 5.3 e 6; AC09–AC10 |
| Botão + para adicionar item apenas à recorrência escolhida | Seção 5.4; AC11 |
| Dry run, relatório imprimível, revisão e confirmação antes de aplicar | Seções 8 e 9; AC13–AC19 |
| Agendar por data/valor, revalidar e evitar duplicidade | Seções 9–11; AC20–AC21 e AC25–AC27 |
| Histórico de autor, data, alvos e valores antes/depois | Seções 11–12; AC28 |
| Identificar corretamente itens legados, sem usar descrição como identidade | Seção 7; AC16 |
| Faturas já emitidas: apenas extensão exploratória separada, fora desta implementação | Seções 2 e 14; AC22 e AC32 |

### Nuance explícita do exemplo de honorários

O usuário esclareceu que **quantidade é multiplicador, não porcentagem**. Exemplo ilustrativo discutido: preço unitário de R$ 1.500 e quantidade 0,5 resultam em R$ 750. Quantidades como 0,1, 0,5 ou 2,1 devem manter seu significado nativo; não reinterpretá-las como 0,1%, 0,5% ou 2,1%.

Se o preço proposto passa de R$ 1.500 para R$ 1.700 e a quantidade permanece 0,5, o valor básico proposto é R$ 850, antes de outros componentes nativos aplicáveis. A nova quantidade individual, quando informada, vale apenas para a linha/recorrência escolhida. Usar a precisão, os tributos, descontos e arredondamento reais do CRM.

O preço do catálogo pode continuar sendo editado pelo fluxo normal. Isso **não aplica automaticamente o reajuste às recorrências**: a ação separada simula, apresenta os alvos e aguarda confirmação. Não criar herança automática em cascata ou um novo cadastro de “item base” apenas porque essa alternativa apareceu no raciocínio inicial; o usuário esclareceu depois que a quantidade multiplicadora já existe e que falta o reajuste em massa seguro.

### Cobertura e decisões ainda abertas

Não foi identificada omissão funcional central nas falas comparadas. Esta revisão acrescenta a distinção explícita quantidade/porcentagem e o mapa de cobertura. Os pontos em aberto continuam sendo os do Anexo B, seção 16: fonte do preço atual quando catálogo e recorrências divergem, interação filtros/seleções, item repetido, conflitos de regras, atributos de item adicionado, vínculo legado, precisão, execução parcial, horário/fuso/precedência do agendamento, permissões, snapshots e retenção. São decisões técnicas/comerciais ainda necessárias, não requisitos esquecidos nem autorização para inventar respostas.

As falas comparadas incluem os exemplos iniciais de honorários (00:30:46), separação da ação em massa e agendamento (00:32:52), extensão exploratória para emitidas (00:34:20), confirmação de dry run/futuras emissões (00:39:43), filtros e preço/quantidade (00:41:19), edição individual na mesma tela (00:42:42) e múltiplas regras/adição pelo + (00:45:49). Esta conferência é documental; não confirma código pronto, testes ou aplicação de reajustes reais.


## 5. Execução, validação e entrega

1. Identificar repositório, branch, commit atual, estado da PR61 e trabalho concorrente. Verificar antes de modificar, sem confundir número de PR entre repositórios.
2. Inventariar referências exatas: CRM recente, referência funcional 4.0.2, ZIP Asaas 1.4.8 e SDK. Não afirmar equivalência de versões só pelo nome do arquivo.
3. Separar as frentes CRM geral, Reajustes e Asaas; preservar dependências e limites de autorização. O requisito novo de PR separada prevalece para Asaas.
4. Mapear requisitos para arquivos, serviços, permissões, migrations e testes. Preservar identificadores R01–R11 e critérios históricos; numerar os novos sem colisão.
5. Implementar com escopo controlado, testes de regressão e nenhuma operação financeira de produção.
6. Validar navegação real, desktop/celular/teclado, ausência de vazamento de credenciais, ACL/CSRF, isolamento empresa/ambiente, banco real quando necessário e ciclo de faturas.
7. Publicar a nova PR de desenvolvimento conforme a autorização vigente, **sem merge**. Confirmar seu número/link e os commits efetivos; não inventar PR ou status.
8. Entregar resumo por requisito com estados: implementado, testado localmente, testado em sandbox, pendente ou bloqueado. Separar claramente implementação de implantação.
9. Para cartão, documentar o fluxo comprovado e o que a outra IA precisará adaptar no módulo separado. Não alterar esse repositório nesta frente.

## 6. Evidências e lacunas

### Fontes recuperadas

- Conversa por voz de 6/11/2026, entre 00:39 e 01:11 UTC: ajustes de recorrências, interface Asaas, nova PR, restrição do módulo separado e exceção do SDK.
- Documentos integrais anexados abaixo, com suas próprias referências e critérios.
- [Pedido anterior sobre ciclo das faturas](https://chatgpt.com/c/6aebd9bc-107c-83e9-ab35-04e997d05c09?messageId=9bc445b6-8a9c-4042-9272-3691c5af5fa7).
- [Relato histórico da implementação do ciclo](https://chatgpt.com/c/6aebd9bc-107c-83e9-ab35-04e997d05c09?messageId=7e992b06-254b-5e47-a598-388e08883e6e), referente à [PR31 do módulo Perfex](https://github.com/wkarts/asaas-payment-module-perfex-crm/pull/31), não à nova PR do CRM.

### Trechos exatos das decisões posteriores

<transcript_evidence>
Fonte: falas do usuário na conversa por voz de 6/11/2026, recuperadas do histórico.

00:59 UTC: “Certo. Organizar, pegar o que tem bom aí, trazer pra cá, sem refatoração bruta, sem quebrar, né”

01:05 UTC: “Eu vou aguardar. Eu vou aguardar ela concluir, mas essa que você vai criar aí, você faz uma, abre uma nova PR”

01:06 UTC: “Mas a gente não vai fazer nada, ele não vai fazer nada no, no repositório do módulo. Vai ser tudo dentro do dentro do CRM”

01:07 UTC: “Isso. A única coisa que vai mudar, que pode mudar no repositório, é a SDK, se houver necessidade de atualizar ela pra suporte a cartão de crédito”

01:11 UTC, após o assistente mencionar checkout hospedado de cartão à vista: “Não atende, não”
</transcript_evidence>

### Limites desta recuperação

Não foram realizados testes do CRM, inspeção de código, emissão de cobranças ou publicação de PR para elaborar este documento. O histórico de mensagens retornou partes limitadas e alguns documentos podem conter decisões temporárias superadas. Os anexos não são prova atual do estado de desenvolvimento. Não se declara recuperação de toda conversa já mantida em qualquer serviço; estão cobertos os documentos recuperados e os esclarecimentos recentes verificados.

Ainda precisam ser verificados: revisão exata do CRM e PR61; nova PR e seus checks; equivalência do ZIP 1.4.8 com a fonte; implementação real e experiência desejada do cartão; necessidade concreta de alterar SDK; ações de reconciliação efetivamente disponíveis; detalhes de agendamento/concorrência e migrations de Reajustes. Sem essas verificações, não declarar entrega concluída ou recomendar merge como validado.

Nenhuma chave, token, senha, dado de cartão ou captura com segredos foi incorporado. Capturas e ZIPs citados devem ser consultados de forma segura quando necessários, sem reproduzir segredos na documentação ou PR.

## 7. Anexos integrais A–F: CRM geral, Reajustes, Asaas, dois documentos Banco do Brasil e transcrição da chamada

Os anexos seguintes são preservação histórica. **Em conflito, aplicar as decisões posteriores das seções 0–6.** Em especial, “na PR61” no título antigo do Asaas não define o destino novo, e “aguardar autorização” em documentos antigos não cancela autorização específica posterior nem amplia seu escopo.



---

# Anexo A — Requisitos gerais do CRM (R01–R11, revisão 8)

Arquivo original: `Requisitos_ARGWS_CRM.md`. Conteúdo preservado integralmente abaixo.

# Requisitos para atualização do ARGWS CRM

Registro incremental para execução futura por outra IA

Revisão 8 • 5 de novembro de 2026 • Em coleta de requisitos

## Objetivo e limites

Atualizar futuramente a versão mais recente do ARGWS CRM, com a versão 4.8.7 como referência informada. Este documento registra o que foi solicitado; não autoriza iniciar implementação agora. O trabalho da versão 4.0.3 segue separado. O escopo inclui todo o menu de Vendas e as demais áreas expressamente relacionadas em R10, com todos os seus recursos reais.

O escopo registrado utiliza o padrão multiempresa existente. Não retoma a transformação em SaaS ou multitenancy cancelada. A versão e o código exatos da base de destino devem ser verificados antes da execução futura.

## R01 Vendas e propostas como na versão 402

Status: requisito explícito, ainda não implementado nem validado.

Em Vendas > Propostas, reproduzir o fluxo, a view, o conteúdo e a experiência da versão 4.0.2, preservando o layout funcional de referência dentro do padrão multiempresa da versão de destino. A identidade visual e o PWA devem permanecer iguais aos da versão mais recente, conforme R02. Não redesenhar a interface para realizar essa adaptação.

### Fala original do usuário

“em vendas, propostas, atender ao mesmo fluxo da quatro zero dois, a mesma view, pois são diferentes, né? Harmonicamente, visualmente são views diferentes. Então eu quero que ela tenha a mesma experiência, atender o mesmo conteúdo, o mesmo padrão da quatro zero dois, porém dentro do padrão multi-empresa, né? Sem mudar nada visual”

### Como interpretar o requisito

A versão 4.0.2 é a referência de fluxo, conteúdo e layout funcional de Propostas; a identidade visual e o PWA têm como referência a versão mais recente. A base mais recente é o destino da adaptação. Preservar o padrão multiempresa existente não significa criar uma nova arquitetura de tenants. Não completar lacunas com funcionalidades presumidas.

## R02 Preservar identidade visual e PWA

Status: esclarecimento explícito posterior do usuário. Manter a identidade visual e o PWA iguais aos da versão mais recente, com 4.8.7 como referência informada. Não regredir marca ou PWA ao adaptar Propostas, Orçamentos, Faturas, Faturas recorrentes, Pagamentos, Notas de crédito e Itens à referência funcional 4.0.2.

Usuário: “Identidade visual e PWA”

Assistente: “Esse é o próximo item do documento para a outra IA, certo? O que você quer manter ou mudar na identidade visual e no PWA?”

Usuário: “Não, eu quero que fique igual ao... da versão mais recente”

## Critérios de aceite de R01

Os critérios abaixo operacionalizam o pedido. A referência 4.0.2 ainda precisa ser inspecionada; não se afirma aqui quais campos ou ações ela contém.

- AC01 Fluxo: as etapas e ações observadas em Propostas na versão 4.0.2 estão disponíveis na versão de destino, com o mesmo comportamento para os mesmos cenários.

- AC02 Conteúdo: campos, informações, rótulos e demais conteúdos da view de referência foram inventariados e comparados, sem omissões ou acréscimos não solicitados.

- AC03 Visual: comparação lado a lado, nas mesmas condições de tela e dados, comprova a preservação do layout funcional da versão 4.0.2, com a identidade visual da versão mais recente. Não há redesenho introduzido pela adaptação.

- AC04 Multiempresa: a adaptação respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários já previstos pela base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC05 Evidências: a entrega futura identifica as versões comparadas, os cenários executados, os resultados e eventuais diferenças ainda pendentes. Sem acesso à referência, não declarar equivalência validada.

## Critérios de aceite de R02

AC06 Identidade visual: comparar antes e depois com a versão mais recente e comprovar que os elementos de marca e a identidade visual foram preservados. Não aplicar a identidade antiga por consequência da reutilização de telas.

AC07 PWA: comprovar que a apresentação, configuração e funcionamento existentes do PWA permanecem como na versão mais recente. O inventário e os testes devem partir da base real, sem presumir funcionalidades ainda não verificadas.

## R03 Vendas e orçamentos como na versão 402

Status: requisito explícito, ainda não implementado nem validado.

Aplicar a Vendas > Orçamentos o mesmo requisito solicitado para Propostas: reproduzir a tela, o fluxo, o conteúdo e a experiência de Orçamentos da versão 4.0.2, dentro do padrão multiempresa existente. Preservar a identidade visual e o PWA da versão mais recente, com 4.8.7 como referência informada, conforme R02. Não redesenhar a interface nem retomar SaaS ou multitenancy.

### Fala original do usuário

“Pronto, é o mesmo requisição que foi feito nas propostas, é para ser feito em orçamentos, vendas orçamentos Ouviu aí? Como é que é”

## Critérios de aceite de R03

A equivalência se refere a Orçamentos na 4.0.2, não à cópia da tela de Propostas sobre Orçamentos. O inventário da referência ainda precisa ser realizado.

- AC08 Fluxo: as etapas e ações observadas em Orçamentos na 4.0.2 estão disponíveis no destino, com o mesmo comportamento para os mesmos cenários.

- AC09 Conteúdo: campos, informações, rótulos e demais conteúdos da tela de Orçamentos da referência foram inventariados e comparados, sem omissões ou acréscimos não solicitados.

- AC10 Tela: comparação lado a lado, nas mesmas condições de tela e dados, comprova a preservação do layout funcional de Orçamentos da 4.0.2, com a identidade visual recente e sem redesenho.

- AC11 Multiempresa: Orçamentos respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários já previstos pela base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC12 Identidade e PWA: aplicar também a Orçamentos os critérios AC06 e AC07. A adaptação não deve regredir a marca, a apresentação, a configuração ou o funcionamento existentes do PWA.

- AC13 Evidências: identificar as versões comparadas e os cenários executados em Orçamentos, com resultados e diferenças pendentes. Sem acesso à referência, não declarar equivalência validada.

## R04 Vendas e faturas como na versão 402

Status: requisito explícito, ainda não implementado nem validado.

Aplicar a Vendas > Faturas o mesmo requisito confirmado para Propostas e Orçamentos: reproduzir a view, o conteúdo e o fluxo funcional de Faturas da versão 4.0.2, dentro do padrão multiempresa existente. Preservar a identidade visual e o PWA recentes, com 4.8.7 como referência informada. Não redesenhar a interface nem retomar SaaS ou multitenancy.

### Fala original do usuário para R04 e R05

“Orçamento, faturas, vendas, faturas, faturas recorrentes”

Orçamentos permanece registrado em R03, sem duplicação. Faturas e Faturas recorrentes são os dois acréscimos desta revisão.

## Critérios de aceite de R04

- AC14 Fluxo: as etapas e ações observadas em Faturas na 4.0.2 estão disponíveis no destino, com o mesmo comportamento para os mesmos cenários.

- AC15 Conteúdo: campos, informações, rótulos e demais conteúdos da view de Faturas foram inventariados e comparados com a referência, sem omissões ou acréscimos não solicitados.

- AC16 View: comparação lado a lado, nas mesmas condições de tela e dados, comprova o layout funcional de Faturas da 4.0.2, com a identidade visual recente e sem redesenho.

- AC17 Multiempresa: Faturas respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários da base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC18 Identidade e PWA: aplicar a Faturas AC06 e AC07, preservando a marca, a apresentação, a configuração e o funcionamento existentes do PWA.

- AC19 Evidências: identificar versões comparadas, cenários de Faturas, resultados e diferenças pendentes. Sem acesso à referência, não declarar equivalência validada.

## R05 Faturas recorrentes como na versão 402

Status: requisito explícito, ainda não implementado nem validado.

Aplicar a Faturas recorrentes o mesmo requisito confirmado para Propostas e Orçamentos: reproduzir a view, o conteúdo e o fluxo funcional de Faturas recorrentes da versão 4.0.2, dentro do padrão multiempresa existente, preservando a identidade visual e o PWA recentes, com 4.8.7 como referência informada. Não redesenhar a interface nem retomar SaaS ou multitenancy.

A fala de origem está preservada em R04. A referência é o funcionamento de Faturas recorrentes na 4.0.2, não a cópia de Faturas comuns sobre esse módulo. Não presumir periodicidades, regras de cobrança, integrações ou automações sem inspecionar a base.

## Critérios de aceite de R05

- AC20 Fluxo: as etapas e ações observadas em Faturas recorrentes na 4.0.2 estão disponíveis no destino, com o mesmo comportamento para os mesmos cenários.

- AC21 Conteúdo: campos, informações, rótulos e demais conteúdos da view de Faturas recorrentes foram inventariados e comparados com a referência, sem omissões ou acréscimos não solicitados.

- AC22 View: comparação lado a lado, nas mesmas condições de tela e dados, comprova o layout funcional de Faturas recorrentes da 4.0.2, com a identidade visual recente e sem redesenho.

- AC23 Multiempresa: Faturas recorrentes respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários da base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC24 Identidade e PWA: aplicar a Faturas recorrentes AC06 e AC07, preservando a marca, a apresentação, a configuração e o funcionamento existentes do PWA.

- AC25 Evidências: identificar versões comparadas, cenários de Faturas recorrentes, resultados e diferenças pendentes. Sem acesso à referência, não declarar equivalência validada.

## R06 Corrigir Vendas e pagamentos

Status: falha relatada pelo usuário, ainda não reproduzida, diagnosticada, corrigida ou validada. A causa não é conhecida.

Corrigir Vendas > Pagamentos, que segundo o relato está quebrado, não atende ao padrão, desconfigura a aplicação, apresenta erro e provoca travamento, impedindo a continuidade do uso. Investigar o comportamento antes de atribuir uma causa.

Aplicar o padrão funcional da versão 4.0.2, conforme os demais requisitos de Vendas: view, conteúdo e fluxo de Pagamentos, dentro do multiempresa existente. Preservar a identidade visual e o PWA recentes, com 4.8.7 como referência informada, sem redesenho ou retomada de SaaS ou multitenancy.

### Fala original do usuário

“Vendas, pagamentos, tá quebrado e não atende ao padrão, e tem que contar que deixa tudo desconfigurado, dá erro na aplicação, o sistema trava, não funciona mais”

Esclarecimento posterior: o gatilho relatado é clicar em Vendas > Pagamentos, quando a aplicação toda trava. Esse gatilho ainda não foi reproduzido. O relato de travamento não é atribuído a Notas de crédito nem a Itens.

Fala posterior: “É, a Vendas, ela fica travando, trava a aplicação toda. Quando você clica em Vendas, Pagamentos. Ela precisa voltar a ser o mesmo padrão. Não atende ao padrão Vendas. Notas de crédito também. Vendas, nota de crédito. Venda, itens”

## Critérios de aceite de R06

- AC26 Reprodução e diagnóstico: registrar os passos, o contexto, as mensagens e as evidências da falha. Distinguir o relato do comportamento reproduzido. Se não reproduzir, registrar essa limitação, sem declarar a falha corrigida por suposição.

- AC27 Fluxo e conteúdo: inventariar e comparar Pagamentos com a 4.0.2, preservando etapas, ações, informações e comportamento dos mesmos cenários, sem omissões ou acréscimos não solicitados.

- AC28 View e identidade: comprovar por comparação lado a lado o layout funcional de Pagamentos da 4.0.2 com a identidade visual recente. Aplicar AC06 e AC07 para preservar marca e PWA.

- AC29 Estabilidade: clicar em Vendas > Pagamentos e executar os cenários de Pagamentos e demais passos de reprodução identificados sem erro da aplicação, travamento ou interrupção do funcionamento do sistema.

- AC30 Integridade: comprovar que acessar e operar Pagamentos não desconfigura a interface nem altera indevidamente configurações ou dados. Após a operação, a navegação e o uso dos demais módulos permanecem funcionais.

- AC31 Multiempresa: validar as regras existentes de contexto de empresa, acesso e vínculo de dados em Pagamentos, inclusive a troca de empresa quando disponível, sem mistura indevida de dados nem criação de regras novas.

- AC32 Evidências: registrar versões, cenários, resultados antes e depois, verificações de estabilidade e multiempresa e diferenças pendentes. A conclusão deve indicar o que foi reproduzido e efetivamente validado.

## R07 Vendas e notas de crédito como na versão 402

Status: requisito explícito, ainda não implementado nem validado. Não há atribuição do relato de travamento de Pagamentos a este módulo.

Aplicar a Vendas > Notas de crédito o mesmo requisito confirmado para os demais módulos de Vendas: reproduzir a view, o conteúdo e o fluxo funcional de Notas de crédito da versão 4.0.2, dentro do multiempresa existente, preservando a identidade visual e o PWA recentes, com 4.8.7 como referência informada. Não redesenhar a interface nem retomar SaaS ou multitenancy.

A fala de origem está preservada no esclarecimento posterior de R06. A referência é o próprio módulo de Notas de crédito da 4.0.2; suas ações e informações ainda devem ser inventariadas, sem presumir funcionalidades.

## Critérios de aceite de R07

- AC33 Fluxo: as etapas e ações observadas em Notas de crédito na 4.0.2 estão disponíveis no destino, com o mesmo comportamento para os mesmos cenários.

- AC34 Conteúdo: campos, informações, rótulos e demais conteúdos de Notas de crédito foram inventariados e comparados com a referência, sem omissões ou acréscimos não solicitados.

- AC35 View: comparação lado a lado, nas mesmas condições de tela e dados, comprova o layout funcional de Notas de crédito da 4.0.2, com a identidade visual recente e sem redesenho.

- AC36 Multiempresa: Notas de crédito respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários da base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC37 Identidade e PWA: aplicar a Notas de crédito AC06 e AC07, preservando marca e apresentação, configuração e funcionamento existentes do PWA.

- AC38 Evidências: identificar versões, cenários de Notas de crédito, resultados e diferenças pendentes. Sem acesso à referência, não declarar equivalência validada.

## R08 Vendas e itens como na versão 402

Status: requisito explícito, ainda não implementado nem validado. Não há atribuição do relato de travamento de Pagamentos a este módulo.

Aplicar a Vendas > Itens o mesmo requisito confirmado para os demais módulos de Vendas: reproduzir a view, o conteúdo e o fluxo funcional de Itens da versão 4.0.2, dentro do multiempresa existente, preservando a identidade visual e o PWA recentes, com 4.8.7 como referência informada. Não redesenhar a interface nem retomar SaaS ou multitenancy.

A fala de origem está preservada no esclarecimento posterior de R06. A referência é o próprio módulo de Itens da 4.0.2; suas ações e informações ainda devem ser inventariadas, sem presumir funcionalidades.

## Critérios de aceite de R08

- AC39 Fluxo: as etapas e ações observadas em Itens na 4.0.2 estão disponíveis no destino, com o mesmo comportamento para os mesmos cenários.

- AC40 Conteúdo: campos, informações, rótulos e demais conteúdos de Itens foram inventariados e comparados com a referência, sem omissões ou acréscimos não solicitados.

- AC41 View: comparação lado a lado, nas mesmas condições de tela e dados, comprova o layout funcional de Itens da 4.0.2, com a identidade visual recente e sem redesenho.

- AC42 Multiempresa: Itens respeita as regras existentes de contexto de empresa, acesso e vínculo de dados. Validar os cenários da base, inclusive a troca de empresa quando disponível, sem criar regras novas.

- AC43 Identidade e PWA: aplicar a Itens AC06 e AC07, preservando marca e apresentação, configuração e funcionamento existentes do PWA.

- AC44 Evidências: identificar versões, cenários de Itens, resultados e diferenças pendentes. Sem acesso à referência, não declarar equivalência validada.

## R09 Abranger todo o menu de Vendas

Status: ampliação de escopo explicitamente confirmada pelo usuário, ainda não implementada nem validada.

Aplicar o mesmo padrão funcional da versão 4.0.2 a todo o menu de Vendas: views, conteúdo e fluxos, preservando o multiempresa existente e a identidade visual e o PWA da versão recente, com 4.8.7 como referência informada. Propostas, Orçamentos, Faturas, Faturas recorrentes, Pagamentos, Notas de crédito e Itens são exemplos já citados, não uma lista que limita o escopo.

### Confirmação do usuário

Usuário: “Toda parte de vendas ali, todo menu de vendas”

Assistente: “Se ‘todo o menu de Vendas’ realmente precisa seguir o mesmo padrão, me confirma, que aí eu registro isso também.”

Usuário: “Sim”

## Critérios de aceite de R09

- AC45 Inventário: identificar todas as entradas e subentradas reais do menu de Vendas nas bases de referência e de destino, sem inventar nomes ou restringir a execução aos exemplos citados.

- AC46 Cobertura: registrar, para cada entrada real, a comparação de view, conteúdo e fluxo com a 4.0.2, a adaptação realizada e os testes correspondentes.

- AC47 Preservação: não remover recursos existentes para forçar equivalência. Se uma entrada ou recurso do destino não tiver correspondência na 4.0.2, registrar a diferença e solicitar decisão antes de uma alteração não definida.

- AC48 Multiempresa e identidade: validar o contexto de empresa, acesso e vínculo de dados em todo o escopo; preservar marca e PWA recentes conforme AC06 e AC07, sem redesenho ou nova arquitetura de tenants.

- AC49 Evidências e limites: comprovar a cobertura do inventário completo. Manter o travamento relatado associado especificamente ao clique em Vendas > Pagamentos; não estender esse diagnóstico aos demais itens sem evidência.

## R10 Aplicar o padrão às demais áreas

Status: ampliação explícita do escopo, ainda não implementada nem validada. Não implica relato de falhas nessas áreas.

Aplicar o mesmo padrão geral de view, conteúdo e fluxo funcional da versão 4.0.2 às áreas citadas abaixo, preservando o multiempresa existente e a identidade visual e o PWA recentes, com 4.8.7 como referência informada. Manter todos os recursos existentes, inclusive os que não tenham equivalente na referência.

### Áreas expressamente citadas

Assinaturas; Despesas; Contratos; Projetos; Tarefas; Suporte; módulo Webhooks; módulo API; Leads; Connect API com todos os recursos; Manager; Conector; Chat; Pedido de orçamento; Ajuda online; Utilitários e todas as utilidades.

Os nomes acima reproduzem as áreas mencionadas. A nomenclatura, a estrutura de navegação e os recursos reais de cada área devem ser confirmados na base. Não presumir que API e Connect API são o mesmo módulo nem fundir áreas por semelhança de nomes.

### Fala original do usuário

“É. O mesmo para todos, assinaturas, despesas, contratos, projetos, tarefas, suporte, o módulo Webhooks, o módulo API, Leads, a Connect API, a com todos os recursos, o Manager, o Conector, o Chat, pedido de orçamento, ajuda online, os utilitários, que as utilidades todas, tudo. Vê se deu pra pegar”

## Critérios de aceite de R10

- AC50 Inventário completo: identificar cada área citada, suas entradas e todos os recursos reais na versão de destino; registrar quais têm correspondência verificável na 4.0.2, sem inventar nomes, módulos ou equivalentes.

- AC51 Equivalência aplicável: para cada correspondência real, comparar e adequar view, conteúdo e fluxo funcional à 4.0.2, mantendo a identidade visual e o PWA recentes e documentando as diferenças.

- AC52 Preservação dos recursos: não remover funcionalidades para forçar equivalência. Preservar contratos e comportamentos existentes, inclusive interfaces de API, Webhooks e integrações efetivamente encontradas. Não inventar endpoints, eventos ou integrações.

- AC53 Ausência de equivalente: quando uma área ou recurso for novo ou não existir na 4.0.2, conservar seus recursos e contratos, registrar a ausência e sinalizar conflitos que exijam decisão antes de uma alteração não definida.

- AC54 Multiempresa e identidade: validar o contexto de empresa, acesso e vínculo de dados conforme as regras existentes em todas as áreas aplicáveis; preservar marca e PWA conforme AC06 e AC07, sem criar arquitetura SaaS ou multitenancy.

- AC55 Cobertura e evidências: apresentar comparação e testes por área e recurso, incluindo Connect API completa e todas as utilidades reais. Registrar limitações e pendências; não declarar equivalência ou funcionamento sem validação.

- AC56 Relatos de falha: não atribuir travamentos ou outros defeitos às áreas desta ampliação sem evidência. O travamento relatado permanece especificamente associado ao clique em Vendas > Pagamentos.

## R11 Filtrar a aplicação pela empresa ativa

Status: requisito transversal explícito, ainda não implementado nem validado. Os critérios de verificação abaixo não são relatos de defeitos encontrados.

Ao trocar o emitente ou empresa no seletor existente, toda a aplicação deve filtrar os dados pela empresa ativa, respeitando as autorizações existentes. Dados e ações devem permanecer no contexto autorizado da empresa selecionada, sem informações residuais da empresa anterior nem cruzamento indevido entre empresas.

A referência 4.0.2 abrange também o padrão visual e layout, o fluxo e a navegação, conforme complemento explícito do usuário. Conciliar essa referência com a marca, a identidade visual e o PWA da versão recente, com 4.8.7 como referência informada, já determinados em R02. Não inventar um redesign; apresentar conflitos reais que exijam decisão.

Usar o padrão multiempresa existente. Este requisito não define topologia de bancos, mecanismos técnicos de isolamento ou arquitetura nova e não retoma SaaS ou multitenancy.

### Falas originais do usuário

“É isso. Todas as views padrão, quando troca o emitente, né, quando troca ali que eu mudo a empresa, então os dados, a aplicação toda vai filtrar os dados daquela empresa, mas o padrão é o padrão da 4.0.2”

Complementos: “e padrão visual”, “, fluxo, navegação, isso”

## Critérios de aceite de R11

- AC57 Troca de contexto: alternar entre empresas autorizadas no seletor existente e comprovar que a aplicação passa a usar a empresa ativa, sem exibir dados residuais da seleção anterior.

- AC58 Listas e busca: verificar listas, resultados de busca e filtros aplicáveis em todas as áreas inventariadas, retornando somente dados permitidos no contexto da empresa ativa.

- AC59 Detalhes e ações: validar abertura de detalhes e ações disponíveis, inclusive em registros previamente abertos ou acessados por navegação direta. Não permitir leitura ou alteração indevida de dados de outra empresa.

- AC60 Contadores e relatórios: verificar que contadores, totais, indicadores e relatórios existentes refletem a empresa ativa, sem combinação indevida de informações entre empresas.

- AC61 Integrações: inventariar APIs, Webhooks, conectores e outras integrações existentes e testar o contexto de empresa e as autorizações aplicáveis a dados e ações, preservando seus contratos e sem presumir como o contexto é transmitido.

- AC62 Autorização: validar as permissões existentes de seleção e acesso à empresa, incluindo tentativas de acesso não autorizado, sem ampliar permissões ou introduzir regras não definidas.

- AC63 Padrão visual e navegação: comprovar, nos cenários de troca de empresa, o padrão visual e layout, fluxo e navegação de referência 4.0.2, preservando marca e PWA recentes. Registrar e resolver conflitos antes de alterar decisões do usuário.

- AC64 Evidências: documentar empresas e perfis de teste, áreas cobertas, resultados e pendências, sem confundir critérios derivados com defeitos efetivamente reproduzidos. Não declarar isolamento validado sem testes.

## Informações ainda não definidas

- Localização do repositório, tag ou artefato exato da versão 4.0.2 e da base mais recente a atualizar.

- Inventário detalhado de telas, estados, ações, conteúdos e recursos de todo o menu de Vendas e das áreas de R10 nas bases de referência e destino; identificação de módulos novos sem equivalente na 4.0.2.

- Tratamento de eventual conflito entre a fidelidade à referência e as regras multiempresa existentes. Se houver conflito real, explicá-lo e pedir decisão antes de alterar a experiência.

- Eventual conflito entre layout funcional da 4.0.2 e identidade visual da versão recente deve ser apresentado ao usuário antes de escolher uma alteração não definida.

- Ambiente, evidências técnicas e eventuais passos adicionais ao clique em Vendas > Pagamentos; extensão da desconfiguração relatada; causa e correção adequadas, ainda dependentes de reprodução e diagnóstico.

- Demais requisitos que o usuário ainda ditará. Este registro não encerra nem amplia o escopo.

## Instruções para a próxima IA

Ler a revisão mais recente deste documento e aguardar autorização de execução. Quando a execução for autorizada, aplicar também o requisito transversal R11, identificar as bases exatas, inventariar e inspecionar todo o menu de Vendas e as áreas de R10 na 4.0.2 e na base recente e as regras multiempresa do destino, registrar a comparação e implementar somente o escopo confirmado. Não tratar dúvidas como decisões tomadas nem misturar este trabalho com a frente 4.0.3.

## Histórico de atualização

Revisão 1: registrado R01 a partir da fala do usuário. Revisão 2: registrado R02 e ajustada a interpretação de R01 ao esclarecimento posterior sobre identidade visual e PWA. Revisão 3: registrado R03, estendendo a Orçamentos o mesmo pedido feito para Propostas, com os critérios AC08 a AC13. Revisão 4: registrados R04 para Faturas e R05 para Faturas recorrentes, com critérios AC14 a AC25, sem duplicar Orçamentos. Revisão 5: registrado R06 para corrigir Pagamentos, com o relato ainda não reproduzido e os critérios AC26 a AC32. Revisão 6: especificado o clique em Vendas > Pagamentos como gatilho relatado; registrados R07 para Notas de crédito e R08 para Itens, com AC33 a AC44, sem atribuir o travamento a esses módulos. Na mesma revisão, registrado R09 com a confirmação explícita de abrangência de todo o menu de Vendas e AC45 a AC49. Os subitens citados não limitam o escopo. Revisão 7: registrado R10 com as demais áreas citadas, todos os recursos, preservação de contratos e tratamento de módulos sem equivalente, com AC50 a AC56. Revisão 8: registrado R11 para troca de emitente e contexto da empresa ativa em toda a aplicação, com AC57 a AC64; explicitados padrão visual, fluxo e navegação da 4.0.2, preservando marca e PWA recentes. Novos pontos devem receber identificadores estáveis R12, R13 e seguintes. Correções devem atualizar o requisito correspondente e preservar a decisão mais recente.


---

# Anexo B — Reajustes de recorrências (especificação integral)

Arquivo original: `Especificacao_Reajustes_Recorrencias.md`. Conteúdo preservado integralmente abaixo.

# Especificação do mecanismo de Reajustes em Faturas recorrentes

Revisão 1 — 6 de novembro de 2026  
Situação: especificação para execução futura por outra IA. Não implementado nem homologado por este documento.

## 1. Objetivo e autoridade deste documento

Criar uma seção de Reajustes em Faturas recorrentes para preparar, simular, revisar, confirmar e, opcionalmente, agendar mudanças de preços unitários e quantidades em modelos de recorrência. O efeito é sobre ciclos futuros, preservando os documentos já emitidos.

Este documento consolida as decisões discutidas, incluindo a evolução para múltiplas regras de preço e uma grid hierárquica. Não constitui autorização para executar reajustes reais, emitir documentos, alterar cobranças ou modificar dados de produção. Não contém código nem pressupõe implementação concluída.

As decisões descritas como confirmadas devem orientar a futura implementação. Critérios de segurança e validação explicitam como demonstrar essas decisões; não são alegações de defeitos encontrados. As questões ainda não decididas estão identificadas separadamente e não devem ser resolvidas silenciosamente por suposição.

## 2. Escopo confirmado e exclusões

### 2.1 Dentro do escopo

- Uma seção Reajustes dentro de Faturas recorrentes.
- Uma única tela de trabalho, com interface e navegação nativas do CRM.
- Filtros por empresa ativa, meio de pagamento e item.
- Múltiplas regras no topo: item, preço atual somente leitura e novo preço unitário global por item.
- Grid hierárquica de cliente/recorrência e seus itens.
- Seleção explícita dos alvos da proposta.
- Quantidade atual e nova quantidade opcional por linha de item, específica daquela recorrência.
- Adição opcional de item ausente por meio de um botão +, somente para o cliente/recorrência específico.
- Simulação sem gravação nos modelos, relatório imprimível, revisão e confirmação.
- Aplicação confirmada aos modelos para ciclos futuros ou agendamento por data.
- Histórico auditável e revalidação contra alterações posteriores.
- Identificação confiável dos itens, com tratamento explícito dos vínculos legados.
- Preservação de multiempresa, autorizações e proteções existentes.

### 2.2 Fora do escopo

- Alterar o cadastro, a lista ou o fluxo existente de Itens de vendas.
- Unificar Itens de vendas com o módulo Produtos.
- Emitir faturas, cobranças, boletos ou remessas como efeito do reajuste.
- Alterar o calendário ou a periodicidade da recorrência.
- Modificar faturas históricas, inclusive a primeira fatura.
- Filtrar a elegibilidade pelo estado de pagamento da fatura que originou a recorrência.
- Reajustar automaticamente todos os clientes ao adicionar um item em uma linha.
- Implantar uma nova arquitetura SaaS, de tenants ou de bancos.
- Implementar a extensão exploratória para faturas já emitidas.
- Alterar contratos de APIs ou provedores por suposição.

## 3. Referências de experiência e preservação do sistema

A interface deve seguir o padrão nativo de telas, layout, fluxo e navegação da 4.0.2, conciliado com a identidade visual e o PWA da linha recente. A referência recente discutida é a 4.8.7; a versão exata da base de implementação precisa ser identificada antes do desenvolvimento.

Não criar uma aplicação paralela nem substituir a lista de Itens existente. O usuário continuará editando o cadastro de um item pelo fluxo normal. A preparação do reajuste de recorrências fica separada, na seção Reajustes.

A preservação da identidade, do PWA e do multiempresa não autoriza redesenho arbitrário. Conflitos reais entre as referências devem ser apresentados para decisão.

## 4. Conceitos e distinções obrigatórias

- **Item de vendas:** registro do catálogo existente. Não confundir sua descrição com sua identidade.
- **Modelo de recorrência:** objeto que orienta os ciclos futuros e que será o alvo da aplicação.
- **Linha de item da recorrência:** ocorrência concreta do item dentro de um modelo, com preço, quantidade e demais atributos existentes.
- **Regra de preço:** escolha de um item e de um novo preço unitário global para os alvos selecionados dessa regra.
- **Proposta de reajuste:** conjunto de regras, alvos, alterações de quantidade, adições individuais e eventual data de execução submetido à simulação e revisão.
- **Simulação ou dry run:** cálculo dos efeitos propostos, sem alterar os modelos de recorrência.
- **Confirmação:** aprovação explícita da proposta e dos efeitos apresentados, na revisão correspondente.
- **Agendamento:** execução futura da proposta confirmada, condicionada à revalidação.
- **Snapshot:** registro versionado dos dados relevantes de uma simulação, confirmação ou aplicação.
- **Fatura emitida:** documento histórico independente do modelo futuro; não deve ser alterado pelo mecanismo.

Os nomes acima são conceitos funcionais. Não impõem nomes de tabelas, classes, endpoints ou novos estados de banco.

## 5. Tela única de Reajustes

### 5.1 Contexto e filtros

A tela trabalha na empresa ativa do seletor existente e oferece filtros por meio de pagamento e item. Todos os dados exibidos e todos os alvos alteráveis devem respeitar a empresa e as permissões do usuário.

O filtro por item ajuda a localizar recorrências relacionadas. Não converte descrições semelhantes em vínculos válidos. O estado de pagamento da fatura original não é critério de inclusão ou exclusão.

A combinação exata entre filtro de item e múltiplas regras de topo ainda precisa ser detalhada: por exemplo, como preservar alvos já selecionados ao mudar o filtro. A interface não pode ocultar essa mudança nem ampliar silenciosamente os alvos.

### 5.2 Regras no topo

O desenho vigente admite múltiplas regras, superando a ideia inicial de apenas um item.

Cada regra contém:

1. Identificação do item escolhido no catálogo existente.
2. Valor unitário atual em campo somente leitura.
3. Novo valor unitário global proposto, preenchido uma vez para aquela regra.

“Global” significa o preço aplicado aos alvos explicitamente selecionados daquela regra dentro da proposta. Não significa todo o catálogo, todas as empresas ou todos os clientes.

A fonte exata do preço atual exibido no topo ainda deve ser definida, especialmente quando o catálogo e as recorrências possuem preços diferentes. O preço efetivo de cada linha deve aparecer na grid e na simulação, sem mascarar diferenças.

### 5.3 Grid hierárquica

A grid apresenta a hierarquia:

- Cliente e recorrência.
  - Linhas de itens da recorrência.

Cliente não é uma chave suficiente: um cliente pode ter mais de uma recorrência. A proposta precisa identificar o modelo e a linha efetivamente atingidos.

A grid deve permitir reconhecer:

- Cliente, recorrência e empresa de contexto.
- Item e situação de seu vínculo.
- Seleção do alvo.
- Preço unitário efetivo atual.
- Preço unitário proposto pela regra aplicável.
- Quantidade atual.
- Nova quantidade opcional por linha.
- Alteração proposta, preservação ou bloqueio para revisão.

A quantidade atual é mantida por padrão. Alterar a quantidade de um item em uma recorrência não altera a quantidade do mesmo item em outras recorrências.

A semântica exata dos controles de seleção de pai e filho deve ser definida antes da implementação. Qualquer seleção em lote precisa mostrar claramente os itens e modelos incluídos, sem transformar uma ação local em seleção global.

### 5.4 Adição individual pelo botão +

O botão + permite incluir um item ausente apenas no cliente/recorrência em que a ação foi iniciada. A simulação deve distinguir essa inclusão de um reajuste de linha existente.

A adição não deve inserir o item em todos os clientes, em todas as recorrências do mesmo cliente ou em todos os alvos de uma regra.

A origem do preço proposto, a quantidade inicial, os demais atributos obrigatórios e o comportamento diante de item já presente precisam ser definidos. Não inventar valores padrão comerciais. Se houver mais de uma recorrência para o cliente, o alvo concreto deve ficar inequívoco.

## 6. Regras de cálculo e preservação

Para cada linha selecionada:

- Preservar a quantidade atual se não houver nova quantidade informada.
- Usar a nova quantidade somente para aquela linha quando explicitamente proposta.
- Usar o novo valor unitário global da regra do item quando aplicável.
- Demonstrar preço e quantidade antes e depois.
- Preservar todos os itens não selecionados e todos os atributos fora da alteração aprovada.

O valor básico da linha é preço unitário multiplicado pela quantidade. O total final deve seguir as regras reais do CRM para impostos, descontos, moeda, precisão e arredondamento. Esses detalhes não foram redefinidos nesta discussão e devem ser inventariados, não substituídos por fórmulas novas.

Exemplo ilustrativo, não dado de produção: uma recorrência contém X com quantidade 2 e Y com quantidade 1. Uma regra que muda o preço de X mantém a quantidade 2, salvo edição individual. Y permanece intacto. Se a quantidade proposta de X for 3 para essa recorrência, apenas ela usa 3; as demais mantêm suas próprias quantidades.

É necessário resolver regras duplicadas ou conflitantes para o mesmo item antes da confirmação. Não escolher silenciosamente a primeira ou a última regra. A política de resolução ainda precisa ser definida.

## 7. Identidade de itens e recorrências legadas

Foi identificada a necessidade de tratar recorrências legadas que não possuem um ID de item persistido de forma confiável. Não presumir que esse vínculo já existe em todas as linhas.

Requisitos:

1. Usar identificação confiável quando disponível.
2. Detectar vínculos ausentes, ambíguos ou inconsistentes.
3. Exigir vínculo explícito e revisado antes de incluir uma linha legada na aplicação por item.
4. Não usar descrição como chave de identidade ou como autorização automática de vínculo.
5. Não tratar nomes iguais, preços iguais ou textos parecidos como prova de equivalência.
6. Registrar a origem e a revisão do vínculo, para permitir auditoria.
7. Não liberar a aplicação de alvos sem vínculo confiável apenas porque o dry run conseguiu calcular um total.

Uma eventual sugestão de correspondência não pode equivaler à confirmação do vínculo. A forma de revisão, o responsável autorizado e a estratégia de persistência dos vínculos precisam ser definidos na implementação.

Não executar migração ou associação em massa de dados reais apenas para completar esta especificação.

## 8. Simulação e relatório imprimível

A simulação deve ser executável antes de qualquer aplicação e não deve modificar modelos, cadastro de itens, documentos emitidos ou cobranças.

O relatório deve permitir conferir:

- Empresa ativa e critérios de seleção.
- Regras de preço propostas.
- Cliente, recorrência e linha de item atingidos.
- Tipo de alteração: preço, quantidade, ambos ou adição individual.
- Preço unitário atual e proposto.
- Quantidade atual e proposta.
- Total antes e depois por linha e por recorrência, conforme cálculo nativo.
- Diferenças calculadas.
- Itens preservados ou referência clara à composição não alterada.
- Alvos bloqueados e motivo de revisão.
- Data ou vigência proposta, quando houver agendamento.
- Identificação da proposta, revisão e momento da simulação.

A impressão deve representar a mesma revisão submetida à confirmação. Não apresentar um relatório antigo como se refletisse edições posteriores.

O formato de impressão e eventual exportação adicional ainda não foram definidos. “Imprimível” não obriga PDF, planilha ou serviço externo. Não transmitir o relatório a terceiros automaticamente.

## 9. Revisão, confirmação e aplicação

Fluxo funcional:

1. Abrir Reajustes na empresa ativa.
2. Filtrar e localizar recorrências.
3. Definir uma ou mais regras de preço.
4. Selecionar alvos e, se necessário, propor quantidades individuais.
5. Adicionar item ausente somente ao alvo específico, se solicitado.
6. Resolver os vínculos legados exigidos.
7. Simular e revisar o relatório.
8. Corrigir a proposta quando necessário e gerar nova simulação.
9. Confirmar explicitamente a revisão apresentada.
10. Aplicar aos modelos para ciclos futuros ou agendar a execução.
11. Registrar resultados e eventuais divergências.

Antes de gravar, revalidar a revisão da proposta, os dados de origem, a identidade dos alvos, o contexto de empresa e as permissões.

Alterar regras, seleção, quantidade, adições ou data depois da simulação torna necessária a atualização da simulação e da revisão aprovada. Não aplicar silenciosamente uma proposta diferente da que foi confirmada.

A aplicação modifica apenas os campos e linhas abrangidos pela proposta aprovada. Não deve disparar emissão, cobrança, remessa ou alteração de calendário. Não altera retroativamente faturas já emitidas.

A política transacional em caso de falha de parte dos alvos ainda precisa ser definida. Não declarar sucesso integral se houver falhas. Qualquer aplicação parcial deve ser identificável e não permitir que uma repetição reaplique mudanças sem controle.

## 10. Agendamento e concorrência

O usuário deseja agendamento de reajuste por data e valor, com dry run, confirmação, histórico e verificação de divergências.

- A data deve ser interpretada no fuso configurado para a empresa; verificar essa configuração.
- Não assumir o fuso pessoal do usuário nem UTC como regra comercial.
- O agendamento não altera o calendário de emissão dos modelos.
- No momento da execução, repetir as validações de revisão, dados, vínculo, empresa e autorização.
- Se os dados relevantes tiverem mudado desde a simulação ou confirmação, sinalizar a divergência e impedir aplicação silenciosa sobre a nova situação.
- A execução deve lidar com chamadas duplicadas e tentativas concorrentes sem aplicar o mesmo reajuste duas vezes.
- Uma emissão concorrente não pode observar um estado intermediário inconsistente do modelo.

A política exata para divergências, cancelamento, reaprovação, horário de execução, precedência com a emissão do mesmo dia, tentativas e falhas parciais permanece pendente. A implementação deverá propor uma política explícita e testável antes de adotá-la.

A segurança contra concorrência é requisito. O mecanismo técnico, como controle de revisão, transação ou bloqueio, depende da base existente e não está imposto aqui.

## 11. Snapshots versionados e integridade

Simulações, confirmações e aplicações devem preservar uma trilha confiável do que foi visto, aprovado e executado.

- Manter snapshots versionados dos dados relevantes.
- Associar a confirmação à revisão efetivamente apresentada.
- Registrar a revisão de origem utilizada na revalidação.
- Não sobrescrever silenciosamente a evidência histórica para adaptá-la a um novo formato.
- Se houver hash ou assinatura de integridade no sistema existente, preservar a verificação dos registros antigos.
- Uma evolução do formato exige versionamento explícito e compatibilidade de verificação; não recalcular ou reescrever o passado como se fosse o original.
- Não presumir algoritmo, esquema de serialização ou mecanismo de assinatura sem inspeção da implementação.

A modelagem de snapshots deve distinguir alteração da proposta, alteração dos modelos de origem e evento de execução. Um relatório imprimível precisa ser rastreável ao snapshot correspondente.

## 12. Histórico auditável

O histórico deve permitir reconstruir:

- Quem preparou, revisou e confirmou, conforme as ações existentes.
- Empresa e alvos.
- Regras e alterações individuais.
- Valores e quantidades antes e depois.
- Vínculos legados utilizados e sua revisão.
- Simulação e revisão confirmada.
- Data agendada e fuso utilizado.
- Revalidações, divergências, falhas e resultado da aplicação.
- Identificação de execução suficiente para detectar duplicidade.

A forma de apresentação e a política de retenção devem respeitar a base existente e ainda precisam ser detalhadas. Não guardar segredos, tokens ou credenciais no histórico.

## 13. Multiempresa, permissões e proteção de requisições

- Reutilizar o seletor e as regras multiempresa existentes.
- Vincular consultas, propostas, vínculos legados, snapshots, histórico e execução à empresa correta.
- Validar o contexto no servidor; ocultar linhas na interface não basta.
- Impedir uso de identificadores de outra empresa para leitura ou alteração não autorizada.
- Ao trocar a empresa, não reutilizar seleção ou proposta anterior como se pertencesse à nova empresa.
- Respeitar ACL para consultar, preparar, confirmar, agendar e executar conforme as permissões efetivas do CRM.
- Proteger operações mutáveis contra CSRF, conforme a arquitetura existente.
- Revalidar autorização no caminho de execução agendada, com identidade e política claramente definidas.
- Não ampliar permissões nem criar acesso persistente apenas para facilitar a execução.
- Não alterar contas, credenciais ou integrações bancárias por meio desta função.

A divisão exata de permissões e a identidade usada pelo executor agendado precisam ser definidas. Não inventar novos papéis ou atribuir privilégios automaticamente.

## 14. Extensão futura exploratória

Foi cogitada uma extensão separada para faturas já emitidas e ainda não recebidas, com consulta de status e possível sincronização com provedores, como Asaas, somente quando suportada.

Essa extensão não está fechada e não deve ser implementada neste trabalho. Não modifica o padrão confirmado de atuar somente nos modelos recorrentes para ciclos futuros.

Uma discussão futura teria de considerar pagamentos parciais, status reais dos provedores, contratos de API, documentos históricos e integridade de CNAB/remessas congeladas. A existência dessa discussão não autoriza consultas mutáveis, atualização de cobranças ou alterações de documentos reais.

## 15. Critérios de aceite

Os critérios abaixo são verificações propostas para demonstrar os requisitos, não resultados de testes já executados.

- **AC01 — Localização:** Reajustes está em Faturas recorrentes, em tela única nativa.
- **AC02 — Cadastro preservado:** lista, edição e fluxo existente de Itens de vendas continuam funcionando sem alteração imposta pelo mecanismo.
- **AC03 — Filtros:** empresa ativa, meio de pagamento e item delimitam corretamente a consulta, respeitando permissões.
- **AC04 — Elegibilidade:** recorrência não é excluída apenas porque a fatura original está paga.
- **AC05 — Múltiplas regras:** é possível preparar regras para mais de um item, cada qual com novo preço unitário global inequívoco.
- **AC06 — Valores atuais:** os valores atuais são somente leitura e diferenças entre preços efetivos das linhas não são ocultadas.
- **AC07 — Hierarquia:** cliente, recorrência e linhas de itens são distinguíveis, inclusive quando o mesmo cliente possui múltiplos modelos.
- **AC08 — Seleção:** somente os alvos selecionados e mostrados na simulação entram na proposta confirmada.
- **AC09 — Quantidade padrão:** quantidade atual é mantida quando não há alteração individual proposta.
- **AC10 — Quantidade individual:** alterar uma linha não modifica a quantidade de outras recorrências.
- **AC11 — Adição local:** o botão + inclui item somente no cliente/recorrência escolhido, nunca em todos os clientes ou modelos.
- **AC12 — Preservação:** itens não selecionados e atributos não aprovados permanecem inalterados.
- **AC13 — Cálculo:** a simulação mostra preços, quantidades e totais antes/depois e usa as regras monetárias nativas.
- **AC14 — Dry run:** a simulação não grava mudanças nos modelos nem em documentos ou cobranças.
- **AC15 — Impressão:** relatório imprimível é rastreável à mesma revisão da proposta revisada.
- **AC16 — Legado:** linhas sem vínculo confiável não são aplicadas automaticamente por descrição ou sem revisão.
- **AC17 — Conflitos:** regras conflitantes para o mesmo alvo não são resolvidas silenciosamente.
- **AC18 — Confirmação:** nenhuma aplicação ocorre sem confirmação da revisão correspondente.
- **AC19 — Revisão:** alteração da proposta após simulação exige nova revisão antes da aplicação.
- **AC20 — Concorrência:** edição concorrente dos dados de origem é detectada e não recebe sobrescrita silenciosa.
- **AC21 — Duplicidade:** repetição de requisição ou execução agendada não aplica o mesmo reajuste duas vezes.
- **AC22 — Futuro apenas:** somente modelos para ciclos futuros são alterados; faturas históricas permanecem intactas.
- **AC23 — Sem emissão:** aplicar reajuste não emite fatura, cobrança, boleto ou remessa.
- **AC24 — Calendário:** calendário e periodicidade existentes não são modificados.
- **AC25 — Agendamento:** a execução usa o fuso empresarial verificado e revalida a proposta no momento previsto.
- **AC26 — Falhas:** divergências e falhas são visíveis, auditáveis e não aparecem como sucesso integral.
- **AC27 — Snapshots:** versões antigas e seus hashes, quando existentes, continuam verificáveis após evolução do formato.
- **AC28 — Histórico:** é possível relacionar preparação, confirmação, execução, alvos e valores antes/depois.
- **AC29 — Multiempresa:** testar leitura, alteração, troca de empresa e identificadores cruzados; não há acesso ou escrita indevida.
- **AC30 — ACL e CSRF:** permissões são verificadas no servidor e requisições mutáveis seguem a proteção CSRF aplicável.
- **AC31 — Integração com emissão:** execução concorrente não deixa o modelo parcialmente alterado para um ciclo de emissão.
- **AC32 — Limite de escopo:** não há implementação da extensão exploratória de faturas emitidas nem alteração do módulo Produtos.

## 16. Decisões pendentes antes de implementar os pontos dependentes

1. Versão, branch e modelos de dados exatos da base de destino.
2. Fonte do preço atual do topo quando há divergência entre catálogo e recorrências.
3. Interação entre filtros, múltiplas regras e seleções preservadas.
4. Semântica de seleção de cliente/recorrência e seleção de seus itens.
5. Tratamento de item repetido em múltiplas linhas da mesma recorrência.
6. Política de regras duplicadas ou conflitantes.
7. Preço, quantidade e demais atributos iniciais da adição individual.
8. Processo e persistência do vínculo explícito de itens legados, com responsável autorizado.
9. Valores permitidos, precisão, moeda, descontos, tributos e arredondamento efetivos da base.
10. Política de aplicação parcial, falha, repetição e recuperação.
11. Horário e fuso efetivo do agendamento, precedência com emissões, cancelamento e reaprovação.
12. Permissões exatas e identidade autorizada para execução agendada.
13. Formato de snapshot, revisão e verificação de hash existente.
14. Retenção, visualização e formato imprimível do histórico.
15. Comportamento diante de uma mudança no catálogo após preparação da proposta.

Nenhuma pendência deve ser transformada em decisão do usuário por inferência. A inspeção técnica pode resolver fatos existentes; escolhas novas devem ser explicitadas para revisão.

## 17. Orientação para a IA executora

1. Confirmar autorização e escopo de implementação antes de editar código ou dados.
2. Inspecionar Faturas recorrentes, Itens de vendas, geração de ciclos, multiempresa, ACL, CSRF e mecanismos de agendamento e auditoria existentes.
3. Identificar quais fatos estão comprovados no código e quais ainda dependem de teste operacional.
4. Mapear os requisitos desta especificação às estruturas reais, sem criar equivalências por descrição.
5. Resolver decisões que bloqueiem a segurança ou o comportamento comercial.
6. Implementar somente o mecanismo confirmado, preservando a lista e o fluxo de Itens.
7. Testar os critérios de aceite em ambiente seguro, com dados adequados e sem cobrança ou emissão real.
8. Entregar evidências, limites da validação e pendências. Não confundir teste automatizado, revisão de código e homologação operacional.
9. Não implementar a extensão para faturas já emitidas nem aplicar reajustes de produção sem autorização específica.

## 18. Registro da evolução consolidada

- O módulo Produtos foi deixado fora deste mecanismo; usar Itens de vendas.
- O cadastro e o fluxo de Itens devem permanecer como existem.
- A ação separada foi localizada em Faturas recorrentes.
- A elegibilidade foi corrigida para considerar o modelo futuro, não o pagamento da primeira fatura.
- A ideia inicial de item único no topo evoluiu para múltiplas regras de item e preço.
- A grid evoluiu para cliente/recorrência → itens, com quantidade individual e adição local pelo botão +.
- Dry run imprimível, confirmação, agendamento, histórico e revalidação permanecem.
- Faturas emitidas permanecem fora do escopo; a eventual extensão é apenas exploratória.
- Este documento registra o desenho discutido e não comprova implementação, alteração de dados ou autorização para reajuste real.


---

# Anexo C — Asaas (especificação histórica integral, destino PR61 superado)

Arquivo original: `Especificacao_Consolidacao_Asaas_PR61.md`. Conteúdo preservado integralmente abaixo.

# Especificação de consolidação do Asaas na PR61

Revisão 1 — 6 de novembro de 2026  
Destino: outra IA responsável pela implementação na frente da PR61 do ARGWS CRM.  
Estado: requisitos e roteiro de validação. Nenhuma implementação ou alteração de dados foi realizada para produzir este documento.

## 1. Objetivo

Consolidar a experiência Asaas em **Configurações > Meios de pagamento > guia Asaas**, com subguias para configuração, credenciais, webhook, operações, transações e a área chamada pelo usuário de “consolidações”.

A operação deve acontecer dentro dessa guia, sem obrigar a navegação para uma tela externa do módulo. A implementação deverá combinar as capacidades úteis do módulo de referência Asaas 1.4.8 com as capacidades já existentes no ARGWS CRM, preservando multiempresa, separação de ambientes, segurança, contratos e migrations.

Não substituir o módulo atual pelo ZIP de referência de forma indiscriminada. O ZIP é uma fonte de comparação, não uma autorização para sobrescrever a base ou regredir funcionalidades.

## 2. Referências e limites da evidência

### 2.1 Fontes disponíveis

- Solicitações do usuário na chamada recente, consolidadas nesta especificação.
- ZIP Asaas 1.4.8 fornecido como referência: arquivo no Space identificado por **libfile_eba94148348c8191a9395f0ce905b525**.
- Análise técnica fornecida para este documento: a referência utiliza gateway nativo com **setSettings**, formulário único, chaves de produção e sandbox, logs e webhook.
- Análise da versão atual fornecida para este documento: existem separação por empresa e ambiente, credenciais, operações e conciliação.
- Capturas de tela de referência e de produção discutidas durante a revisão.
- Frente de desenvolvimento: [PR61 do ARGWS CRM](https://github.com/wkarts/ARGWS-CRM/pull/61).

A análise acima foi recebida como contexto de elaboração. Este documento não declara uma nova inspeção independente do ZIP, do código ou das capturas. A IA executora deve verificar as capacidades e os contratos na revisão exata da PR antes de modificar a base.

### 2.2 Registro textual das evidências visuais

As capturas servem para comparar a apresentação de referência com a apresentação atual e fundamentar o pedido de manter a experiência dentro de Meios de pagamento.

A apresentação descrita para mudança inclui cards grandes do Asaas e do Banco do Brasil e um agregador de meios de pagamento manuais. O usuário quer retirar esses elementos dessa apresentação, sem perder meios, configurações ou recursos.

O destino solicitado é uma guia Asaas nativa, com suas áreas internas acessíveis por subguias. Essa descrição é um requisito de resultado, não uma afirmação de que todas as subguias já existam.

**As imagens não estão reproduzidas nem anexadas neste documento.** Não foram transcritos valores de credenciais, tokens, chaves, URLs sensíveis ou outros segredos que possam aparecer nas capturas. Não usar valores de produção encontrados em imagens para testes ou configuração.

Antes da homologação, comparar visualmente as telas reais de referência e de produção com o resultado, usando evidência sanitizada. Capturas existentes não demonstram, por si só, funcionamento de APIs, isolamento entre empresas ou validade de credenciais.

## 3. Decisões confirmadas

1. O acesso principal ao Asaas deve ficar em **Configurações > Meios de pagamento > Asaas**.
2. Configuração, credenciais, webhook, operações, transações e “consolidações” devem ser acessíveis no mesmo contexto, por subguias.
3. Não exigir saída para a tela externa atual do módulo para realizar essas atividades.
4. As credenciais devem aparecer mascaradas.
5. A área de webhook deve apresentar URL e eventos, respeitando segurança e contexto.
6. Retirar os cards grandes Asaas e Banco do Brasil dessa apresentação.
7. Retirar o agregador de meios de pagamento manuais dessa apresentação.
8. Preservar todos os meios manuais e seus recursos.
9. Mesclar capacidades da referência 1.4.8 e do ARGWS, em vez de substituir uma implementação pela outra.
10. Preservar separação por empresa e ambiente, contratos, segurança e migrations existentes.

O termo “consolidações” foi usado pelo usuário. Há uma área de conciliação informada na análise da versão atual. A equivalência entre os termos precisa ser confirmada; não renomear nem criar uma função financeira diferente por interpretação.

## 3.1 Limite explícito de integração com Faturas

Asaas é um gateway/módulo integrado às Faturas nativas do CRM, não uma ferramenta substituta. Não criar faturamento paralelo, cadastro financeiro duplicado ou uma segunda fonte de verdade para as faturas.

Operações, transações e conciliação apoiam as cobranças e os vínculos existentes. Preservar o relacionamento com as Faturas nativas, a baixa, os estados e contratos já utilizados pelo CRM. A consolidação da interface não autoriza uma refatoração bruta que quebre esses fluxos.

Esta delimitação foi acrescentada pelo usuário na chamada e prevalece sobre qualquer interpretação de que as novas subguias formariam um sistema financeiro independente.

## 4. Escopo e exclusões

### 4.1 Dentro do escopo

- Organização da experiência nativa de Meios de pagamento.
- Guia Asaas e subguias internas.
- Integração dos formulários e recursos existentes no mesmo contexto.
- Preservação e mapeamento de capacidades entre referência e base atual.
- Correta seleção de empresa e ambiente.
- Proteção de credenciais e dados.
- Validação de navegação, operações, transações, webhook, logs e conciliação existentes.
- Compatibilidade com atualização de instalações já existentes.

### 4.2 Fora do escopo deste documento

- Implementação imediata, merge ou publicação.
- Substituição cega da base pelo ZIP 1.4.8.
- Remoção de meios manuais, Banco do Brasil ou outros gateways.
- Redesenho funcional do Banco do Brasil além da mudança de apresentação solicitada.
- Exclusão de configurações, transações, logs, histórico ou dados de conciliação.
- Criação de operações Asaas não suportadas ou não identificadas.
- Alteração de cobranças reais, emissão, cancelamento ou movimentação financeira para validar a interface.
- Criação ou ampliação de credenciais e acesso persistente.
- Mudança de contratos externos, eventos ou endpoints por suposição.
- Nova arquitetura SaaS ou multitenancy.
- Renomeação definitiva de “consolidações” para “conciliação” sem esclarecer o significado pretendido.

## 5. Organização da interface

### 5.1 Localização e contexto

A guia Asaas deve estar no fluxo nativo de Configurações > Meios de pagamento, utilizando os componentes e a navegação coerentes com o CRM.

A referência funcional e de navegação geral é o padrão nativo solicitado pelo usuário. Preservar identidade visual e PWA da linha recente e o multiempresa existente. Não criar um painel paralelo com aparência e navegação desconectadas.

A tela deve deixar claro qual empresa e qual ambiente estão em edição ou consulta. A troca de contexto não pode reaproveitar visualmente dados da empresa ou ambiente anterior como se fossem do novo contexto.

### 5.2 Subguias esperadas

As áreas abaixo são requisitos de cobertura. A ordem, o agrupamento exato e os rótulos finais ainda precisam de definição:

- **Configuração:** opções existentes do gateway e comportamento operacional.
- **Credenciais:** credenciais de produção e sandbox, sempre com tratamento seguro e indicação do contexto.
- **Webhook:** URL aplicável, eventos e demais opções já suportadas.
- **Operações:** recursos operacionais existentes, com as mesmas validações e permissões.
- **Transações:** consulta e recursos existentes relacionados às transações.
- **“Consolidações”:** área solicitada pelo usuário; confirmar se corresponde à conciliação existente.

Configuração e credenciais podem exigir agrupamento na mesma subguia ou separação. A decisão de apresentação não pode omitir nenhum recurso. Logs também devem permanecer acessíveis no contexto Asaas; sua posição exata não foi definida.

### 5.3 Permanecer na guia Asaas

Formulários, consultas e ações das áreas listadas devem ser incorporados ao contexto da guia. Não basta trocar um card por um botão que continue redirecionando à tela externa.

Reutilização de componentes e serviços existentes é desejável quando compatível com a base. Este requisito não determina iframe, tecnologia de renderização, estrutura de rotas ou arquitetura de frontend.

Rotas antigas, favoritos e links internos devem ser inventariados. Sua compatibilidade, redirecionamento ou descontinuação controlada precisa ser definida sem quebrar integrações ou acesso a recursos.

### 5.4 Remoção de cards e do agregador

A remoção se refere aos elementos de apresentação descritos, não aos gateways ou meios de pagamento que eles representam.

Antes da alteração:

1. Identificar quais recursos são acessados por cada card ou agrupador.
2. Mapear o acesso equivalente no fluxo nativo.
3. Garantir que configurações e ações continuem disponíveis a quem tem permissão.
4. Só então retirar a apresentação redundante.

Não apagar registros de meios manuais ou desativá-los. O local final exato do acesso ao Banco do Brasil e dos meios manuais deve respeitar o fluxo nativo e as decisões da frente correspondente; não inventar uma reorganização global adicional.

## 6. Mapeamento de capacidades

O inventário abaixo parte da análise recebida. A IA executora deve preencher arquivos, serviços, rotas e testes correspondentes após inspeção, sem tratar o inventário como prova de implementação.

| Capacidade | Referência Asaas 1.4.8 informada | ARGWS atual informado | Resultado exigido |
| --- | --- | --- | --- |
| Configuração nativa | Gateway com setSettings e formulário único | Estrutura atual a mapear | Configuração acessível na guia Asaas nativa, com todos os campos aplicáveis |
| Produção e sandbox | Chaves dos dois ambientes | Separação por empresa e ambiente | Preservar a separação e impedir mistura de contexto |
| Credenciais | Campos no formulário de referência | Gerenciamento existente | Exibição mascarada e atualização segura, sem expor valores |
| Webhook | Recurso existente na referência | Contrato e implementação a verificar | URL e eventos acessíveis em subguia, sem alteração indevida do contrato |
| Logs | Recurso existente na referência | Cobertura atual a inventariar | Preservar acesso e redigir dados sensíveis |
| Operações | Cobertura detalhada a inventariar | Operações existentes | Incorporar recursos reais sem inventar ações novas |
| Transações | Cobertura detalhada a inventariar | Cobertura detalhada a inventariar | Subguia com os recursos reais identificados |
| Conciliação | Cobertura detalhada a inventariar | Capacidade existente informada | Preservar a capacidade; confirmar relação com “consolidações” |
| Multiempresa | Não presumir equivalência com ARGWS | Separação existente | Manter empresa em consultas, alterações e integrações |
| Migrations | Estrutura a inspecionar | Histórico existente | Evolução compatível, sem substituir ou reexecutar destrutivamente o histórico |

Para cada capacidade, registrar na implementação:

- Origem e revisão inspecionadas.
- Campos, ações e dependências encontrados.
- Escopo por empresa e ambiente.
- Permissões e validações.
- Destino na nova apresentação.
- Estratégia de preservação.
- Testes executados e limitações.

Não interpretar uma célula “a inventariar” como autorização para criar a função ou como prova de que ela não existe.

## 7. Configuração e credenciais

- Identificar os nomes e o significado dos campos reais antes de mesclar formulários.
- Preservar opções necessárias em produção e sandbox.
- Mascarar credenciais na interface e impedir vazamento em HTML, respostas de API, logs, mensagens de erro e evidências.
- Não enviar o segredo completo ao navegador apenas para aplicar uma máscara visual.
- Distinguir credencial já cadastrada de campo vazio sem revelar seu conteúdo.
- Uma submissão que não altere a credencial não pode substituí-la pela máscara exibida.
- Evitar que alternar empresa ou ambiente copie ou sobrescreva credenciais de outro contexto.
- Reutilizar mecanismos seguros de armazenamento existentes, verificando sua compatibilidade.
- Não migrar credenciais do ZIP ou de capturas.
- Não introduzir uma opção de revelar segredos sem requisito e avaliação específicos.

A semântica de manter, substituir e remover credenciais deve seguir o contrato existente quando adequado. Se precisar mudar, a decisão e seus efeitos devem ser apresentados antes da implementação.

## 8. Webhook, operações, transações e conciliação

### 8.1 Webhook

Apresentar a URL apropriada e os eventos suportados no contexto da empresa e do ambiente. Inventariar eventos e formas de autenticação reais; não inventar uma lista de eventos com base apenas em aparência de tela.

Preservar validação de origem, autenticação, tratamento de repetição e comportamento de processamento existentes. Não remover parâmetros ou alterar URLs de callbacks apenas para simplificar a interface.

Se uma URL contiver material sensível, não reproduzi-la em documentação, logs ou evidência pública. A apresentação e eventual cópia da URL precisam seguir o mecanismo seguro aplicável.

### 8.2 Operações

Mapear todas as operações já disponíveis e transportá-las para o contexto nativo sem mudar silenciosamente seus efeitos.

A mudança de localização não transforma operações mutáveis em consultas inofensivas. Preservar confirmações, permissões, validação de estado e restrições existentes. Não executar operações de produção para provar que o botão funciona.

### 8.3 Transações

As consultas e ações existentes devem respeitar empresa e ambiente. Preservar identificadores, estados e vínculos reais.

Filtros, paginação, detalhes e ações efetivamente existentes devem ser inventariados e testados. Não prometer recursos não encontrados.

### 8.4 “Consolidações” e conciliação

Manter a conciliação já existente enquanto se esclarece o nome e o resultado esperado pelo usuário.

Não criar consolidação contábil, agregação de empresas, fechamento financeiro ou liquidação por inferência a partir do termo. A implementação depende de confirmação de que a área solicitada corresponde à conciliação atual ou de um requisito adicional explícito.

## 9. Multiempresa, ambientes e segurança

- Validar empresa ativa e autorização no servidor em todas as leituras e alterações.
- Não confiar apenas no seletor visual ou em campos enviados pelo navegador.
- Isolar contexto de produção e sandbox.
- Impedir acesso cruzado por manipulação de identificadores.
- Preservar ACL e proteção CSRF aplicável.
- Revalidar contexto em ações assíncronas e callbacks conforme os contratos reais.
- Não ampliar privilégios ou gerar credenciais para facilitar a consolidação.
- Proteger logs e histórico contra exposição de segredos.
- Verificar tratamento seguro de erros, sem respostas que exponham cabeçalhos de autenticação ou chaves.
- Manter contratos e vínculos usados por outros módulos.

Não presumir banco por empresa ou qualquer topologia de persistência. O objetivo é preservar a arquitetura multiempresa existente, não substituí-la.

## 10. Migrations e compatibilidade

Antes de alterar persistência, inventariar migrations aplicadas, esquema atual, instalação nova e atualização de versões existentes.

- Não sobrescrever migrations históricas para acomodar o ZIP.
- Não apagar configurações ou dados existentes.
- Não importar tabelas e registros do módulo de referência sem mapeamento.
- Preservar os contratos de leitura e escrita usados pelo sistema.
- Se forem necessárias mudanças de esquema, propor evolução compatível e verificável.
- Definir tratamento de dados existentes e recuperação em caso de falha antes de executar migração real.
- Testar atualização e instalação limpa em ambientes apropriados.

Não se afirma que uma nova migration seja necessária. A necessidade só pode ser determinada após inspeção.

## 11. Critérios de aceite

- **AC01 — Localização:** a guia Asaas está em Configurações > Meios de pagamento.
- **AC02 — Contexto único:** as áreas solicitadas funcionam dentro da guia, sem redirecionamento obrigatório à tela externa.
- **AC03 — Cobertura:** configuração, credenciais, webhook, operações, transações e a área esclarecida de “consolidações” estão mapeadas e acessíveis.
- **AC04 — Cards:** os cards grandes Asaas e Banco do Brasil foram retirados da apresentação especificada, sem remoção dos gateways.
- **AC05 — Meios manuais:** o agregador foi retirado da apresentação, preservando meios manuais, registros, configurações e acesso funcional.
- **AC06 — Inventário:** cada capacidade da referência e da base atual possui destino ou justificativa explícita, sem perda silenciosa.
- **AC07 — Credenciais mascaradas:** valores não aparecem em tela, DOM, respostas indevidas, logs ou relatórios.
- **AC08 — Salvar sem troca:** salvar outra configuração não substitui a credencial pela máscara ou por vazio indevidamente.
- **AC09 — Empresa:** consultas e alterações respeitam a empresa e bloqueiam identificadores cruzados.
- **AC10 — Ambiente:** produção e sandbox permanecem separados em configuração, consultas e operações.
- **AC11 — Troca de contexto:** alternar empresa ou ambiente não reaproveita dados nem credenciais do contexto anterior.
- **AC12 — Webhook:** URL, eventos e autenticação correspondem ao contrato real e continuam funcionando sem regressão.
- **AC13 — Operações:** ações existentes mantêm efeitos, validações e permissões, com testes seguros.
- **AC14 — Transações:** recursos existentes são preservados e filtrados no contexto correto.
- **AC15 — Conciliação:** capacidade atual é preservada e o significado de “consolidações” é resolvido antes de uma mudança funcional.
- **AC16 — Logs:** registros continuam acessíveis e não expõem segredos.
- **AC17 — ACL e CSRF:** operações mutáveis mantêm controles no servidor e proteção aplicável.
- **AC18 — Compatibilidade:** rotas e consumidores existentes foram inventariados, sem quebra silenciosa.
- **AC19 — Migrations:** instalação e atualização preservam dados e histórico; nenhuma substituição cega pelo ZIP foi feita.
- **AC20 — Visual:** comparação sanitizada demonstra a experiência nativa solicitada e a preservação de identidade e PWA.
- **AC21 — Evidência segura:** documentação e evidências não contêm chaves, tokens ou capturas com segredos.
- **AC22 — Relato honesto:** a entrega distingue testes locais, sandbox e homologação de produção; não declara integração validada somente pela aparência.

- **AC23 — Faturas nativas:** o Asaas permanece integrado às Faturas do CRM, sem faturamento paralelo nem cadastro financeiro duplicado.
- **AC24 — Vínculos e baixa:** operações, transações e conciliação preservam os vínculos, a baixa e os contratos existentes, sem quebra por refatoração indiscriminada.

## 12. Pendências e decisões necessárias

1. Confirmar se “consolidações” significa a conciliação já existente.
2. Definir a ordem e o agrupamento final das subguias, incluindo configuração/credenciais e localização dos logs.
3. Confirmar a revisão exata da PR61 e a base de versão que receberá a consolidação.
4. Inventariar todos os campos e recursos das duas implementações.
5. Mapear rotas antigas e decidir compatibilidade ou redirecionamento.
6. Confirmar destinos de acesso aos meios manuais e ao Banco do Brasil após a remoção dos elementos de apresentação.
7. Verificar contratos reais de webhook, eventos, autenticação e contexto.
8. Definir tratamento de credenciais caso o comportamento atual não seja compatível com máscara e edição segura.
9. Determinar se há necessidade de migrations novas; não presumir.
10. Definir cenários de teste seguros e quais validações exigirão autorização posterior.

A IA pode resolver fatos por inspeção da base. Não deve converter lacunas de requisito em novas decisões de produto ou autorizações de produção.

## 13. Orientação para a IA executora

1. Ler esta especificação e verificar a autorização vigente antes de implementar.
2. Inspecionar a revisão atual da PR61 e o ZIP 1.4.8, sem sobrepor o ZIP à base.
3. Produzir o mapeamento de capacidades e contratos.
4. Resolver pendências que alterem escopo, comportamento financeiro ou segurança.
5. Consolidar a interface reutilizando componentes e serviços existentes quando adequado.
6. Preservar multiempresa, ambientes, dados, migrations e todos os recursos.
7. Validar critérios com testes e evidências sanitizadas.
8. Reportar o que foi realmente verificado, o que permanece pendente e qualquer incompatibilidade.
9. Não executar alterações financeiras, criar acesso persistente, mesclar ou publicar apenas por este documento.

## 14. Resultado esperado

Uma experiência Asaas completa e nativa em Configurações > Meios de pagamento, com subguias no mesmo contexto, credenciais protegidas e recursos preservados. A referência 1.4.8 orienta a integração nativa; a base ARGWS conserva suas capacidades multiempresa e operacionais. A mudança de apresentação não pode resultar em perda de segurança, meios de pagamento ou funcionalidade.


---

# Anexo D — Banco do Brasil: transferência histórica integral

Arquivo original: `Banco-do-Brasil-continuidade.md`. Preservado integralmente. Ressalvas de atualidade: seção 0.3.

# Banco do Brasil — transferência do trabalho em andamento

Checkpoint remoto confirmado: `ce5588623bbc4f2ea7ba5c15b51fc1903898a3bd`.
https://github.com/wkarts/ARGWS-CRM/commit/ce5588623bbc4f2ea7ba5c15b51fc1903898a3bd

## Ponto de partida

- Repositório público: https://github.com/wkarts/ARGWS-CRM
- PR: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch: `feat/banco-brasil-billing-cnab240`.
- Base revisada: `aa58b832b3c5ed04a599caacba125b056c081ba7` (core 4.0.2 / migration 402).
- Checkpoint de implementação BB: `b799212896756839888a25de4f9f664f271ebb45`, agora composto sobre a base acima.
- Alvo reservado pelo usuário: **core 4.0.3 / migration 403**, módulo **1.0.2 / migrations 101 e 102**. A migration 403 é marcador NOOP de release; o DDL BB fica nas migrations do módulo.
- Esta é uma transferência **incompleta e em rascunho**, não recomendação de merge ou homologação. O próximo commit da branch contém os arquivos descritos aqui. Consulte o SHA atual da PR antes de iniciar alterações concorrentes.

## O que existe

1. Instalação e upgrade nativos `App_module_migration`, validação estrutural InnoDB e tracking somente após sucesso.
2. Cadastro visual de conexões com origem por empresa, conta, ambiente, convênio e carteira; oito guias administrativas.
3. Perfis 240/400 imutáveis, seleção explícita por conexão e nenhum fallback por região/formato.
4. Codec restrito de remessa 240 P/Q e parser de retorno T/U. Parser 400 CBR643 e codificador de candidato CBR641.
5. Importação observacional, deduplicação/conflito de conteúdo, auditoria e proveniência transacionais. Não há baixa financeira, emissão ou envio bancário.
6. Guardas de autorização/contexto que negam operações quando o contrato de Empresas não está disponível. Isso não equivale a integração multiempresa operacional.

## Lacunas obrigatórias

- Adaptar o consumidor ao **núcleo Empresas**, em desenvolvimento separado. O checkpoint ainda usa a dependência histórica `issuers`; sua mera ativação não habilita os novos grants nativos. Não criar outro módulo Empresas nem conceder acesso por `is_admin` global. O adaptador experimental não integra este commit porque não foi validado.
- Obter e verificar a especificação OpenAPI atual do BB; implementar OAuth, proteção de credenciais/configuração visual e os executores de cobrança/consulta/cancelamento suportados pelo contrato. Não inventar endpoints, escopos ou certificados.
- Integrar emissão, origem imutável, reserva de Nosso Número, lotes, download auditado, retry idempotente e reconciliação ao core isolado. Tabelas reservadas não provam funcionalidades conectadas.
- Completar remessa operacional 240 e 400. O candidato 400 está intencionalmente indisponível: código BRL e divergência do picture do trailer exigem confirmação do contrato/manual. Não enviar o candidato sintético ao banco.
- Retorno permanece observação. Definir e testar elegibilidade de baixa, pagamentos parciais, tarifas, estorno, eventos fora de ordem, reimportação e conciliação assistida antes de qualquer escrita financeira.
- Homologar separadamente API, CNAB240 e CNAB400 por conta/convênio/carteira. Sem transações ou dados reais nesta transferência.

## Fontes e aprendizado

Leia `docs/banco-brasil-cnab240.md` e `docs/banco-brasil-cnab400.md`: URLs oficiais BB/FEBRABAN, revisões, hashes dos PDFs e limites de cada codec estão registrados ali. Catálogo: https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/ .

O projeto `wkarts/argws-financial-platform` foi abandonado e nunca homologado pelo usuário. É somente referência de aprendizado; não copiar stubs ou promover suporte presumido.

## Validação neste checkpoint

Reexecução local em PHP 8.5.10 WASM, sem rede bancária: 412 verificações distintas (CNAB240 118; CNAB400 82; store 48; admin 63; migration nativa 36; vínculos de layout 38; upgrade102 27). Há testes que incluem outros testes: não somar novamente os totais repetidos no console.

MySQL 8.4/concorrência/mysqli da **versão102** devem ser confirmados no novo SHA da PR pelo workflow `Banco do Brasil module`. O verde histórico da versão101 não valida102. Browser autenticado, homologação e execução operacional não foram realizados. Os gates de core/módulo também precisam passar no novo SHA.

Com PHP e MySQL de teste disponíveis:

```sh
php tests/banco_brasil_admin.php
php tests/banco_brasil_layouts.php
php tests/banco_brasil_layout_migration.php
# MYSQL_DSN/USER/PASSWORD apontando exclusivamente para banco descartável:
php tests/banco_brasil_mysql.php
php tests/banco_brasil_layout_mysql.php
php tests/banco_brasil_mysql_driver.php
python scripts/check-release-version.py
python scripts/check-module-migration-diff.py --base origin/main --head HEAD
```

## Critérios para encerrar

Configuração visual completa, operações autorizadas entre empresas A/B sem vazamento, origem preservada em jobs/webhooks e documentos, idempotência sob corrida, erros remotos visíveis, retornos reconciliados sem falso pagamento, testes reais de instalação/upgrade/repetição/falha e homologação por capacidade. Cada nova correção funcional do módulo deve incrementar sua versão e acrescentar migration nativa (NOOP quando aplicável); nunca editar migrations publicadas. Coordenar a versão core 403 com a fila de releases e não alterar migrations já aplicadas.



---

# Anexo E — Banco do Brasil e multiempresa: continuidade histórica integral

Arquivo original: `CONTINUACAO-CRM-BANCO-BRASIL-E-MULTIEMPRESA.md`. Preservado integralmente. Ressalvas de atualidade: seção 0.3.

# Continuação do CRM: Banco do Brasil e integração multiempresa

Estado verificado em 18/10/2026. Este documento é um roteiro de transferência, não uma declaração de implementação concluída. Não contém credenciais nem dados de clientes.

## Pedido para a próxima IA

Conclua a integração Banco do Brasil no CRM, incluindo API e CNAB 240/400, partindo do código já publicado. Corrija os testes e conecte os recursos ao núcleo Empresas em desenvolvimento. Preserve o trabalho existente. Não entregue tabelas, interfaces ou stubs como operações bancárias prontas. Diferencie implementação técnica, testes e homologação real.

Antes de escrever, leia AGENTS.md, CONTRIBUTING.md, os documentos bancários e os diffs das duas PRs. Confira os SHAs atuais para não sobrescrever trabalho concorrente.

## 1. Repositório e pontos de partida

- Repositório público: https://github.com/wkarts/ARGWS-CRM
- Main verificada: `aa58b832b3c5ed04a599caacba125b056c081ba7`, core 4.0.2 / migration 402.
- Banco do Brasil: https://github.com/wkarts/ARGWS-CRM/pull/45
  - Branch `feat/banco-brasil-billing-cnab240`.
  - Head `ce5588623bbc4f2ea7ba5c15b51fc1903898a3bd`.
  - Destino main; core 4.0.3 / migration 403; módulo 1.0.2.
  - Incompleta, embora atualmente não esteja marcada como rascunho. Não interpretar isso como aprovação de merge.
- Multiempresa/Asaas/notificações: https://github.com/wkarts/ARGWS-CRM/pull/46
  - Branch `wip/companies-core-checkpoint-5833c02e`.
  - Head remoto `3005ea024dabe79a010b0711f7b774e621db0dcb`.
  - Árvore `02a7f27c15b351687ba36d4d05e9a35fa1348d1b`, idêntica ao checkpoint local 5833c02e entregue ao usuário.
  - 389 alterações em relação à branch do Banco do Brasil.
  - Core 4.0.4 / migration 404; Asaas 1.4.8 / migration 148.
  - Rascunho, ainda incompleta e com falhas de CI.
  - **Seu destino continua sendo a branch da PR45, não main.** O usuário pediu posteriormente apontá-la para main e alinhar develop depois; essa mudança ainda não foi executada.

Não é necessário reconstruir o ZIP dividido entregue no chat: o checkpoint está publicado na PR46. Não há nova implementação posterior a esse snapshot nesta entrega.

## 2. Organização das branches

O usuário quer concluir Banco do Brasil, levar as alterações à main e depois deixar develop alinhada. Não fazer merge automático com checks falhos.

1. Inspecionar a main e as duas PRs atuais.
2. Concluir/corrigir BB na PR45, preservando o histórico.
3. Incorporar as correções necessárias à PR46 sem perder as alterações de Empresas.
4. Se mudar a base da PR46 para main antes de mesclar a PR45, seu diff também incluirá as alterações bancárias. Documentar isso; não afirmar que virou uma PR independente apenas por trocar o destino.
5. A sequência originalmente reservada é 402 → 403 (BB) → 404 (Empresas). Validar as regras de upgrade para qualquer consolidação/reordenação; não apagar migrations nem contornar o gate.
6. Depois de main estar realmente atualizada e validada, integrar main em develop por merge normal, preservando a ancestralidade e quaisquer alterações exclusivas. Não copiar somente VERSION/config, não usar reset/force-push para fingir alinhamento.

Este roteiro não autoriza transações bancárias reais, uso de credenciais de produção ou implantação no cliente. Para ações externas, seguir a autorização e as confirmações exigidas pela ferramenta usada.

## 3. Banco do Brasil: o que já existe

Arquivos principais:
- `modules/banco_brasil/banco_brasil.php`
- `modules/banco_brasil/controllers/Banco_brasil.php`
- `modules/banco_brasil/libraries/BancoBrasil/`
- `modules/banco_brasil/views/index.php`
- `modules/banco_brasil/migrations/101_version_101.php` e `102_version_102.php`
- `tests/banco_brasil_*.php`
- `docs/banco-brasil-cnab240.md`, `docs/banco-brasil-cnab400.md`, `docs/banco-brasil-handoff.md`

Existe instalação/upgrade nativo, validação de estrutura InnoDB, cadastro visual de conexões em oito guias, origem por empresa/conta/ambiente/convênio/carteira, perfis 240/400 imutáveis e seleção explícita por conexão.

Existe codec **restrito** de remessa 240 P/Q e retorno T/U. Para 400, existe parser CBR643 e gerador **candidato** CBR641. A importação de retorno é observacional, com deduplicação, conflito de conteúdo, auditoria e proveniência transacional. Não há conclusão operacional de emissão, envio ou baixa financeira.

O checkpoint BB foi registrado com 412 verificações locais PHP 8.5 WASM. Isso não comprova homologação bancária, todos os casos reais ou compatibilidade com a nova composição multiempresa.

## 4. Banco do Brasil: concluir de verdade

### Contratos e especificações
- Ler as fontes oficiais/revisões/hashes registrados nos dois documentos CNAB. Revalidar a documentação atual BB/FEBRABAN e a OpenAPI oficial antes de implementar endpoints.
- Catálogo oficial de leiautes: https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/
- Não inferir 240/400 pela região do cliente. Usar contrato, convênio, carteira e modalidade efetivamente habilitada.
- Resolver as dúvidas documentadas do candidato 400, inclusive campo BRL e picture do trailer. Não liberar arquivo sintético como homologado.
- `wkarts/argws-financial-platform` é referência de aprendizado abandonada e não homologada. Não copiar stubs como funcionalidades concluídas.

### API e configuração visual
- Implementar autenticação e operações de cobrança/consulta/cancelamento realmente suportadas pela especificação vigente, com tratamento de erros e timeouts.
- Parametrização pelo painel nativo do módulo, por empresa, conta e ambiente. Não depender de editar código ou arquivo interno de container para o cliente configurar.
- Ambiente exclusivo homologação/produção, credenciais protegidas, mascaramento, permissões, CSRF e auditoria sem segredos.
- Webhook próprio quando previsto pelo contrato oficial: autenticação apropriada ao provedor, idempotência, validação de origem e vínculo imutável. Não inventar recursos que a API não suporta.

### Emissão e remessas
- Conectar faturas, origem imutável, reserva concorrente de Nosso Número, geração de lotes, download auditado e reenvio idempotente.
- Completar 240 e 400 dentro dos perfis suportados, com validação de comprimento/encoding/contadores/totais e campos por convênio/carteira.
- Separar estados gerado, baixado, transmitido, aceito/rejeitado e liquidado; baixar arquivo não significa enviar ao banco.
- Definir envio manual e eventual transmissão automatizada somente onde houver contrato implementado. Não simular confirmação bancária.
- Troca de banco/conta não pode redirecionar cancelamento de cobrança antiga à integração atual. Preservar provedor/conta/ambiente/empresa/canal de origem.
- Cobrança já transmitida via CNAB exige tratamento compatível com seu estado; excluir localmente não equivale a baixa no banco.

### Retorno e conciliação
- Ir além da observação: mapear eventos elegíveis a baixa, pagamentos parciais, juros/descontos/tarifas, estorno, rejeição e eventos fora de ordem.
- Reimportação/retry/corrida não podem duplicar pagamentos ou lançamentos.
- Vincular por origem e identificadores bancários corretos, nunca apenas valor, nome ou configuração atual.
- Conciliação assistida para divergências; registrar motivo e evidência. Sem falso pagamento.
- Homologação separada por API, CNAB240 e CNAB400, com indicação clara de capacidades implementadas/testadas/homologadas.

## 5. Integração com Empresas

O destino arquitetural é **Empresas no núcleo**, não outro módulo de emitentes. O BB antigo ainda possui dependências históricas de `issuers`; adaptar conscientemente ao contrato nativo da PR46.

Requisitos definidos pelo usuário:
- Cadastro em Definições/Configurações, acima de Colaboradores; seletor de empresa no topo.
- Empresa original da instalação pertence ao mesmo cadastro. Wizard para nova empresa dentro do CRM, sem tela solta de configuração bloqueando o primeiro acesso.
- Administrador principal comprovado da instalação acessa todas as empresas atuais e futuras. Em legado, não assumir automaticamente ID1 ou promover qualquer administrador global; definir identificação/migração segura.
- Outros colaboradores têm empresas autorizadas e padrão no perfil, além das permissões nativas por recurso.
- Configurações, bancos, SMTP, Connect API, módulos, documentos, jobs, webhooks, arquivos, relatórios e caches respeitam o contexto da empresa.
- Empresa nova não herda credenciais de outra. Dados cadastrais alimentam Informações da empresa sem duplicação.
- Clientes privados por padrão; compartilhamento explícito opcional, sem compartilhar automaticamente histórico financeiro.
- White-label da instalação é distinguido das informações fiscais de cada empresa.

Pendências multiempresa já conhecidas: recuperação de acesso de portal privado por empresa (identidade, templates, fila e transporte), consumidores financeiros e módulos restantes, validação real MySQL/MariaDB/HTTP/navegador/concorrência/instalação/upgrade. Inventários lexicais não representam contagem de bugs nem percentual de conclusão.

## 6. Resultado real do CI da PR46

Head 3005ea0, execução encerrada em 18/10 às 18:31 UTC: 16 workflows; 7 passaram e 9 falharam. Jobs: 21 sucesso, 11 falha, 7 cancelados, 6 ignorados. Não havia pendência em execução.

Falhas confirmadas:
1. Containers e Stacks: quatro asserts regex de `tests/crm_integrations_contract.test.cjs` relacionados ao Asaas. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289652
2. Interface: `Using $this when not in object context`, `application/views/admin/includes/head.php:17`, no teste `crm_ui_branding.php`. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289644
3. Núcleo: `retry confirms success after actual marker write` em `native_migration_tracking.php:38`. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289629
4. Empresas: inventário desatualizado e `activation detects native tracking write failure` em teste de migration. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289622
5. Asaas origem/callback: `failed final tracking caught`, `asaas_callback_version.php:22`; a sintaxe PHP passou. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289681
6. BB na composição: `tracking failure detected`, `banco_brasil_module_version.php:42`. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289692
7. Escopo de faturas: auditoria com 78 divergências, operational_rollout bloqueado; manutenção lança `issuer_storage_failed` ao verificar master_staff_id. https://github.com/wkarts/ARGWS-CRM/actions/runs/39154289680

Passaram: privacidade de clientes, calendário, recorrências independentes, PIGE, política de versões/migrations dos módulos, aviso de atualização e Deployer (CLI/GUI Windows/Linux). A coerência canônica 4.0.4/404 passou; isso não prova tracking no banco.

Hipótese a investigar: parte dos fixtures ficou desatualizada após alterações de contrato. Exemplo: teste de tracking sem APPPATH, enquanto App::upgrade_database_silent carrega IssuerCore por essa constante. **Não corrigir simplesmente removendo asserts ou afrouxando segurança**. Reproduzir, distinguir defeito de produção e fixture incorreto, cobrir o comportamento real e atualizar evidências somente após validação.

## 7. Versionamento obrigatório

- ID nativo sequencial: 393=3.9.3, 399=3.9.9, 400=4.0.0, 401=4.0.1 etc. Não gerar 3.11.x por SemVer genérico.
- Toda PR distribuída inclui incremento do núcleo e migration, mesmo NOOP; todo módulo corrigido incrementa header e ganha migration nativa correspondente.
- Módulo novo inicia 1.0.1, `101_version_101.php`, `Migration_Version_101 extends App_module_migration`, `public function up()`.
- Install chama executor nativo, sem duplicar DDL. Versão declarada, arquivo, classe e tracking instalado precisam concordar.
- Preservar migrations já publicadas/aplicadas; não renumerar histórico para fazer check passar.
- Reavaliar próximo ID contra a base efetiva e a ordem de integração. O salto já autorizado 394→401 é histórico, não precedente para novos saltos.

## 8. Critérios de aceite e entrega

Executar testes de campo fixo CNAB, parsers, roundtrip onde aplicável, validação oficial, idempotência e concorrência. Executar MySQL/MariaDB descartável para instalação limpa, upgrade, repetição e falha de tracking. Testar empresas A/B, acesso direto por ID, credenciais e ambientes distintos, fila/webhook/documentos e ausência de vazamento. Usar dados sintéticos e provedores simulados para testes; identificar claramente limites.

Testes existentes incluem `banco_brasil_admin.php`, `banco_brasil_cnab240.php`, `banco_brasil_cnab400.php`, `banco_brasil_store.php`, `banco_brasil_layouts.php`, `banco_brasil_layout_migration.php`, `banco_brasil_mysql.php`, `banco_brasil_layout_mysql.php`, `banco_brasil_mysql_driver.php`, `banco_brasil_module_version.php`. Consultar os workflows para comandos/dependências exatos.

Entregar commits verificáveis, PRs coerentes, testes do SHA final e uma matriz objetiva do que está implementado, testado e homologado. Documentar qualquer requisito de convênio/manual/credencial ainda necessário. Não declarar conclusão total com placeholders, somente UI, resultados de testes antigos ou gates desativados. Não mesclar checkpoint falho para acelerar a entrega.


---

# Anexo F — Transcrição integral da chamada sobre Banco do Brasil

Fonte: mensagem de voz do usuário em 6/11/2026, 00:17:16 UTC. Rótulos preservados; afirmações do assistente não constituem prova de implementação.

Voice transcript (quoted speech, with speaker labels):
assistant:  onde a gente parou?
user: Oi, sim, vamos lá
assistant:  Lembro, lembro. Você disse que ia testar a 4.0.3, achou uns erros nas rotas, né? Tava vendo com a outra IA... Tô pensando também... outras telas tão fora de padrão, o módulo Asaas, né? Tô te ouvindo. Pode falar.
user:  Pronto. Eu vou te a gente vai fazer uma reflexão aí sobre os módulos bancários, certo
assistant:  Uhum.
user:  Vamos começar pelo Banco do Brasil
assistant:  Certo.
user:  Primeiro, ele está localizado no local errado. Por exemplo, é O módulo do Banco do Brasil, ele deveria tá lá em meios de pagamento ou dentro de
assistant:  Uhum.
user:  configurações, no menu pra ele, não no menu principal. E o que acontece? As telas de configuração CRM, elas não tem nada a ver. Por exemplo, autenticação. Aí tem aqui, um parâmetro pra habilitar o KINAB 240, mas não tem parâmetro para eu informar. É. As configurações, né, que o KINAB precisa, né? Nosso
assistant:  Uhum.
user:  número, né, essas coisas todas, número da carteira no banco, número do contrato, número do código do cliente no banco, né? Então assim, tudo que pertence ao KINAB 240. E eu quero separar, eu quero o Banco do Brasil KINAB 240 como um meio de pagamento e parametrização da carteira, certo? Dentro das configurações lá do meio de pagamento desse banco, seguindo o padrão do CRM que eu eu eu mandei o Asaas aí
assistant:  Uhum.
user:  que eu quero que utilize esse formato, esse Asaas aí. E aí a gente evolui ele, né? Que que acontece? Eh. Aí tem o ambiente, não tem opção de alternar o ambiente ou de habilitar convênio, conta. Talvez isso aqui, essa parte da essa guia da conta seja os parâmetros que eu tô cobrando aqui, é isso mesmo, sobre homologação, produção, é isso mesmo. Carteira, o número de posições, né? Se eu vou usar KINAB 240 ou de 240 ou de tá certo. Esses parâmetros aqui. Apesar de tá fugindo, né? De dentro do do do do do perfil, né, que eu posso falar
assistant:  Uhum.
user: , destoando, né? Da do tema ou da construção visual do CRM. E posicionado num local errado. Então eu quero separar o que é Banco do Brasil. Eh KINAB, padrão KINAB, o que é Banco do Brasil API. Ou pode ser realmente um módulo só, porém uma guia voltado só ao KINAB 240. Só reorganizar isso aqui, né? Ao KINAB 240, ao KINAB 400, né? O padrão de retorno e remessa. Beleza
assistant:  Uhum.
user:  E ao eh e ao API. Essas partes de parâmetro, da configuração do do, do do módulo, né? Do recurso, tem que ficar em configurações lá no meio de pagamento, na área destinada ao Banco do Brasil. Se ele é na parte de API e na parte de KINAB. Beleza
assistant:  Entendi.
user:  A parte de remessa e retorno, essa pode, sim, ficar aqui no menu num local próprio, né? É. Talvez seja melhor aqui mesmo, manter Banco do Brasil, onde ele gera os arquivos de remessa. Tô pensando mover ele pra outro local, mas eu acho que eu vou deixar ele aqui mesmo. Banco do Brasil, arquivos de remessa e retorno, beleza? Eh. E. Eu ficaria aqui somente remessa e retorno. E aqui no menu principal, né
assistant:  Uhum.
user: ? Aonde eu ia eh. Baixar o arquivo do lote da remessa, sobe o que que acontece? Como ele é um meio de pagamento, quando eu gero a uma cobrança, que eu escolho lá que é Banco do Brasil e ele é o padrão remessa, não é isso
assistant:  Isso.
user: ? Ele é o padrão remessa, Banco do Brasil remessa. E aí eu emito essa cobrança, automaticamente ele vai gerar o lote. E automaticamente ele tem que gerar o boleto e imprimível. Dependente do do lote tá transmitido ou não. Beleza
assistant:  Certo.
user: ? Porque eu preciso enviar pro cliente esse esse boleto imprimível. E aí, Tem um outro porém, eu preciso de uma aplicação que a gente pode eu já tenho um padrão aí eh. Uma aplicação que ela pode rodar multiplataforma. Eh. A gente pode construir ela em tauri rust. Eu já tenho um um template
assistant:  Uhum.
user: , certo? Que ela vai comunicar com... com o computador que vai tá instalado lá e ela e eu posso sem conectar no CRM, rec, quer dizer, eu emiti aqui, ele aparece a remessa cá pra mim. No computador também. E eu também posso enviar remessa e retorno. Tanto manualmente dentro da aplicação, como eu posso pegar dentro desse aplicativo que seria algo como é que eu posso dizer, eh. Um recurso a mais Da aplicação
assistant:  Uhum.
user: . A gente a gente pode deixar isso aí por último, pra fazer. Mas ele ia comunicar via API, iria pegar esse arquivo de remessa. E aí tem um porém, né? E eu gero eh. Vários eh cobranças naquele momento ali, eh eu vou gerar aí uma uma recorrência e aonde aquela recorrência, todos é Banco do Brasil. Né? Ou um grupo. Eu posso gerar um lote de vez ou ou ele iria gerar de um em um? Eu tô nessa dúvida, né? Então. Dependendo do momento da emissão e se o lote já foi enviado pro banco, já foi baixado, aí gera um outro lote. Poderia ser um recurso assim, né? Onde o lote foi atualizado. Pra poder eu ter lá um grupo. E não ficar gerando vários arquivos de lote, porque num momento esses lotes vão ser enviado manualmente, beleza
assistant:  Deixa eu amarrar isso direitinho
user: . A gente a gente pode deixar isso aí por último, pra fazer. Mas ele ia comunicar via API, iria pegar esse arquivo de remessa. E aí tem um porém, né? E eu gero eh. Vários eh cobranças naquele momento ali, eh eu vou gerar aí uma uma recorrência e aonde aquela recorrência, todos é Banco do Brasil. Né? Ou um grupo. Eu posso gerar um lote de vez ou ou ele iria gerar de um em um? Eu tô nessa dúvida, né? Então. Dependendo do momento da emissão e se o lote já foi enviado pro banco, já foi baixado, aí gera um outro lote. Poderia ser um recurso assim, né? Onde o lote foi atualizado. Pra poder eu ter lá um grupo. E não ficar gerando vários arquivos de lote, porque num momento esses lotes vão ser enviado manualmente, beleza
user: ? E aí é o seguinte
assistant: .  Vamos lá, Wallace, eu agruparia
user:  Pode falar
assistant:  as cobranças por empresa, conta, convênio e padrão CNAB, né? Então uma recorrência com várias cobranças alimenta
user:  Sim
assistant:  esse grupo. Quando você baixar o arquivo para enviar ao banco, essa remessa fica fechada, e novas cobranças vão para a próxima. Baixar não é transmitir, são estados separados. E se você baixar de novo, entrega exatamente o mesmo arquivo pra evitar
user:  Hm
assistant:  versões diferentes do mesmo lote. Enquanto isso, a parte de configurar API, CNAB 240 e 400 fica nas configurações de meios de pagamento, e não no menu principal. E o aplicativo Tauri pode vir numa segunda etapa, consumindo
user:  Isso
assistant:  essa mesma lógica pela API. Até aqui faz sentido?
user:  Faz, sim. Eu acredito que você entendeu bem. Agora, vam volta essa parte do grupo aí que ficou meio vago pra mim, essa sua sugestão, como foi
assistant:  Claro.
user:  Faz, sim. Eu acredito que você entendeu bem. Agora, vam volta essa parte do grupo aí que ficou meio vago pra mim, essa sua sugestão, como foi
assistant:  Pensa assim...
user:  A gente vai criar um grupo de recorrência
assistant:  você gera dez cobranças do Banco do Brasil, elas ficam juntas lá. Antes de baixar a remessa, você gera outras cinco pra mesma empresa e configuração bancária. Pronto, ficam quinze juntas.
user:  Em tempos
assistant:  Isso
user:  diferentes
assistant: , em tempos diferentes. Mas...
user:  Vamo dizer que eu, vamo dizer que eu gerei a a cobrança número 1 agora, a 50 depois, mas nenhuma delas foi baixada, ele vai agrupar automaticamente
assistant:  Uhum. Ahm.
assistant:  Vai. Todas as que tão abertas e não foram baixadas vão pra mesma remessa. Não é pela numeração, não.
user:  Como seria
user:  Hmmmm. Perfeito, é isso, aí falou minha língua
assistant:  Ótimo. Então não importa a ordem nem o intervalo de tempo. Enquanto a remessa estiver aberta, tudo... Isso
user:  Isso
assistant: , tudo da mesma empresa, conta e convênio entra no mesmo pacote. Quando baixar, fecha aquele pacote e começa outro.
user:  Perfeito. Aí sim. Baixou, determina que aquela remessa tá fechada, aquele grupo ali, né? Aquele lote
assistant:  Isso. Isso.
user:  Feito o download, o lote tá fechado
assistant:  Exatamente. E
user:  Perfeito
assistant:  o sistema não mexe mais nele. Só cria
user:  Aí
assistant:  o próximo com o que vier depois.
user:  Perfeito. Aí agora vamo pensar o seguinte. Digamos lá que eu tenho eh x clientes e aí eu crio um produto, é uma coisa que eu quero criar. A gente tem lá itens, né, na vendas, o item da fatura
assistant:  Uhum.
user: . E eu criei
assistant: tá...
user:  também, uma, um módulo aqui, deixa eu ver se ele tá disponível, que é o módulo produtos. Eu não sei se eu devo usar ele, deixa eu ver aqui. Um instante, viu? Mas eu vou, enquanto, enquanto eu procuro
assistant:  Claro.
user: , há uma coisa que eu notei também, por exemplo, se o clico em algum recurso, outro some, por exemplo, em algum link, eu tô eu selecionei o menu Banco do Brasil. Se eu clicar em
assistant:  Uhum.
user:  definições, o submenu, o menu recursos some. Ele não fica disponível pra eu clicar nele, não. Aí se eu clicar em painel, né? E depois eu clicar em definições, aí o menu recursos aparece. Então o que acontece? Várias situações, parecendo que é uma view nova fica trocando, e aquela view nova não não tem o menu
assistant:  Uhum.
user: . Eu não quero views novas, eu quero o mesmo sistema da 4.0.2 porém aprimorado para né, no caso dessa versão aí que a gente tá tentando corrigir aí, salvar a a da main, né


---

# Anexo G — Configuração nativa PIGE 1.0.7: documento recuperado integral

Arquivo recuperado: `PIGE_Configuracao_Nativa_107_Recuperado.md`. Preservado integralmente; o checkpoint não comprova estado atual.

# Configuração nativa PIGE 1.0.7

A grade usa o inicializador de tabela inline do CRM quando disponível e permanece legível como tabela HTML. Nova integração e Editar abrem modal Bootstrap nativo com guias Conexão, Fatura local, Cobrança externa e Como conectar. Desativar exige confirmação visual e POST protegido. Não há exclusão de históricos, novo provider ou ativação automática.

O formulário de edição carrega apenas dados públicos autorizados; token nunca retorna. Escola/empresa/conta/ambiente e identificador existentes são imutáveis no servidor e bloqueados na apresentação. Revisões são transportadas em campos ocultos. Cada formulário salva separadamente e redireciona para uma lista nova; reabrir a conexão obtém a revisão atual. Salvar a conexão revoga a delegação local e torna obsoleto o consentimento externo. Consentimento externo de revisão antiga não aparece marcado como habilitado no modal.

A configuração continua administrativa no contexto primário e lista empresas autorizadas por settings.edit. Não muda a fronteira de rotas nem abre módulos globais em empresas secundárias. Funcionário, moeda e conta financeira continuam campos de ID validados pelos serviços, não seletores novos; essa melhoria fica pendente.

## Segurança e efeitos

`POST /admin/pige_bridge/settings/disable` aceita connection_key, revision, CSRF e contexto nativos. Scope vem do registro autorizado, não do request. O writer existente revalida ACL e revisão sob lock e preserva token, origem e histórico. Um formulário obsoleto falha. Sem chamada bancária, emissão, baixa ou efeito acadêmico. JSON embutido usa escape HEX para impedir fechamento de script e expõe somente escopo, revisão, flags e mapping público; nenhuma cifra ou hash de credencial.

## Contrato bilateral confirmado

PIGE360-self main f47b8916b92231ac29b56862b133c9d2b2eec851 já possui GET/PUT `/api/v1/schools/{school_id}/crm-bridge/configuration` e POST `/connection-test` em backend/app/crm_bridge.py. Exigem JWT administrativo, vínculo escolar e integrations.manage. PUT requer base_url, issuer_id, account_id, environment, secret, enabled e version. O segredo HMAC da ponte não é um token Bearer administrativo.

O teste usa POST `/pige_bridge/api/v1/diagnostics` no CRM, com scope completo, nonce e HMAC temporal vinculado ao caminho. Não descobre IDs nem cadastra a outra ponta. A documentação antiga INTEGRACAO-CRM.md no PIGE não retrata todas as rotas atuais.

Autoconfiguração por URL/token não foi implementada. O menor próximo passo é um contrato de descoberta limitado a uma conexão, credencial específica, resposta mínima e transporte HTTPS com bloqueio SSRF/redirect. Nunca ampliar o diagnóstico existente para scope incompleto ou reutilizar segredo financeiro como sessão administrativa. Configuração/consentimentos financeiros devem continuar explícitos.

## Validação deste checkpoint

Executados: cinco suites Python (pige_bridge_settings_ui, configuration_static, external_static, invoice_static e bb_static), quatro checks cada; node --check modules/pige_bridge/assets/settings.js; git diff --check. São inventário estático e sintaxe JS, não execução PHP/HTTP/browser.

Ampliados testes PHP de guards da ação disable e tracking/upgrade até 107. PHP não está instalado neste executor; PHP/MySQL/browser não executados. CI deve executar testes administrativos, migration e isolamento antes de publicação final. Homologar: Novo→Cancelar→Editar outra conexão; token apagado ao fechar; guia financeira após criação; revisão obsoleta; rotação; desativação com permissões revogadas; duas empresas e perfil restrito; busca/paginação/responsividade.

## Versão e operação

Módulo 1.0.7, migration 107 NOOP; nenhuma DDL, backfill ou seed. Migrations anteriores intactas. Integração prevista na PR61 pelo integrador; versão core e gates agregados pertencem à composição final. Sem merge/deploy.

Após aprovação, atualizar pelo runner nativo e confirmar marcador 1.0.7. Backup consistente continua obrigatório. Em falha, suspender uso e corrigir/reaplicar, sem reduzir marcadores. Reverter apresentação não restaura consentimentos revogados nem reconstrói estados gravados depois; preservar histórico.



---

# Anexo H — Transcrições integrais sobre a ponte CRM–PIGE360

Rótulos de falante preservados. As falas comprovam os requisitos e esclarecimentos, não a implementação atual.

Fonte: chamada de 6/11/2026, 00:20:57 UTC.

Voice transcript (quoted speech, with speaker labels):
assistant:  Tô entendendo, peraí.
user: . Eu não quero views novas, eu quero o mesmo sistema da 4.0.2 porém aprimorado para né, no caso dessa versão aí que a gente tá tentando corrigir aí, salvar a a da main, né
user:  Pra multiempresa
assistant:  Isso... sem criar uma view nova que quebre o padrão. E esse sumiço do menu é um...
user:  Isso
assistant:  é um defeito a parte. Então deixa eu confirmar, você quer manter a navegação,
user:  Mas não, mas não acontece só com
assistant:  sim
user:  Mas, eh, eu quero
assistant:  Eu quero manter a navegação
user:  isso não acontece só com esse
assistant: , claro...
user:  Pode falar. Por exemplo, ó, eu vou, eu tô com o módulo
assistant:  Sim.
user:  eu eu tava com o módulo eu tinha clicado no Banco do Brasil, e do menu de definições sumiu o menu recursos e outros que eu não lembro agora. Aí, por exemplo, agora eu tô num com o menu definições aberto, beleza? Que é uma sidebar
assistant:  Uhum.
user:  sobre a a sidebar principal, beleza
assistant:  Sim.
user: ? Eu vou clicar fonte CRM
assistant:  Tô te ouvindo, pode seguir.
user:  Pronto Essa tela aqui ela já foge do padrão do do CRM, e automaticamente, ela ocultou um outro submenu que tinha aqui, integrações que eu até mandei uma imagem pra você, que tá com as anotações em vermelho, que é integrações, fingeponte, integrações, onde aparece Asaas, ó, ó, ó, ó, eu botei assim. Acesse essas
assistant:  Tô vendo,
user:  parametrizações e são localizadas em locais incorretos
assistant:  sim... Tô vendo esse print que você mandou com as an
user:  Pronto, se eu
assistant: otações. Realmente tem vários lugares em que tá diferente, né? Do que você queria também. Tem coisas que tão faltando, algumas parametrizações tão em lugares errados. Tô vendo.
user:  E, isso. Aí o que acontece, se eu clico em PIG, esse menuzinho integrações aí, onde tem Asaas, não sei o quê, ele some
assistant:  Uhum...
user: . E ele não é nem pra existir esse menu integrações, o PIG também não era pra existir aqui, ele era pra tá dentro de configurações. E eu entendo que esse menu aqui fugindo um pouco, né, mas ainda falando de organização, eh, o PIG ele, ele esse módulo aqui, ele não atende ao padrão do CRM visualmente falando, né
assistant:  Uhum.
user: ? Ele deveria ter uma grid onde eu pudesse ter várias integrações do PIG, nesse, nessa mesma empresa, por exemplo, eh, nesse nesse mesmo módulo, onde eu pudesse editar uma integração ou cancelar ela, né? Entendeu? E esse, e esse e esses parâmetros poderiam vir numa modal
assistant:  Claro.
user: , né
assistant:  Sim.
user: ? Seguindo o padrão, tudo do CRM, com guias, tudo certinho. Então, o PIG, ele não atende. E muita coisa quando Ele deveria gerar uma pré-configuração, certo? Ou ah, já já tendo a comunicação, algo pra facilitar, né, copiar o URL, envia no vai no CRM. Se já tiver um token, o CRM conecta aqui e já se pré-configura totalmente, se integra ao à ponte do PIG, entendeu
assistant:  Tô entendendo. Claro. E sim.
user:  Então, tem muita coisa aqui que deveria facilitar e tá muito manual

Fonte: chamada de 6/11/2026, 00:23:21 UTC.

Voice transcript (quoted speech, with speaker labels):
assistant:  Hum, ok. Deixa eu organizar isso
user:  Então, tem muita coisa aqui que deveria facilitar e tá muito manual
user:  Aí
assistant: , peraí. Tô separando os pontos. Já te falo.
assistant:  Sim. No PIG, a gente pode manter uma lista de integrações por empresa, com edição e desativação, e configurações em modais com guias nativas do CRM. E aí, pra não ficar tão manual, quando você informa a URL e o token, o sistema testa a conexão e puxa o que conseguir da API pra preencher. Sem cópia-e-cola de parâmetros de um lado pro outro. Além disso, parece que a correção dos menus precisa olhar a navegação inteira, porque o problema não tá só no Banco do Brasil, né?
user:  Não, geral. Por exemplo, é, eh, deixa eu ver aqui. Uma coisa simples. Eu quero Eu tenho aí o menu Eu tenho aí um, um, um, um módulo chamado produtos
assistant:  Uhum.
user:  E eu não sei se ele Conecta. A fatura, eu posso
assistant:  Eu vou conferir direitinho
user:  lançar uma fatura e buscar um
assistant:  como tá hoje, peraí. Quer continuar falando do que você
user:  Eu acho que a fatura não
assistant:  precisa?
user:  Sim, sim, mas eu acho que esse produtos ele não tá pegando diretamente, a fatura não consegue capturar um produto, deixa eu ver aqui
assistant:  Achei uma limitação já.
user:  Sim
assistant:  Do PIGE. A API não
user:  Hm
assistant:  consegue preparar tudo só com URL e token, viu? Ela exige algumas informações já configuradas. A lista e o modal dá pra faze, mas pra automatizar dos dois lados vai precisar ampliar a integração entre o CRM e a ponte. E ainda tem produtos...
user:  Pode fazer
