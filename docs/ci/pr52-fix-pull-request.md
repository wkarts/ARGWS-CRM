# Complemento da PR #52

**Branch:** `fix/native-company-experience`.

**Título:** `fix(ci): corrigir falhas reais do smoke e reduzir preparação e validações repetidas`

A PR ficava mais de uma hora validando e parava em erros sucessivos, deixando cenários posteriores sem diagnóstico. Esta alteração corrige a divergência da numeração legada e a expectativa incorreta de formato PDF; distribui os 101 comandos de navegador em oito bancos isolados; reutiliza ferramentas por digest/cache; e corrige as falhas adicionais reveladas pelos oito grupos. O gate agregado exige todos os grupos e as matrizes de domínio continuam obrigatórias.

## Alterações e motivos

- Criação/edição de fatura legada usam o mesmo escopo de duplicidade do validador remoto. O driver comprova um ID novo do cliente/número exatos, sem repetir o POST legado nem aceitar uma linha antiga. Retirados waits fixos e fallbacks longos.
- PDF legado exige a opção global atual; PDF nativo exige seu snapshot imutável. Dimensões continuam conferidas nos bytes reais, sem alargar tolerâncias.
- Rotas exatas da API do núcleo têm precedência sobre o wildcard legado, com formato de argumentos limitado e autenticação preservada.
- Asaas carrega a criptografia no contexto isolado. O formulário global de gateways permanece administrativo e exclusivo da principal. Google/Microsoft, chamados e os formulários nativos delimitados compartilham a apresentação autorizada da principal; callbacks negados removem protocolo da URL por 303 sem trocar códigos.
- Restaurado o link das regras de publicação de projetos. O leitor do portal respeita atribuições nativas ativas, gates de equipe e ACL, incluindo remoções. O tab da equipe principal conserva o renderer legado.
- Edição de faturas preserva o algoritmo registrado na emissão e os impostos históricos da própria linha; novos impostos continuam sujeitos ao catálogo. A criação legada da principal e as linhas `issuer_id IS NULL` preservam seu caminho anterior. Confirmações de Settings usam mensagem vinculada a empresa, ator e grupo.
- Arquivos de chamados resolvem o status nativo pelo catálogo/projeção existente; fechado continua sem permitir upload. Rotas de revisão pós-devolução rejeitam sufixos desconhecidos.
- Banco do Brasil corrige o fechamento excedente da view e a recusa isolada que chamava helper global. Versão 1.3.6 e nova migration nativa 136 NOOP.
- Fixtures usam `require_once` para dependências transitivas. Navegadores usam os controles, campos CSRF, previews, PDFs e IDs tipados/raw atuais, preservando verificações financeiras e de segurança.

## CI, GHCR e desempenho

A imagem do aplicativo é construída uma vez, exportada como artefato e conferida pela árvore Git em cada worker. Cada grupo provisiona seu banco; não há compartilhamento de snapshots nem paralelismo de testes no mesmo banco. Rust/Compose executam em paralelo. Configurações não repetem sete navegadores que criavam fixtures desnecessários; publicação/escrita de tarefas tem um único grupo dono. Todos os 101 comandos mantêm cobertura e ordem por grupo, com verificação de conclusão no shell.

Os 60 comandos SQL MySQL e os 72 MariaDB do Banco do Brasil são distribuídos em quatro bancos isolados por variante, com PHP/extensões e serviços originais. O verificador da cadeia e seu teste de checkout permanecem no mesmo worker e na mesma ordem. Os checks obrigatórios `mysql` e `mariadb` agregam os quatro comprovantes do commit atual e reprovam cobertura incompleta, falha ou cancelamento.

Sete workflows adicionais reutilizam as bases GHCR PHP 8.1/8.5 existentes por digest. Bases só são reconstruídas quando os inputs mudam ou há atualização manual explícita; o smoke das ferramentas sempre executa. Caches npm/Chromium/Rust/Buildx e compilação paralela das mesmas extensões reduzem preparação. Removido apenas o job `registry-plan`, que imprimia mensagem sem executar teste; seu preflight e oito shards permanecem.

O monitor mantém os grupos de diagnóstico do commit atual após a primeira falha e cancela trabalho obsoleto. Stack traces compactos ficam acessíveis em anotações; logs e tempos por teste permanecem nos artefatos. Ganho global só pode ser declarado após aprovação integral do commit final: a rodada de diagnóstico ainda falhou.

