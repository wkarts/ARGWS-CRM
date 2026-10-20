# Recuperação de envio API interrompido

Um processo morto depois de persistir `sending` pode ter enviado o comando ao banco. O vencimento da reserva de execução não comprova ausência de efeito e não autoriza reenviar o POST/PATCH, atribuir rejeição ou registrar recebimento.

A repetição da instrução conserva o ID e o nonce originais e não autentica nem envia novamente. Com reserva ainda vigente, outras operações continuam bloqueadas. Depois do prazo, somente uma nova consulta explicitamente autorizada pode inspecionar a origem. Essa consulta tem seu próprio nonce persistido e revalida empresa, origem, perfil, carteira, conta, credenciais e resposta; o detalhamento e a listagem da conta devem concordar. Uma lista vazia ou limitada não prova ausência bancária.

A observação NORMAL comprovada pode recuperar a apresentação de uma origem que ficou em `api_auth_failed` durante uma retomada interrompida, sem fabricar resposta201, código de barras ou recibo. A observação7 confirma a baixa da origem e libera sua posição ativa; não paga nem cancela a fatura local.

Uma alteração de vencimento ainda em `sending` só pode ser confirmada depois do prazo e quando a data retornada corresponder exatamente à intenção original e à conta comprovada. O hash do nonce original é capturado antes da consulta à conta e conferido na conclusão, para impedir que uma consulta confirme uma reserva substituída. A data original da fatura permanece imutável. Uma data antiga ou observação inconclusiva deixa o envio incerto. Os bloqueios financeiros de alterações ou baixas ainda pendentes permanecem ativos.

Os testes usam SIGKILL real de filhos PHP próprios, após observar o marcador de envio persistido e um efeito de transporte simulado fora da transação. O prazo é acelerado nos fixtures por timestamp expirado; não há tráfego, credenciais ou homologação no Banco do Brasil.
