# Recuperar leitura API interrompida

Uma leitura query ou movements pode permanecer em sending após SIGKILL ou indisponibilidade SQL durante o registro de resultado. A guarda anterior aceitava nova consulta depois do prazo, mas conservava esse marcador de leitura e barrava para sempre outro PATCH. É uma lacuna local de disponibilidade, separada de homologação bancária.

Apenas nova consulta autorizada pode aposentar claim de leitura conhecido com prazo expirado e nonce válido. Sob mutex/transação existentes, a atualização compara ID, origem, operação, estado, nonce e prazo. O resultado fica unknown/read_lease_expired; nonce, prazo, hash e conteúdo originais são preservados e a ação é auditada. Nenhuma resposta bancária é inventada. Um trabalhador antigo que volte perde a condição sending exigida para concluir: não pode persistir prova, alterar a origem ou liberar a reserva.

Claims enviados de emissão, baixa ou PATCH não são aposentados por essa regra. A nova leitura ainda exige origem, credencial atual, permissões, detalhamento e confirmação independente da conta. Aposentar leitura não fornece prova NORMAL fresca, não repete instrução enviada e não movimenta o financeiro.

A prova usa SIGKILL real após marcador durável de GET título e POST de listagem de movimentos. Outro trabalhador real permanece vivo e retorna resposta7/conta coerente apenas após a aposentadoria e nova consulta NORMAL independente. Sua resposta tardia é rejeitada; origem e reserva são preservadas. Revogação, lease ativo, conta errada/listagem vazia e falta de prova fresca também são exercitados. Protocolo do provedor injetado; nenhuma credencial/transação BB real ou homologação.
