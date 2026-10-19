> Evolução 1.0.2: leia também [perfis CNAB 240/400 e migração 102](banco-brasil-cnab400.md). Novas importações agora exigem um perfil explicitamente registrado; históricos 101 não são reatribuídos.

# Banco do Brasil: cobrança e conciliação

## Estado desta entrega

Histórico405 (estado atual em [matriz](companies/pr45-status.md)): módulo `banco_brasil`, versão **1.0.4**, com migrations nativas **101–104** preservadas. A PR45 já incorporou a PR46; Empresas pertence ao núcleo, sem carregar o módulo histórico issuers. O núcleo avança para **4.0.5/405** com responsabilidade e wizard, descritos em [Empresas](companies/onboarding.md). A instalação usa `App_module_migration`; não há executor paralelo. Este é um recorte revisável do pedido de API de cobrança e CNAB 240, **não a conclusão de ambos os canais**.

Implementado e ligado à interface:

- Cadastro de vínculo imutável por emitente/conexão/conta/ambiente/convênio, sempre pendente de verificação bancária. Documento e nome vêm do cadastro autorizado do emitente; o formulário BB não pode substituí-los.
- Importação administrativa de retorno T/U restrito, validação integral antes de persistir, eventos observacionais, revisão visível, deduplicação e associação de cada arquivo com seus eventos/lote/sequência.
- Transação própria InnoDB, auditoria atômica e recusa de transação externa. Arquivo reempacotado preserva a proveniência sem duplicar o evento. Mesmo identificador semântico com dados financeiros divergentes exige revisão, revertendo a importação inteira.
- Guias Autenticação, Ambiente, Convênio, Conta, Cobrança, Remessa, Retorno e Webhook, em português.

Implementado apenas como codec puro exercitado por fixtures:

- Gerador de remessa P/Q, carteira 17 simples, convênio de sete posições, arquivo/lote 084/043, moeda Real. Não há endpoint operacional de geração/download/transmissão.

Ainda indisponível:

- Executor API, OAuth, cofre de credenciais, webhook BB, consulta canônica pela API, baixa financeira e conciliação automática.
- Orquestração de emissão de fatura, reserva de Nosso Número, ledger conectado ao core, lote operacional e download auditado. As tabelas `bb_origins` e `bb_batches` reservam o contrato, mas **não constituem funcionalidades conectadas**.
- Homologação bancária, outros convênios/carteiras/segmentos, parcelamento, alterações, protesto/negativação, juros/multa/desconto e pagamento parcial.

A importação salva observações e **não cria pagamento, não muda a fatura e não envia mensagens**. Arquivo válido estruturalmente não comprova que foi emitido pelo banco: conta e documento no arquivo podem ser forjados. A origem e a evidência bancária exigem conciliação assistida; um evento importado não autoriza uma baixa.

## Fontes primárias verificadas em 18/10/2026

