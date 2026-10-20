# PR45 — validação HTTP do recuperado439

A instalação limpa439 e o upgrade nativo436→439 passaram em HTTP e Chromium reais sobre os bytes congelados: manifesto `ba0016ea94b6bf84a0e9cb0f55389894776ae27a7817db1f687deab0019d22f9`,18.485 arquivos de produção. Cada execução de confirmação conferiu18.471 arquivos da aplicação antes/depois; os14 arquivos do instalador ficam ausentes da raiz publicada do laboratório.

## Prova A — instalação limpa439

Instalador real, administrador17 com origem `installer`, CSRF inválido403 antes de criar tabelas. O banco foi preservado entre continuações, sem reset financeiro. Passaram:

| Cenário | Tempo |
|---|---:|
| Shell, seleção de empresa, permissões e instalação |12,79s|
| Portal A/B e contatos |11,71s|
| Faturas, pagamentos, portal privado e PDF |35,73s|
| Créditos e reversões |18,19s|
| Metadados de3 linhas, opcionais, clones e PDF |13,90s|
| Operações A/B, cronômetros, lote, discussão e anexo privado |39,00s|
| Microsoft: formulários, PKCE, segredo oculto, papéis e revogação |10,58s|
| Connect: perfil, fila, worker local, contexto e isolamento |12,02s|
| Pusher: recuperação de configuração e troca de responsável |10,87s|
| Recorrência: criar, retry, processar, PDF, pausar, retomar, limite e cancelar |14,00s|
| Funcionário nativo: criação GUI, membership e default; identidade preservada |10,08s|
| BB: origem, CNAB240, retorno e baixa assistida |27,35s|
| BB: componentes caixa/não caixa e relatórios |28,69s|
| BB: reversão externa declarada |21,23s|
| Callback público BB:12 verificações de rejeição |0,42s|
| BB: juros mensais |15,15s|
| BB: primeiro desconto fixo |16,01s|
| BB: primeiro desconto percentual |15,24s|
| BB: componentesAPI16 |33,64s|
| BB: origemAPI com retornoCNAB240 |72,03s|
| BB119: formulários de abatimento, endereço e desconto + projeções e serviço canônico |24,31s|

A fixture nativa final BB119 confirmou tracking `1.1.9:1`, executou o runner e seu replay, preservando51 faturas,16 recebimentos e64 operaçõesAPI. No cenário novo119, aGUI configura perfis e captura os parâmetros exatos dos formulários; PATCH/consulta usam serviços canônicos emCLI com transporte sintético, sem alegar POST bancário real do controlador.

O teste `company_context_tabs_browser.cjs` também passou, mas é uma prova de componente DOM e não conta como fluxo funcional HTTP.

## Prova B — upgrade nativo436→439

Instalação real436 do HEAD conhecido `e3ffef4a66231562d232d0e6b545c49898d0f548`; shell inicial12,87s e portal11,98s. Uma nova raiz imutável439 e um novo processoPHP reutilizaram o mesmo banco. O fluxo nativo de upgrade passou4,79s, negou CSRF antes da atualização e terminou tracking439. O conteúdo das oito tabelas históricas de identidade, empresas, acesso, clientes, contatos e recebimentos permaneceu byte a byte igual.18.471 arquivos de runtime conferidos antes/depois, sem diferenças.

## Execução e limites

Evidência estruturada: `docs/companies/evidence/http-resume439.json`. Os grupos mantêm resultados individuais e hashes dos logs. Comandos locais: `python test-runtime/run_resume_db.py --cwd "$PWD" -- python http-resume439/fresh439-1.py`, equivalente para `upgrade439-1.py`; continuações usam `python http-resume439/reuse-db.py python http-resume439/resume439-*.py`. O banco e o servidor filho executam no mesmo namespace; o shim local substitui somente operaçõesDocker por transporte local, preservando os CJS e suas asserções.

Foram retidas falhas de infraestrutura e de reconstrução de teste: extensõesPHP/sessões ausentes, montagem anterior com versões misturadas, operaçõesmkdir/cp não implementadas no shim; expectativa403 onde o contrato legado de projeto exige409; guards de testeBB116; falta do funcionário do fixture de upgrade legado em uma instalação nova; tentativa de repetir declaração mensal com a mesma referência idempotente. Esta última retomada usou nova referência sintética preenchida naGUI, mantendo todas as asserções. O onboarding legado completo negou corretamente o pré-requisito de masterNULL; foi substituído nesta instalação por um teste independente da criação nativa de funcionário, sem apagar a identidade17. O teste novoBB119 também teve corrigidos contratos do fixture: isenção canônica por interest_exempt sem interest_policy, ownership por JOIN da origem, e reload após mutaçãoCLI. Não houve correção de produção para fazer esses testes passarem.

Não foi executado o lote integral dos57 comandosDocker publicados neste ambiente; o script conserva os57 comandos na ordem original e incorpora8 novos fluxos, cujos hashes estão noJSON. Foram executados os cenários da tabela usando HTTP/Chromium reais. Integrações externas usaram somente doubles de protocolo locais por serviços canônicos: sem envio bancário, Microsoft, Pusher, WhatsApp ou SMTP. CNAB400 não recebeu nova provaGUI nesta rodada; homologação bancária não é alegada. Esta evidência vincula bytes locais congelados e precisa ser associada ao SHA publicado na etapa de publicação.
