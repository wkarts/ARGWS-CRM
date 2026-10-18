# Empresas no núcleo: checkpoint de integração

Empresas deixa de ser um módulo operacional. O catálogo e o loader excluem
`issuers` explicitamente, inclusive se uma instalação antiga ainda tiver seu
marcador `active=1`. Os arquivos históricos de `modules/issuers`, especialmente
as migrations 101 e 102, permanecem byte a byte para rastreabilidade; não são
executados pela interface de módulos. O histórico Git e os dados existentes não
são apagados. A reconciliação nativa desativa apenas esse marcador antigo.

## Contratos para os consumidores

- `issuer_core_ready(): bool`: apenas leitura. Inclusão do helper não consulta o
  banco, executa DDL ou registra callbacks de módulo.
- `issuer_resource_can($issuerId, $staffId, $feature, $capability): bool`: decisão
  contextual completa. Não combinar com um veto/permissão global `staff_can`.
  Uma permissão global não concede acesso contextual; uma negativa global não
  revoga uma concessão contextual válida.
- `load->model('issuers_model')`, `available($staffId)` e `get($issuerId)` consultam
  a identidade das empresas autorizadas. `get` usa o ator autenticado. Não usar
  o estado do antigo módulo como dependência.
- `IssuerCore::reconcile($db, $prefix)` pertence exclusivamente à instalação ou
  atualização nativa controlada. `ready` não corrige schema em GET/login.
- `IssuerCore::bindInstallationMaster($db, $prefix, $staffId)` recebe evidência
  interna do instalador, nunca ID de formulário/opção/configuração funcional.

O acesso de Colaboradores é editado no cadastro nativo: vínculo de empresa,
empresa padrão válida e capacidades nativas por recurso. Novos colaboradores
não herdam empresas ou permissões. A identidade comprovada de instalação tem
acesso às empresas e recursos atuais/futuros enquanto o usuário está ativo e
administrador. Administradores comuns não recebem esse privilégio implicitamente.
Uma instalação legada ambígua mantém `master_staff_id=NULL`; não inferimos ID 1.
Ainda é necessária a resolução assistida e auditada dessa identidade legada.

As flags genéricas antigas (`can_manage_clients`, `can_share_clients`,
`can_manage_integrations`) são preservadas apenas como histórico. Não concedem
permissões no núcleo. O getter compatível `canAccess` autoriza somente a leitura
da identidade por vínculo; operações usam o contrato contextual por recurso.

## Principal legada

A reconciliação preserva um ID principal estável. Nome e CPF/CNPJ válidos do
legado podem preencher a identidade. Se estiverem ausentes ou malformados, a
principal recebe estado explícito `pending`, com tax_id nullable somente nesse
bootstrap. O conteúdo bruto original de nome/VAT fica em snapshot interno, sem
credenciais de SMTP/bancos. Não há alteração de faturas, clientes ou opções
legadas, nem inferência de origem bancária. Empresas novas exigem identidade
válida; preencher a pendência usa o cadastro normal. A principal não pode ser
inativada. Pendência não bloqueia indiscriminadamente a emissão legada existente.

## Limites deste checkpoint

Este checkpoint não é uma liberação de operação financeira multiempresa. O gate
financeiro permanece fechado. A seleção contextual e as configurações de identidade/e-mail já têm composição
local. Os demais adaptadores de módulos, relatórios, arquivos, portal e jobs
ainda precisam ser completados e validados. As antigas ações paralelas de adoção/compartilhamento de
clientes estão fechadas; sua implementação privada permanece em integração
separada. Vínculo de empresa não concede contatos/login/anexos/finanças por uma
relação global de cliente.

A composição local usa migration core 404 e versão 4.0.4, após a base 403 da PR45.
Essa numeração ainda exige revalidação da fila antes de publicação. Não publicar este checkpoint como módulo 1.0.3, nem reutilizar
migrations core já presentes na base. Os testes locais SQLite exercitam os
serviços/controle, mas não demonstram ALTER MySQL, locks de duas conexões nem a
instalação HTTP completa. CI MySQL e Docker são requisitos de integração.

## Inventário executável

`python scripts/inventory-company-boundaries.py` enumera fontes em application,
modules, assets/js, docker e install, incluindo dependências como fronteiras
explícitas. Cada arquivo tem hash e referências de linhas/categorias, sem copiar
conteúdo de configurações para o manifesto. `--check` detecta manifesto obsoleto.
As revisões em `boundary-reviews.json` exigem hash atual, justificativa e testes;
qualquer mudança invalida a revisão anterior. `--require-ready` falha enquanto
houver fronteiras pendentes. Encontrar palavras-chave não demonstra isolamento.
O manifesto inicial não possui revisões aprovadas e permanece não operacional.

### Compatibilidade administrativa da principal

A migração registra uma concessão restrita `core_legacy_administration/access` para administradores ativos já existentes na empresa principal. Ela mantém os controles antigos que ainda consultam `is_admin()` durante a transição. Não identifica o master e não concede administração em outra empresa, mesmo que o colaborador receba um vínculo nela. O papel administrativo atual, o vínculo e a concessão registrada são verificados sem cache; revogar qualquer condição encerra esse acesso. Novos colaboradores não recebem essa concessão por terem o flag global de administrador. A adaptação dos controles antigos para capacidades específicas continua necessária antes de habilitá-los em outras empresas.

### Inventário verificável

`docs/companies/boundary-inventory.json` registra totais e um fingerprint de todos os arquivos/revisões. A matriz completa por arquivo, hash e linhas é produzida pelo comando `python scripts/inventory-company-boundaries.py --full --output /tmp/company-boundaries-full.json` e publicada como artefato de CI `company-boundaries-full`. Mantemos a matriz de 13 mil arquivos fora do diff de código para facilitar revisão, sem omitir dependências. O inventário não é prova de isolamento: `operational_ready=false` permanece enquanto há fronteiras não revisadas. O job de inventário valida consistência; não libera recursos automaticamente.
