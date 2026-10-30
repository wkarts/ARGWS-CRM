# Calendário — evidência remota do SHA447

SHA c07a03a2155d18f82a825fa48bba53476bf15e98, Stacks run39461317710/job118761232333. A execução terminou em falha. Artefato issuer-native-browser12286420736, ZIP SHA256c543eae10911b46d32fe10eda3c8ed1bc30c22aa8fdbd70ba731017b66ea1080. A captura foi baixada e examinada nesta retomada; resultados não são atribuídos a outroSHA.

A navegação mensal `company_calendar?anchor=2026-10-23` da segunda empresa devolveu **422**, HTMLcompleto de erro e semtabela. A resposta dizia “Não foi possível confirmar o calendário desta empresa.” A captura não foi truncada; screenshot precede o fechamento do browser. Na mesma empresa, a visão diária respondeu200 e confirmou as sete fontes próprias antes do novo acesso mensal422. A mensal da primeira empresa havia passado.

O log do processo registra a resposta422 final em1566ms, pico2097152bytes, sem last_error. Container continuava running, OOMKilledfalse,77,85MiB no instante coletado. O healthcheck2019 é incompatível com o servidorPHP-S usado pelo smoke e estava unhealthy também durante todos os testes200; não prova indisponibilidade da requisição. Esse healthcheck não foi alterado para produzir aprovação.

## Distinções sustentadas

- **Seletor/interface:** a view mensal contém a tabela, mas a resposta observada é outra página, de erro. Trocar o seletor ou ampliar timeout não resolve422.
- **Carregamento:** a requisição terminou em aproximadamente1,6s; não ficou carregando por30s.
- **Memória:** sem fatal/OOM, pico2MiB nessa requisição. Não é o esgotamento do harnessPHP8.1.
- **Permissão:** diária200 no mesmo contexto e mensal422, enquanto a negativa de acesso nativa usa403. Não há base para ampliar grants. A validação atual/referências permanece obrigatória.
- **Dados/domínio:** o erro corresponde ao catch deDomainException, com status422. Ainda falta o código exato da rejeição ou reprodução com os dados anteriores do smoke; não se anuncia causa raiz corrigida.

Os logs padrão da aplicação estavam desligados em production; por isso o código da exceção não veio no artefato447.449 habilita APP_LOG_THRESHOLD1 exclusivamente no app-config gerado para argws_upgrade_ci, antes do calendário, com guarda explícita do nome do banco. O controller registra somente código técnico limitado, nunca mensagem/conteúdo arbitrário. O coletor existente redige logs antes da limpeza. Nenhum seletor, timeout, filtro, limite ou cenário original é removido.

A reprodução local com conjunto reduzido passou; ela não equivale à história acumulada das dezenas de testes anteriores do smoke. A comparação deve continuar no SHA final e, se houver rejeição, usar o código técnico agora preservado. As pendências funcionais independentes e a falha de relógioLOCAL são assuntos separados.
