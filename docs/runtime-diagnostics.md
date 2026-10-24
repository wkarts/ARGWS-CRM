# Diagnóstico de upstream e latência (sem modificar produção)

## Evidência de 24/10/2026

Duas navegações do navegador conectado para `/admin/`, às 07:44:47 e
07:45:03 UTC, exibiram **502 Bad Gateway / nginx**. Isso confirma a falha
observada, não identifica se o motivo é container parado, porta incorreta,
reinício, esgotamento de recursos ou outra falha upstream. O tempo da ferramenta
de navegador não foi usado como TTFB. Não houve acesso ao shell da VPS nem
alteração de serviço durante essa análise.

## Coleta local autorizada

Execute somente na máquina que hospeda o Docker do CRM, usando o nome real do
container. Não execute em outro servidor para inferir a saúde da VPS.

```sh
python3 scripts/diagnose-runtime.py --container NOME_REAL_DO_CONTAINER
```

Se a porta publicada já foi confirmada, acrescente `--local-port 8089` (substitua
pelo valor real). Isso faz **um único GET anônimo** de `/admin/` em loopback,
descarta corpo/cookies e registra código HTTP, conexão, TTFB e tempo total.
Sem essa opção não há requisição HTTP. Uma resposta de login/redirecionamento
não valida navegação autenticada nem consultas da sessão do usuário.

O script consulta apenas campos escalares permitidos de Docker inspect e uma
amostra de stats. Não reinicia, executa comandos dentro do container, altera
configurações, lê `.env`, exporta ambiente, credenciais, SQL, logs ou health logs.
Erros são registrados por código, sem copiar stderr. Campos indisponíveis não
significam recursos saudáveis. `memory_limit_bytes=0` não significa memória
infinita na VPS; significa ausência desse limite específico do container.

### Interpretação, sem mudanças automáticas

- Container parado/reiniciando, contador de restart ou OOM: correlacionar com
  horário da falha; um OOM histórico isolado não prova causa atual.
- HTTP local falha e público retorna 502: investigar upstream/container e porta
  real antes de mudar frontend, SMTP ou bloqueios de sessão.
- HTTP local responde e público retorna 502: verificar configuração/log de erro
  do proxy com acesso autorizado e redação de dados sensíveis.
- HTTP responde lentamente: separar TTFB, tempo de download e execução JS no
  waterfall de uma sessão autorizada. Somente depois correlacionar métricas
  de banco/esperas e PHP. Não remover locks de sessão ou isolamento multiempresa
  para mascarar espera; não aumentar timeouts/threads sem evidência.

O coletor **não mede queries, locks de sessão nem tempo PHP interno**. Esses
dados ainda exigem acesso autorizado ao runtime/banco. O PHPMailer e OAuth
existentes não constituem causa comprovada do 502.

## Validação local do coletor

```sh
python3 -m unittest discover -s tests -p test_runtime_diagnostics.py -v
```

Testes usam subprocessos simulados, verificam coleta restrita, limites, uma só
requisição local opcional e supressão de erros sensíveis. Não equivalem a teste
na VPS. A ferramenta não é habilitada automaticamente nem adiciona hooks à
aplicação, e não requer migration própria. Versionamento de release permanece
responsabilidade da PR integradora, conforme AGENTS.md.
