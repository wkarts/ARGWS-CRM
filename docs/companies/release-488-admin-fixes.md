# Correções administrativas 4.8.8 / Asaas 1.6.1

## PR55 e validação

O head `0dd35eec15127235d29cea839d7945da51ba53d0` e o merge
`1e5a4a1e64ef230f3f996ebe871dc3c20943829d` têm a mesma árvore
`4f929dc91ff16089faaafb6fb914ae3912cfd8cb`.
No run 41955855966, job 127013777097, `timers-b` expirou em `page.goto`
aguardando `load` da página de tarefas do projeto. O run 42019161022 do merge
executou todos os grupos HTTP novamente e passou. Isso é evidência de
intermitência; o sucesso do merge não converte retroativamente o run da PR em verde.

O artefato da falha mostra requisições de imagens/fontes esperando no PHP CLI
single-process. O CI usa agora quatro workers no servidor descartável; a produção
continua no seu servidor normal. O helper aguarda DOMContentLoaded, HTTP 200,
inicializador da tarefa e botão visível. Não há retry nem remoção de asserções.
O teste Chromium mantém uma imagem pendente e comprova que a ação funciona;
um documento 503 continua falhando. Também foi corrigida a consulta do nome do
estado das tarefas do projeto: estado de domínio não é ID canônico de status.

## Empresas

Administrador global ativo pode iniciar, retomar e concluir o cadastro, mesmo sem
identidade original atestada. O criador recebe membership e edição de definições
na nova empresa; no assistente, recebe o catálogo de permissões da empresa criada.
As concessões escolhidas para colaboradores, configurações e conclusão do rascunho
são confirmadas na mesma transação. Falha tardia desfaz a criação. Demissão do papel
administrativo ou desativação revoga o acesso ao assistente.

Não existe tratamento especial por número de usuário. A conta ID 1 funciona pelo
seu papel ativo. Nenhuma empresa existente ganha permissões por esse mecanismo,
e `master_staff_id` e sua proveniência são preservados. A manutenção excepcional
continua disponível para identidade original; deixou de ser requisito de criação.

## Certificados

PFX/P12 com algoritmos antigos são lidos automaticamente por subprocesso OpenSSL
isolado, somente após a leitura moderna identificar algoritmo indisponível.
`ARGWS_CERTIFICATE_LEGACY_IMPORT=0` permite desativar explicitamente. O provider
legado não é carregado no PHP/TLS. Mantidos prlimit, deadline, pipes privados,
validação de senha/MAC/chave/CNPJ/validade e preservação do certificado anterior.

Dependências: OpenSSL CLI com legacy provider, prlimit/util-linux e proc_open
permitido. A imagem distribuída já instala openssl/util-linux. Se a hospedagem
bloquear esses recursos, a importação compatível falha de forma explícita;
não há execução sem limites nem upload de certificado a serviço externo.

## Asaas e módulo de referência 1.4.8

A adaptação usa os contratos de webhook do módulo anexado no módulo multempresa
existente, avançando 1.6.0 → 1.6.1. Não substitui o SDK, as origens financeiras,
as credenciais ou o writer de pagamentos pelo módulo independente.

- URL por recebedor/conta/ambiente, com subdiretório e index.php configurados.
- Token local cifrado, recuperação/revelação/cópia autorizadas; nunca chave API
  como token nem segredo em URL ou histórico.
- Consultar, criar, atualizar, excluir e retomar fila por ações POST explícitas.
- GET `/v3/webhooks` paginado e seleção pela URL exata; nenhum webhook de terceiro
  é escolhido por posição. Destinos duplicados exigem revisão.
- POST/PUT enviam API v3, SEQUENTIALLY, nome, email, eventos escolhidos, habilitação
  e token local. Atualizar não retoma fila interrompida implicitamente.
- Exclusão afeta somente o webhook remoto encontrado; mantém recebedor local,
  eventos, cobranças e comprovantes. A retomada usa PUT `interrupted=false`.
- Conta/carteira verificada antes do envio, revisão e permissões revalidadas.
  O ambiente do recebedor prevalece sobre o seletor global.
- CSRF específico consumido antes do envio. Não há retry de mutação. Uma resposta
  incerta é registrada e exige consulta antes de nova ação; SQL não desfaz HTTP.
- Histórico de início/confirmação/incerteza mostra usuário e data, sem payloads,
  token, credenciais, corpo de resposta ou mensagens livres do provedor.
- O cartão Asaas acima das abas de meios de pagamento foi removido. A aba Asaas
  e os controles de webhook permanecem nos contextos próprios.

Os fluxos financeiros existentes de emissão, consulta, exclusão por origem,
conciliação e reembolso conservam suas provas/ACLs. A política de receber em dinheiro
ou compensar operações financeiras do módulo independente não foi transplantada:
esses caminhos exigem o writer canônico do CRM e não podem ser sobrepostos por hooks.

## Validação e implantação

Testes locais: PHP 8.3, PDO SQLite, OpenSSL 3, Chromium/Playwright 1.55.1 e build
oficial `npm run build`. As chamadas Asaas são simuladas; nenhum dinheiro real ou
credencial de produção foi usado. As matrizes MySQL/HTTP e versões PHP do projeto
precisam terminar no GitHub Actions do commit final antes do merge.

Núcleo 488 e módulo 161 possuem migrations nativas NOOP, sem DDL ou limpeza de dados.
Após aprovação, usar o processo normal de atualização e conferir os dois marcadores.
Teste a configuração de webhook na conta sandbox autorizada antes de produção.
Reverter código não desfaz empresas criadas, grants registrados nem configuração
remota modificada explicitamente. Preserve backup e histórico; não edite migrations
antigas nem remova permissões/dados para simular downgrade.

Referências oficiais consultadas:
- https://docs.asaas.com/reference/create-new-webhook
- https://docs.asaas.com/reference/update-existing-webhook
- https://docs.asaas.com/docs/about-webhooks
- https://www.php.net/manual/en/features.commandline.webserver.php
