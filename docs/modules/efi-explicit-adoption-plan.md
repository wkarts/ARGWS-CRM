# Efí: proposta de adoção explícita do tracking nativo

Estado: plano autorizado, implementação isolada em andamento. Não habilita o gateway,
não executa instalador legado e não comprova homologação bancária.

## Proveniência inspecionada

Base: `7c55d08a` (PR50 incorporada). O header literal é `5.1.4 sdk final`;
o recibo da instalação aponta `5.1.4 sdk f`. O módulo não tem migrations.
Blob Git do entrypoint: `ee78d02d25946371879686cc6e6e1892e0df96bf`.
Blob do instalador: `215b894e39e0b07c4e1a65ae338a25d9bbf599b1`.
Esses fatos não provam que uma migration 514 existiu ou foi aplicada.

O instalador legado apaga/copia controller e library para `application/`.
Esses destinos não existem na árvore inspecionada. O carregador nativo já aceita
library do módulo (`Payment_modes_model::add_payment_gateway`), mas as URLs do
controller usam `gateways/gerencia_net`. Remover a cópia exige contrato explícito
de roteamento e compatibilidade das URLs existentes, não um NOOP que finja instalar.

## Decisão autorizada e implementação

1. Nova release canônica 5.1.5, primeira etapa efetivamente implementada 515.
   Não gravar 5.1.4 intermediário, não criar migrations fictícias 100–514.
2. Ponte de adoção separada do runner genérico, somente para módulo Efí,
   origem literal aprovada e destino exato. Preservar origem integral no histórico
   de adoção, junto de plano/release, ator administrativo e estado verificável.
3. Ação administrativa POST + CSRF explícita, confirmação e lock por módulo;
   registro e marcador atômicos para operações DML. Falha de persistência não
   anuncia sucesso; replay verifica histórico e destino, sem duplicar efeitos.
4. Não iniciar hooks/SDK, não ler credenciais, não chamar provedor, não mudar
   `active`, configurações, faturas, pagamentos, certificados ou ACL.
5. Exceção de política por plano auditado e proveniência exata; manter rejeição
   de todos os demais headers não canônicos. Não interpretar truncamentos
   arbitrários como uma versão válida.
6. Disponibilidade operacional permanece separada da adoção de tracking.

## Bloqueios operacionais concretos

- `Gerencia_net::view_converter_certificado_p12_para_pem` e
  `converterCertificado` não apresentam verificação administrativa local.
- `callback_check_pix_pago($txid, $id_fatura)` aceita os dois identificadores e
  chama `fazerPagamento` após consulta ao provedor, sem verificação local visível
  da associação da cobrança à fatura/empresa ou hash da fatura.
- Preservar ACL exige tratar esses contratos antes de abrir novas rotas ou
  declarar disponibilidade segura. Esta inspeção não conclui auditoria completa.

## Evidência exigida para entrega funcional

Testes de origem exata, origem desconhecida/truncada diferente, target incorreto,
ativo/inativo preservado, falha no histórico, falha no marcador, rollback,
replay, concorrência, admin/CSRF e negativos de chamadas ao provedor/instalador.
MySQL real para transação/lock/tracking; PHP em CI. Runtime PHP/Docker não está
presente no executor local. Não há teste de ativação ou provedor executado.

## Checkpoint de implementação

- Serviço `EfiVersionAdoption`: duas labels exatas, transação InnoDB, histórico
  somente INSERT, CAS do marcador, confirmação persistida, replay que exige recibo.
- Núcleo473: tabela `module_version_adoptions` com schema/engine/índices conferidos.
- Etapa515 NOOP real, instalador sem cópia/unlink e política por SHA256 exato do
  entrypoint anterior. Nenhuma etapa514 artificial.
- Manutenção administrativa oferece adoção explícita, com label de origem
  confirmada sob lock, sem alterar o estado ativo. Loading/rotas/callbacks ainda
  são dependência obrigatória antes de publicar ou anunciar disponibilidade.
- `python3 tests/test_module_migration_diff.py`:44 testes PASS localmente.
- `php tests/companies/efi_adoption_mysql.php`: preparado para CI MySQL isolada;
  não executado localmente. Exercita DDL/replay, labels, estado ativo, outros
  módulos, falha real por triggers em INSERT/UPDATE, rollback e recibo conflitante.

## Dependência operacional em revisão

Core473 também prepara `efi_charge_bindings`, com identidade única de provedor,
modalidade e ambiente, invoice/issuer, valor em centavos, moeda e recibo local.
O preflight nega faturas de empresas nomeadas antes de hidratar gateways globais,
nega módulo inativo/não adotado e recusa controllers legados copiados. Aliases
finitos preservam URLs revisadas; Pix passa a exigir também hash da fatura.

O token histórico de boleto/cartão não guarda ambiente, e Pix não guarda vínculo
local. Portanto não se pode reconstruir com segurança um binding apenas pelos
identificadores recebidos no callback. Tratamento de cobranças históricas sem
binding está sob decisão explícita; não se promete conciliação automática delas.
Os testes de preflight preparados são doubles de SQL/roteador, não substituem
prova HTTP real ou SDK fake integrado.

## Política operacional aprovada

Cobranças novas terão vínculo persistido incluindo aplicação (fingerprint do
client_id, sem segredo), ambiente, modalidade, identidade do provedor e fatura.
TODAS as cobranças históricas sem vínculo comprovado ficam para revisão manual,
inclusive boleto/cartão: o token antigo não prova a aplicação nem o ambiente.
Não haverá preenchimento automático de vínculos no callback. Eventual migração
legada futura exigirá ação individual auditável antes da conciliação.

Desinstalação não apaga histórico, certificados cifrados, opções nem arquivos
copiados no núcleo pelo instalador antigo. A remoção dos arquivos próprios do
módulo continua pertencendo ao framework. O preflight recusa execução do
controller copiado, em vez de apagar artefatos da instalação silenciosamente.
