# Navegação autenticada — evidência local de 6/11/2026

Base do código: PR63, `4ec09a4732442fddfea445b404b0279b1de7539f`.
Execução local descartável com PHP8.3.36, MySQL8.4 e Chromium instalado. Não é
homologação de produção nem substitui o CI da árvore exata. A cópia HTTP contém
instrumentação local para registrar somente códigos/localização de exceções.

## Percurso e resultado

Usados login e seletor originais de empresa. Em cada página, aberto o menu
Definições pelo controle original quando ainda fechado. Comparados os destinos
não vazios do menu principal e de Definições com o Painel do mesmo contexto.
Nenhum token, cookie ou conteúdo de cliente integra este documento.

| Contexto | Páginas, na ordem | Resultado |
|---|---|---|
| Principal | Banco do Brasil → Configurações/Meios de pagamento/BB → Pagamentos → Configurações/Empresa → PIGE → Painel | Todos HTTP200; nenhum destino autorizado do menu de referência perdido; Recursos presente |
| Secundário | Banco do Brasil → Configurações/Meios de pagamento/BB → Pagamentos → Configurações/Empresa → Painel | Todos HTTP200; nenhum destino autorizado do menu de referência perdido |

Referências locais:70 âncoras no contexto principal e62 no secundário. Esses
números descrevem a instalação sintética, não um limite esperado para clientes.
A comparação inicial de todas as âncoras/textos foi diferente: a página legada
do Painel principal e as rotas isoladas apresentam contadores e agrupamentos
adicionais distintos. Isso continua sendo uma diferença de apresentação a
reconciliar; não houve perda dos destinos autorizados. O desaparecimento
relatado de Recursos não foi reproduzido neste percurso. Nenhuma causa técnica
ou correção desse relato é declarada sem reprodução.

## Dependência PIGE

O banco de diagnóstico inicialmente não tinha registro nem tabelas do PIGE;
acesso direto à rota devolveu503. Executada ativação pelo controle original de
Recursos, sem inserir marcador manual. O runner registrou1.1.1 e a página
passou a200. Consulta posterior: módulo ativo e zero conexões. Nenhuma
credencial ou consentimento financeiro criado, nenhuma chamada bilateral.

## Outras verificações autenticadas locais

- Orçamento: criação, PDF, abertura do menu e aceite POST200 pelo driver original.
- CNAB: espécie01 recusada422 sem fatura nem número consumido; espécie02 emite
  pelo formulário original e o replay não duplica. Provedores não chamados.
- Asaas: abrir Transações, confirmar remoção de origem já excluída por transporte
  CLI simulado e repetir via controller; HTTP200 e ação removida da apresentação.
- Tarefa: abertura, inicialização de checklist, inclusão e reabertura passaram
  com detalhe HTTP200. O executor exigiu diagnóstico local com prazo maior;
  timeouts e verificações do CI não foram alterados. A falha anterior do CI
  permanece sem causa reproduzida neste ambiente.
- Contrato: inclusão de tipo por modal depois da seleção de cliente/projeto e
  depois de trocar empresa passou com POST200. Isso não encerra a falha anterior
  da suíte completa.

CI anterior75fb7ac4 falhou em tarefas recorrentes e cadastros inline de contratos
/despesas. A rodada4ec09a47 ainda executava quando esta evidência foi escrita.
Resultados locais acima não são declaração de aprovação desses grupos.