## Banco, contratos e compatibilidade

Sem DDL ou migração de dados nesta correção. A migration 136 é NOOP e preserva todas as anteriores. Núcleo 4.8.4 e passos 474–484 já existentes nesta mesma PR são mantidos em relação à main 473. Sem dependências da aplicação, secrets ou endpoints externos novos. Contexto, CSRF, permissões, retries, snapshots e isolamento A/B/principal continuam verificados. Sem operação bancária/SMTP real ou homologação financeira.

## Validação

- 74 testes Python dos contratos de CI e 19 testes Node direcionados aprovados; manifest confere 101 invocações e oito grupos.
- Novos contratos rápidos: 12 cenários de numeração, quatro de formato PDF, 47 de roteamento API, 45 de seleção do leitor por origem persistida e 21 de callback negado.
- MySQL real: 110 checks do leitor do portal, 133 de arquivos, 250 de subrecursos operacionais, 228 de tracking bancário até 136.
- Chromium/MySQL direcionados aprovados: relatórios/API; Google/portal/política de arquivos; publicação e escrita de tarefas; arquivos operacionais inclusive rejeição de upload em chamado fechado; vendas e conversão com PDF/impostos; Asaas, ciclo pós-devolução A/B, IA A/B/principal e origem/componentes/reversão bancários.
- Bases GHCR reais PHP 8.1/8.5: Chromium, numeração, 4.023 checks de recorrências, 33 de idioma, 88 do painel e quatro testes de persistência passaram em ambas.
- Quatro inventários gerados são reconciliados e conferidos em conjunto antes do push, preservando a classificação de prontidão operacional.

Execuções direcionadas não equivalem ao smoke completo. O grupo de provedores concluiu os 18 comandos em instalação limpa, com as provas TLS originais. Os 14 alvos bancários passaram em execuções direcionadas. Marcos, horas, Gantt, arquivos do portal e preferências de suporte passaram em execuções completas dos respectivos navegadores. Faturamento de tarefas comprovou edição histórica para 55,11 e cancelamento, mas a execução direcionada foi interrompida no helper após a navegação; a leitura da resposta foi antecipada. O cenário completo de faturamento e o CI do commit final permanecem obrigatórios. Evidências e limites em [pr52-validation.md](pr52-validation.md); arquitetura e reprodução em [standalone-browser-groups.md](standalone-browser-groups.md).

## Auditoria histórica

A coleta diária encontrou **7.506 registros de workflows**, de 25/10/2026 19:43:59 UTC até 02/11/2026 07:19 UTC. São registros de workflows, não testes ou minutos faturados. PR aberta em 25/10 às 21:02:18 UTC. Fontes e contagem por dia em [pr52-validation-audit.json](pr52-validation-audit.json).

## Riscos, rollback e entrega

Filas, cache frio e capacidade dos runners variam; não há promessa de duração fixa. Cada banco independente tem custo comum explícito. Reverter estes commits desfaz a correção; caches são descartáveis e a migration 136 não modifica dados. Atualização apenas da PR existente; merge, release e implantação permanecem fora desta entrega.

- [x] Causas reproduzidas; cobertura e matrizes preservadas.
- [x] Histórico de execuções e limites documentados.
- [ ] CI completo do commit final aprovado.
- [ ] Ganho global medido em rodada aprovada.

Pusher: a origem privada resolve a atribuição nativa explícita de um único responsável, preservando o marcador armazenado e as atribuições legadas. Remoção, ambiguidade e troca de época vetam o envio; novos casos PDO exercitam esses limites. Carregamentos de classes em fixtures/bootstraps HTTP são idempotentes, sem excluir cenários ou assertivas.

Regressões novas: formulário e writer completos com 236 checks SQLite e 232 MySQL; contexto/bootstrap com 132; Pusher com 35 adicionais em cada banco sobre os 78 básicos; portal com 110 checks MySQL. O teste de impostos cobre preservação, retry, falsificação de nome/taxa/linha, nova linha sem autorização de catálogo, empresa estrangeira, algoritmo forjado e proteção do legado NULL.

Correção do contrato de sessão dos quatro fixtures de Settings: dependência explícita, negação sem flash e mensagem vinculada a empresa/ator/grupo. Os 14 entrypoints MySQL focados passaram localmente; a fase remota será repetida no novo commit. Falhas dessa suíte agora produzem anotação pública sanitizada e limitada, preservando causa e final da pilha.
