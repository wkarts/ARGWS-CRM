# Banco do Brasil como meio de pagamento nativo (1.2.4)

Registro App_gateway no catálogo nativo, inativo e não selecionado por padrão.
Credenciais, conta, convênio e ambiente continuam nos perfis por empresa existentes;
não há configuração global duplicada nem ativação automática.

A emissão permanece na operação API administrativa protegida e idempotente. O novo
checkout **não emite cobrança por GET nem registra pagamento**. Um responsável com
`manage`, `view`, `api_query` e acesso à fatura revisa e publica explicitamente o
instrumento já confirmado. O portal exige identidade privada, permissão nativa1,
cliente proprietário, empresa, fatura, origem e revisão coincidentes. Revogação do
contato ou publicador, alteração financeira, cancelamento e revisão divergente
fecham o acesso. Homologação é identificada e não representa pagamento real.

Faturas legadas sem empresa não são redirecionadas para a empresa da sessão. O
checkout global rejeita esse caminho; somente o portal autorizado da empresa
expõe o instrumento. CNAB sem comprovante de registro API não é apresentado como
boleto pagável confirmado.

Migration124 cria apenas `bb_checkout_publications`; replay valida esquema e
preserva histórico. Nenhum banco externo, segredo real, ativação ou baixa foi
executado. Testes PHP/MySQL e navegador ainda não executados neste ambiente.

## Validação implementada

- `python3 tests/test_bb_native_gateway.py` (contratos estáticos).
- `php tests/banco_brasil_checkout_mysql.php` (banco real descartável e provedor
  simulado; publicação/replay, stale, empresa estrangeira, contato revogado,
  publicador revogado, ocultação de instrumento inválido e zero HTTP/baixa).
- Histórico de upgrades101–123 preservado; acrescentado124 com falha de tracking,
  retry e replay no fixture nativo existente.

## Limites

Não há homologação bancária nesta entrega. Títulos alterados após emissão exigem
nova revisão de instrumento; não reutilizamos linha digitável potencialmente
obsoleta. A publicação é uma permissão de apresentação, não confirmação de crédito.

## Revisão e limites comprováveis

O catálogo administrador permanece nativo. A projeção do gateway para o checkout
legado é sempre indisponível (mesmo que um switch global antigo esteja salvo como
ativo); nenhum switch salvo é apagado. POST legado BB retorna aviso antes de ler
fatura ou criar tentativa. A publicação por empresa é a autorização operacional,
não esse switch global.

A leitura revalida cliente e moeda da fatura, valor, vencimento, estado, ausência
de pagamento/crédito, todos os estados/observações bancários conhecidos e código
completo (dígito, campo livre, fator de vencimento e linha). Uma observação parcial
ou adversa passada não é apagada por consulta normal posterior. A autoridade do
portal deve compartilhar conexão e prefixo exatos. O controlador revalida a
projeção novamente antes de renderizar. Ocultar continua disponível sem precisar
abrir um instrumento já inválido; repetição não duplica auditoria. O token de
revisão autoriza publicação, não impede revogação administrativa fail-closed.

- `tests/banco_brasil_checkout_legacy_guard.php`: executa limites reais do modelo
  e métodos/CSRF dos dois métodos administrativos, sem colaborador de banco.
- `tests/banco_brasil_checkout_browser.cjs`: 18 renders das views PHP reais, dados
  sintéticos, 1280/768/390px, ativos nativos, forms/CSRF, revisão, ocultação,
  linha readonly, isolamento visual e ausência de overflow/requisições externas.
  Não equivale a sessão CRM autenticada nem comprova ACL backend; essa parte é
  exercitada pelo MySQL descartável separado. Capturas só serão produzidas em CI.
- Workflow BB conserva suítes existentes, inclui novos testes em MySQL/MariaDB e
  um job curto de apresentação; valida novamente push de main, sem reutilizar
  resultados de contexto PR.

Resultado local: 5 contratos Python e sintaxe Node aprovados; PHP, MySQL/MariaDB
 e navegador pendentes CI. Nenhum resultado bancário operacional é alegado.
