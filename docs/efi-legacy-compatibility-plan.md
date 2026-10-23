# Plano seguro para compatibilidade do Efí legado

Estado conhecido no código base3833fa4c: módulo `gerencianet_gateway`, header
`5.1.4 sdk final`, sem diretório de migrations. Seu `install.php` ativa uma opção,
apaga destinos existentes e copia dois arquivos para o núcleo:

- `application/controllers/gateways/Gerencia_net.php`
- `application/libraries/gateways/Gerencianet_gateway.php`

A release4.7.2 amplia a capacidade de `installed_version`, mas não executa esse
instalador, não reconstrói a versão truncada e não converte o header para X.Y.Z.
Este documento não autoriza execução em produção.

## 1. Evidência antes de escolher baseline

Em uma cópia autorizada e isolada, levantar somente módulo, estado ativo, string
integral de versão e hashes dos dois arquivos de destino e fontes distribuídas.
Comparar com os commits/pacotes realmente instalados; registrar ausente/igual/
divergente. Não exportar opções de conta, tokens, certificados ou dados financeiros.
String truncada isoladamente não identifica qual pacote foi instalado.

**Critério para continuar:** pacote de origem identificável por hash, arquivos
core existentes classificados e backup verificável. Caso divergente ou desconhecido,
parar a conversão e registrar revisão manual; não escolher baseline presumido.

## 2. Separar carregamento do módulo da cópia de arquivos

Uma implementação futura deve resolver controllers/gateway via mecanismo suportado
pelo framework, sem sobrescrever arquivos globais durante ativação. Se ainda houver
compatibilidade com cópia histórica, o plano deve definir roteamento e remoção
controlada apenas de arquivos com hash comprovado; versões personalizadas devem
permanecer intactas. Evitar dois handlers concorrentes para o mesmo callback.

**Critério para continuar:** instalação limpa e reativação carregam a mesma classe
esperada sem alterações no núcleo, sem acesso ao provedor e sem troca de estado
operacional implícita.

## 3. Contrato explícito de versionamento

Propor em mudança revisada separada como o framework tratará o identificador não
canônico. Uma ponte de compatibilidade só pode aceitar baselines comprovados pela
etapa1 e preservar a string histórica em evidência auditável. Não renomear migrations
aplicadas, inventar101–514, truncar texto ou assumir que “5.1.4” significa514.
Se o contrato evoluir, testar também todos os módulos atuais X.Y.Z e colisões.

**Critério para continuar:** plano de transição aprovado, nova migration/tracking
verificável e erro fechado para qualquer identificador fora da lista comprovada.

## 4. Validação e reversibilidade

Matriz mínima: instalação limpa; cada baseline comprovado ativo/inativo; bytes
core personalizados; versão truncada desconhecida; replay; falha/negação de escrita
de tracking; rollback de código; callback autenticado e idempotente, payload
repetido e conta/ambiente divergentes. Usar fixtures locais para transporte e banco
isolado. Homologação financeira real exige autorização e credenciais próprias,
fora desta PR. Nunca ativar produção só para validar a interface.

**Critério de entrega:** tracking confirmado por readback, nenhum arquivo global
sobrescrito, estados preservados, CI final e evidência funcional/visual, backup e
procedimento de recuperação documentados. Até lá, Efí permanece bloqueado pelo
contrato de versão; a coluna maior não é um indicador de operacionalidade.
