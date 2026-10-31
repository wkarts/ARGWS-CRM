# Efí por empresa: emissão e aplicação explícitas

Estado: implementação isolada, sem execução PHP/MySQL local e sem homologação.
O controller, schema, configuração e fences reversos são entregues na composição.
O legado `issuer_id IS NULL` permanece fechado às empresas nomeadas.

## Fronteiras

- Contas cifradas são selecionadas por empresa/conta/revisão; nenhuma opção global.
- Reserva única por fatura, commit confirmado antes de cada POST. Boleto possui
  dois fences porque createCharge e definePayMethod são duas mutações remotas.
- Resultado desconhecido ou etapa intermediária exige revisão; não há retry de POST,
  troca automática de modalidade, nova conta nem renovação silenciosa.
- Consulta/instrumentos revalidam ACL, conta habilitada, revisão, proprietário e
  snapshot depois da rede. SDK não participa da transação financeira.
- O resultado conhecido após revogação é retido em quarentena por tentativa congelada,
  sem usar a permissão revogada para baixar pagamento ou enviar outra chamada.
- Prova canônica sanitizada e hash persistem antes da aplicação. A classe final
  `Efi\CompanyReceivedReceipt` entra na allowlist exata do escritor financeiro.
  O writer atualiza recibo, saldo, journal e vínculo Efí atomicamente. Replay mantém
  uma chave por empresa/conta/ambiente/modalidade/identificador remoto.
- Callback não cria autoridade financeira: a aplicação requer staff autorizado.
- A revogação concorrente posterior ao último commit de dispatch não pode desfazer
  um POST já enviado. O fence persiste; não prometemos atomicidade DB+provedor.
- Falha de certificado antes do envio pode deixar reserva pendente. Não há reset
  automático. SIGKILL pode deixar arquivo temporário privado0600 até limpeza do host.

## Evidência externa consultada em 2026-10-24

Fontes primárias:
- https://dev.efipay.com.br/docs/api-pix/cobrancas-imediatas/
- https://dev.efipay.com.br/docs/api-cobrancas/boleto/
- https://dev.efipay.com.br/docs/api-cobrancas/cartao/
- https://dev.efipay.com.br/docs/api-cobrancas/notificacoes/
- https://gerencianet.github.io/documentation/docs/Notificacoes/RecebendoNotificacoes/

Pix: consulta por txid, valor original e recebimento integral único; data `horario`,
`endToEndId` válido (persistimos SHA256, não o identificador bruto), ausência de
`devolucoes`. Um valor parcial, múltiplos recebimentos, devolução, identificador ou
valor divergente nunca autoriza aplicação. A data não é fabricada a partir do relógio.

Boleto: emissão usa valores inteiros em centavos; instrumento vem do SDK autenticado.
Somente link HTTPS no host documentado `visualizacao.gerencianet.com.br`, caminho
`/emissao/`, é exposto. Pix expõe texto copia-cola limitado, nunca HTML/SVG/base64 remoto.

A documentação de listagem `/v1/charges` mostra `payment.paid_at` e `paid_value`,
mas não prova esses campos/unidades em `detailCharge`. Não deslocamos esse contrato
por suposição. O SDK bundled5.1.4 possui `getNotification` mas não listCharges/getCharges.
A documentação de notificações oferece alternativa concreta: consulta autenticada
GET `/v1/notification/:token`, `identifiers.charge_id`, estado `paid`,
`received_by_bank_at` e `value` confirmado. O texto consultado não explicita unidade
monetária de `value`; exemplos numéricos não bastam para inventar conversão.
A data `created_at` é mudança de status, não pagamento. `history.message` aceita
mensagens customizadas e não é prova financeira.

Portanto o adaptador atual aplica apenas Pix comprovado; boleto emite e consulta, mas permanece em revisão financeira. O serviço de
cartão possui contrato interno de emissão/consulta testável com transporte falso;
a GUI o mantém indisponível enquanto não houver tokenização própria comprovada. Cartão `paid` não é, por si só,
prova de liquidação do recebível. Próximo passo é confirmar esquema/unidade e
recebível nas fontes oficiais, ligar token apenas como hint a uma consulta scoped e
adicionar fixtures autênticas documentadas; jamais aceitar payload de callback como
prova ou chamar `settleCharge` para fabricar recebimento. Nenhum teste sintético
abaixo é apresentado como homologação bancária.

## Validação

- `python3 tests/test_efi_company_checkout_contract.py`: apenas contrato estático.
- `php tests/efi/native_proof_contract.php`: projeções sintéticas, sem rede.
- `ISSUERS_TEST_DSN=mysql:... php tests/companies/efi_company_checkout.php`:
  MySQL descartável, serviços reais, escritor/journal reais, provider fake contado;
  A/B, legado preservado, replay, rollback, revogação durante POST/GET com retenção técnica em quarentena,
  resultado incerto, transação externa e três modalidades de emissão sintética.
  Dois subprocessos com conexões próprias disputam o lock real de `issuers_lock`:
  a espera é observada via `essentialLockWaiters`, e somente um POST fake é contado.
  Triggers MySQL abortam INSERT de pagamento nativo, recibo Efí e prova; rollback
  e replay não duplicam pagamento/journal. Estes contratos aguardam execução CI.

O teste MySQL registra cleanup das quatro tabelas novas de prefixo aleatório.
PHP e MySQL não estavam disponíveis no executor local; execução é responsabilidade
concreta da CI da composição antes de qualquer alegação de conclusão operacional.
