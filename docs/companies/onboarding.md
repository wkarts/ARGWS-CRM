# Responsabilidade da instalação e cadastro de empresas

Incremento sobre o HEAD `44cd7c3815123883d5c3168ae045a75a3347b236` da PR45.
Núcleo 4.0.5 / migration 405. As migrations 403/404, BB 101–104 e Asaas até 148
permanecem preservadas. Nenhum merge, tag de release ou deploy é parte deste fluxo.

## Instalação nova e legado

O instalador mantém a prova do ID efetivamente criado e a fonte `installer`.
No legado sem prova histórica, nenhum ID, administrador global ou parâmetro HTTP
é promovido automaticamente. O procedimento novo é uma **designação autorizada**,
registrada como `legacy_designation`, sem afirmar recuperação do administrador original.

1. O responsável autorizado pela hospedagem verifica a decisão e o ID do administrador
   legado ativo que deve assumir a responsabilidade. Esta decisão não é inferida pelo CRM.
2. No console da instalação, executa `php index.php argws_provisioning authorize_company_master ID`.
   Informa o motivo autorizado, sem senhas, credenciais ou dados de clientes.
3. O comando retorna uma autorização de 256 bits, vinculada ao ID, válida por 15 minutos.
   Só o hash é persistido. Reemitir revoga as autorizações pendentes anteriores.
4. O próprio colaborador abre Configurações → Empresas → Responsável da instalação
   (`admin/company_master`) e confirma o código com sua senha atual.
5. Cinco senhas incorretas bloqueiam aquela autorização. A contagem é persistida;
   falha de armazenamento não é apresentada como sucesso.

O console só autoriza administrador ativo com acesso administrativo legado explícito
à empresa principal. A confirmação repete essas verificações sob lock e verifica a
senha contra o hash atual. Identidade, consumo da autorização e auditoria são atômicos.
Repetição da confirmação concluída é idempotente; identidade já resolvida não pode ser
substituída por esse procedimento. Desativação ou remoção do papel administrativo
revoga os poderes do responsável imediatamente, sem cache de privilégios.

A interface de colaboradores exige empresa autorizada e padrão antes de criar um
novo colaborador. Perfil e concessões continuam unidades separadas no editor legado:
se a gravação de concessões falhar depois do perfil, a mensagem explícita de falha
é preservada e o formulário permite repetir. Isso não é uma promessa de transação
única para todo o cadastro legado de colaboradores.

## Wizard nativo

O botão Nova empresa abre `admin/company_onboarding`, disponível somente ao responsável
ativo. O renderer isolado admite apenas essa rota; não abre outros domínios legados.

- Identidade fiscal e endereço usam o mesmo escritor canônico de Informações da empresa.
- Preferências de data/hora são próprias. SMTP/integrações começam sem segredos herdados.
- Colaboradores recebem acesso e recursos selecionados; concessões anteriores são
  preservadas. A troca de padrão é explícita e exige associação autorizada.
- Clientes permanecem privados. O compartilhamento não é ativado antes de concluir
  seus consumidores e as validações do domínio.
- Revisão precede a criação. Empresa, preferências, concessões, padrão, templates
  próprios, auditoria e conclusão do rascunho compartilham a mesma transação.

Os rascunhos são persistidos por responsável. Há um rascunho pendente por responsável,
serializado pelo lock nativo. Revisões impedem sobrescrita por abas concorrentes
(HTTP 409). Reenvio da conclusão retorna a mesma empresa. A empresa principal não é
recriada. Troca de empresa mantém o token capturado pela aba e exige recarregar antes
de escrever, sem reaproveitar o POST em outra empresa.

A tela final distingue as capacidades disponíveis dos fluxos ainda bloqueados. O wizard
não habilita emissão/baixa financeira, compartilhamento de histórico, API bancária,
transmissão CNAB ou homologação. Administrador principal automático não contorna gates.

## Schema e operação

Migration 405 cria duas tabelas aditivas InnoDB: `issuers_master_authorizations`
(hash PK binária, colaborador, expiração, tentativas, consumo e motivo) e
`company_wizard_drafts` (chave PK binária, responsável, revisão, etapa, payload sem
segredos, empresa resultante e timestamps; índice de pendência e FK da empresa).
Não há backfill nem alteração dos registros financeiros legados. DDL exige locks
curtos de metadados, conforme o volume e as transações existentes no ambiente.
A migration recusa tipos/chaves/engine incompatíveis e é reexecutável. O downgrade
destrutivo é recusado. Para interromper, revogue acessos/pause o uso preservando
rastros; restauração de aplicação/banco exige o fluxo normal de manutenção e backup.

## Evidências e limites

- `tests/issuers_master_resolution.php`: dados sintéticos, senha, expiração,
  revogação por reemissão, retry, rate limit, auditoria/rollback, ativo/admin.
- `tests/issuers_onboarding.php`: retomada, revisão, validação, permissões,
  defaults, preservação de acessos e legado, falhas antecipadas/tardias e retry.
- `tests/issuers_onboarding_concurrency.php`: duas conexões MySQL observadas
  aguardando o lock no Performance Schema, confirmação idempotente e revogação
  do responsável enquanto os dois escritores aguardam.
- `tests/issuers_staff_native.php`: controller/helper/serviço reais com I/O de
  framework adaptada; criação sem empresa/padrão é recusada antes do writer.
- `tests/companies_onboarding_browser.cjs`: cenário HTTP/MySQL/Chromium real no
  fixture descartável de upgrade, sem acesso a hosts públicos ou clientes reais.
- CI de Empresas executa os serviços e migration 405 em MySQL 8 nas versões PHP 8.1/8.5.

Os testes locais usam PHP 8.3 e SQLite, que não comprovam InnoDB/locks/metadata MySQL.
O ambiente local recusou o socket Unix do MariaDB e a elevação de permissão. O cenário
MySQL/HTTP/navegador é uma obrigação de CI; consultar o resultado do SHA publicado.
Nenhuma evidência de homologação bancária é produzida por esses testes.
