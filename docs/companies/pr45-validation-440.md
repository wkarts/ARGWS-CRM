# PR45 — validação HTTP do recuperado440

Instalação limpa440 e upgrade nativo439→440 passaram em HTTP e Chromium reais. O manifesto de18.522 arquivos de produção é `335a3203d495df04c9fb3d98349cb7c0eb8f83a7b23c5738646aa2fb987bc3ff`; cada raiz web confirmou18.508 arquivos antes/depois das execuções, sem diferenças. Os14 arquivos do instalador ficam ausentes da raiz web do laboratório.

## Instalação e upgrade

A instalação real440 criou o administrador17 com origem `installer`, negou CSRF inválido403 antes de criar tabelas e terminou tracking440. Shell/menu/contextos/mobile passaram11,26s; portal A/B11,81s; faturas/pagamentos/PDF privado37,51s.

No upgrade, o banco439 já validado foi reutilizado sem reset financeiro, com uma nova raiz imutável440 e um novo processoPHP. O fluxo nativo passou4,53s, preservando byte a byte oito tabelas de identidade, empresas, ACL, clientes, contatos e recebimentos. Master17/sourceinstaller e tracking440 confirmados. Não se usou mudança isolada de marcador para simular a migração.

## Fluxos440

| Cenário real | Tempo |
|---|---:|
| Rascunhos A/B/principal/restrito: linhas completas, nenhum número antes da emissão, cópia/cancelamento/retry, CSRF/contexto/rollback/PDF e API opt-in |22,88s|
| Modos de recorrência: principal criada e quitada pelaGUI, filhos anteriores abertos, edição de rascunho, emissão posterior, falha tardia e retomada pelo mesmo ID |28,27s|
| Cópias de projeto A/B/principal, tarefas/DAG/marcos, auditoria503 e retry303, recibos e API privada |35,56s|
| Cópias individuais de tarefas de projeto e avulsas A/B/principal, responsáveis/destinos explícitos, fonte imutável e não adoção de históricos |38,47s|
| Connect439 sob440: perfis/segredos/fila/worker local/contexto/revogação |12,00s|
| TicketConnect A/B/principal: defaultOFF, GUI, opt-in, fila/worker, uncertain sem resend, CSRF/cross403/suspensão |27,37s|
| BB120 origem/API/CNAB240/retorno/baixa assistida |26,50s|
| BB120 janela explícita e desconto nenhum→atribuir→cancelar→reatribuir |22,91s|
| BB119 abatimento/endereço/primeiro desconto sob120 |25,47s|
| RetornoAsaas→novo manual→reversãoLOCAL→cancelamentoLOCAL→nova fatura independente, A/B e PDF privado |25,49s|

Nos cenáriosBB, aGUI configura perfis e captura os parâmetros exatos dos formulários. PATCH/consulta usam serviços canônicos emCLI e doubles explícitos; não se alega envio bancário ou homologação. Os controladores bancários possuem provas separadas do domínioBB.

## POST de revisão e TLS local

O teste financeiro440 foi fortalecido sem remover asserções: depois do linkGET de revisão, clica também o botão real `Consultar novamente e revisar`, que enviaPOST ao controlador. O `CompanyClient` de produção executou quatroGETs HTTPS reais no sink local: wallets/payment paraA e paraB, todos emTLS1.3. A produção manteve verificação de cadeia e hostname habilitadas. O proxy aceita somente CONNECT para `api-sandbox.asaas.com:443`, os dois tokens sintéticos do fixture e caminhos de leitura estritos; não encaminha tráfego.

Uma CA efêmera com certificadoSAN correspondente foi fornecida somente ao processoPHP descartável. Probe semCA falhou antes de qualquerGET; o mesmo cliente comCA passou. Nenhum domínio externo recebeu tráfego. O exportador de protocolo apenas lê o próprio estado sintético e verifica os hashes e a normalização canônica dos dados. Certificados/chaves gerados não integram o código publicado.

## CI e autoria das correções de teste

`tests/issuers_standalone_http_smoke.sh` conserva65 comandos439 na ordem original e acrescenta7 comandos440. Todos os novos cenários foram executados individualmente no laboratórioHTTP real. `bash -n` e as duas provas de `test_issuer_browser_environment.py` passaram; cada comandoNode recebe seu ambiente descartável completo. O loteDocker integral não foi executado neste ambiente.

A infraestrutura publicável roda o proxyPython no host do runner, vinculado ao gateway privado da redeDocker sintética. O containerPHP recebeHTTPS_PROXY e curl.cainfo desde sua criação; aCA e três helpers de teste são copiados para ele. O estado do sink é exportado por `docker exec` comPHP do próprio container. Não requer Python na imagem da aplicação nem caminhos do laboratório. Há timeout de inicialização, cleanup dePID no trap e probes negativos/positivos deCA ao final.

Falhas retidas e corrigidas exclusivamente no teste: prazo de tarefa deslocado terminava12/20 enquanto o projeto novo terminava12/19; seletor usava o botão de projeto para criar tarefa avulsa; snapshot financeiro era capturado antes dos cronômetros criados intencionalmente no preparoGUI. Agora o snapshot é capturado antes de cada cópia, após preparar a fonte, e todas as tabelas, incluindo timers e claims, são comparadas depois da cópia/retry, sem excluir linhas ou tabelas. A regex genérica de credencialBB foi substituída pela mensagem de sucesso exata do controlador. A primeira execução financeira original passou semTLS porque o seletor era um linkGET; a expectativa extra do harness detectou a ausência deGETs. O cliquePOST explícito foi acrescentado e a provaTLS completa então passou. Nenhum desses ajustes alterou produção, versões ou migrations.

Evidência estruturada, hashes e tentativas: `docs/companies/evidence/http-resume440.json`. Comandos locais efetivos: `python test-runtime/run_resume_db.py --cwd "$PWD" -- python http-resume440/fresh440-1.py`, `python http-resume439/reuse-db.py python http-resume440/upgrade439-440-1.py` e continuações `python http-resume440/reuse-db.py python http-resume440/resume440-*.py`. Essas provas vinculam bytes congelados locais; o SHA publicado e o estado terminal do CI remoto devem ser associados na publicação.