1. [BB: aplicativos e leiautes de arquivos](https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/), que aponta para o [manual CNAB 240 P/Q/R/S/T/U/Y](https://www.bb.com.br/docs/pub/emp/empl/dwn/CNAB240SegPQRSTY.pdf), revisão **junho/2026**, 42 páginas. SHA-256: `cbe4d93359eedd6d46c577d465423e05b6c8ea3accef415097cf9d5192c7af74`.
2. [Catálogo FEBRABAN](https://portal.febraban.org.br/pagina/3425/33/pt-br/layout-febraban), então apontando para [CNAB 240 V11.0, 11/09/2026](https://cmsarquivos.febraban.org.br/Arquivos/documentos/PDF/Layout%20padrao%20CNAB 240%20V%2011_0%20-%202026_09_11.pdf). SHA-256: `95a67618027784e9b9b4c4a48c8290082c2bb2245db758619a733f80f36204f4`. A própria página 252 identifica essa revisão como **preliminar**. Isso não confirma que o BB aceite todos os seus campos ou códigos de versão.
3. [BB: API de Cobrança](https://www.bb.com.br/site/developers/api-cobranca/), que descreve API v2 e pré-requisitos de convênio/cadastro Developers. A página geral diz que algumas APIs requerem certificados; não foi inferido mTLS universal.
4. [BB: credenciais](https://apoio.developers.bb.com.br/guias-e-tutoriais/primeiros-passos/credenciais). Não foram geradas, lidas, configuradas ou utilizadas credenciais reais.

Na pesquisa inicial405, a página de referência da API e o endpoint público de especificação não forneceram OpenAPI utilizável: ocorreram erros de TLS/upstream/timeout. O endereço `https://api.bb.com.br/cobrancas/v2/swagger?` respondeu 403 exigindo AppKey. Nenhuma proteção foi contornada. Endpoint, schema, scopes OAuth e exigência de certificado **não foram inventados** com base em implementações de terceiros. Para concluir o executor é necessário obter a especificação oficial atual por uma rota pública funcional ou exportação autorizada, sem segredos.

O projeto `wkarts/argws-financial-platform`, consultado somente para aprendizado no commit `688ddf93d2e4a3879bb60d11b8c025a9b115a053`, contém um adaptador BB de **CNAB 400**, e suas alegações de API não substituem a especificação/homologação. Nenhum código desse adaptador foi promovido a contrato CNAB 240.

## Contrato de escopo e permissões

- `issuer_id`: inteiro positivo; não há fallback para cliente, empresa global ou legado NULL.
- `connection_id`: identificador local estável, distinto da referência da conta.
- `account_id`: referência canônica `001:AGENCIA-DV:CONTA-DV`, cinco/doze dígitos, com DV numérico ou X. Não é prova remota de titularidade. Agência/conta zero são recusadas.
- `environment`: `sandbox` ou `production`, explicitamente selecionado; não há produção por ausência/conversão frouxa.
- `agreement_number`, carteira e variação pertencem à identidade imutável. Alterar a identidade exige outra conexão; alterar documento/nome do emitente não reatribui a conexão já existente.
- A partir da versão102 BB, cadastro usa `issuer_resource_can(id, staff, 'banco_brasil', 'manage') === true` como autorização contextual completa.
- Importação e consulta usam, respectivamente, capabilities nativas `import` e `view` no mesmo recurso Banco do Brasil/Empresa emitente, sem veto ou herança de papéis globais. Lista e menu são filtrados por `view`. Não existe fallback à permissão genérica anterior.
- O helper por recurso pertence ao núcleo Empresas, com grants explícitos. Administrador comum não herda acesso; master exige identidade persistida, colaborador ativo e papel administrativo. A falta de concessão mantém a ação bloqueada.
- Escritas exigem POST e token CSRF próprio de sessão, comparação constante, além do mecanismo global quando habilitado.

Não foram implementadas permissões implícitas para desativar o gate operacional dos emitentes. Nenhuma migração concede acesso financeiro.

## Perfil CNAB e limites intencionais

- As versões **084/043** são códigos do arquivo/lote previstos pelo manual específico BB; não significam versão 11 do manual FEBRABAN.
- Campos Alpha seguem alinhamento à direita com espaços à esquerda, conforme BB; Nosso Número é alinhado à esquerda, conforme a exceção explícita. Campos obrigatórios não aceitam somente espaços nem espaços nas bordas. Não há truncamento nem transliteração silenciosa de nomes/endereço.
- Convênio 7 + sequência 10 forma Nosso Número 17, sem DV. O único vetor textual de origem oficial usado no teste é `12345671234567890` (p.11); os demais dados são sintéticos.
- CPF/CNPJ preservam letras. O codec verifica formato, não substitui a validação cadastral de dígitos verificadores do emitente nem a homologação de CPF/CNPJ do pagador.
- Valores são strings decimais estritas convertidas para centavos inteiros, até `9999999999999.99`. Não há float, notação científica, inferência de moeda nem substituição de pago zero pelo valor nominal.
- Em **P228–229**, o BB determina `00` para Real (p.14). Em **T131–132**, G065 prevê `09` para Real (BB p.28 remete ao G065; FEBRABAN p.208). O parser restrito aceita somente `09` e preserva o código; um arquivo com outro código exige revisão do contrato, sem adivinhação.
- P118=3 exige que a isenção de juros também esteja cadastrada na carteira no BB (p.12). O codec não garante isenção por si só. Não é permitido usá-lo operacionalmente antes de validar esse contrato.
- `TS` no lote indica teste; o BB exige configurar intercâmbio TESTE com a agência. Escolher sandbox local ou produzir `TS` não é prova de homologação.
- Retornos: ASCII, CRLF, exatamente 240 bytes/linha, até 16 MiB; cabeçalho/conta/documento/convênio/versões, lotes sequenciais, pares T/U, sequência de registros e contadores dos trailers são obrigatórios. Segmentos fora do perfil são recusados, sem importação parcial.
- Os campos de valor nominal, pago, líquido, tarifas, juros/encargos, desconto, abatimento, IOF, outras despesas e outros créditos são preservados separadamente. Datas de ocorrência e crédito também. Alterações em campos financeiros que não compõem a chave semântica geram conflito de payload.
- Ocorrências 85/86 são recusadas: U123–125 pode conter agente negativador, não valor financeiro (BB p.31). Não se converte essa sobrecarga em centavos.
- Código 93/ operação P é observação de baixa operacional; código 50 é cheque pendente; 44 é cheque devolvido; nenhum deles é liquidação automática. Até 06/17 permanecem observações sujeitas a revisão de origem.
- A chave semântica inclui escopo, Nosso Número, ocorrência, motivos, pago/líquido e datas. Não há um event ID bancário universal documentado nessa fonte. Dois eventos realmente distintos com os mesmos campos podem colidir; o sistema mantém a proveniência e não executa finanças com essa suposição.

## Persistência, privacidade e ativação

A migração 101 cria sete tabelas próprias InnoDB: conexões, origens futuras, lotes futuros, arquivos de retorno, eventos, associações arquivo/evento e auditoria. Instalação inicial e upgrade usam o mesmo arquivo nativo. Reativação repete DDL aditiva/idempotente e preserva dados. Antes de confirmar a versão, a migração verifica InnoDB, collation binária, colunas/tipos/nulabilidade, ID auto incremental, PK e índices únicos integrais das sete tabelas. Estrutura preexistente incompatível bloqueia a confirmação sem reparar, enfraquecer constraints ou apagar dados. Downgrade ou remoção destrutiva não é executado.

O retorno bruto não é armazenado. São retidos hash, escopo, dados necessários à revisão e proveniência. Não há token, senha, certificado, PAN/CVV ou configuração global copiada. Documentos e referências de cobrança são dados administrativos: revisão segue ACL e a resposta é `no-store`. A auditoria registra ator e ação, sem conteúdo bruto do arquivo.

Para interromper o recurso, desative o módulo ou remova a concessão específica, preservando os dados. Não faça rollback removendo tabelas. Como não há baixa financeira automática, desativar não estorna pagamentos. A revisão histórica permanece no banco; restauração de código/schema exige backup e processo normal de manutenção.

## Validação e pendências

Testes locais sintéticos, PHP WASM 8.1/8.5: codec, persistência/rollback/CSRF/ACL/interface e executor nativo de migração. Veja o log/CI do commit correspondente para a contagem final; contagem sem SHA não certifica outra revisão.

Testes executados no CI do commit `30fb0f10a3ab5eac598d795ef9c712a1187b8364`, [workflow BB](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107753), sem homologação externa:

- PDO/MySQL 8.4: instalação nativa, InnoDB, deduplicação, conflito, rollback, transação externa e preservação histórica.
- Driver **mysqli CI 3 real**, dois processos: mesmo arquivo, evento reempacotado e payload conflitante; a espera pelo lock de registro PRIMARY da conexão é comprovada em `performance_schema.data_lock_waits`, identificando os dois connection IDs. Também testa transação externa SQL/CI e chained commit.

Em 19/10/2026, os três PDFs oficiais foram baixados novamente e os hashes coincidiram com os registrados. A página oficial de Cobrança aponta para `https://apoio.developers.bb.com.br/apis/5?versaoApi=2`; a página depende de JavaScript e não forneceu OpenAPI utilizável nesta consulta. A URL pública `https://api.bb.com.br/cobrancas/v2/swagger?` retornou 403. Nenhuma AppKey foi utilizada.

Não existe evidência de homologação, processamento de arquivo real, emissão de boleto, API autenticada ou integração de baixa PIGE. O pedido BB completo exige esses incrementos revisados, além do isolamento de emitente no core.

## Atualização413

Originação240 e retorno assistido estão ligados às faturas do núcleo; a API possui
executor separado conforme [handoff](banco-brasil-handoff.md). As limitações do codec
240 e a ausência de homologação permanecem. A especificação API oficial foi obtida
em19/10/2026; a indisponibilidade documental histórica acima foi resolvida.
