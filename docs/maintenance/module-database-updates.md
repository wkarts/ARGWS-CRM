# Manutenção de recursos instalados

O contador de Recursos e a tela `admin/modules/maintenance` usam o mesmo inventário de pendências. A tela Recursos agora exibe todos os módulos disponíveis, sem filtrar pelo catálogo ou por `CRM_HIDDEN_RESOURCES`. Exibição não altera estado ativo, autorização das ações, menus operacionais ou disponibilidade dos gateways. A tela de manutenção continua administrativa e técnica.

O inventário inclui módulos instalados ativos, desativados e fora do catálogo. Registros instalados cujos arquivos desapareceram permanecem visíveis como bloqueados. Emitentes, incorporado ao núcleo, fica excluído do executor de módulos.

Somente uma versão nativa válida, crescente e com todos os arquivos intermediários permite atualização, salvo a transição histórica explicitamente reconhecida da API 1.0.3 para 2.0.2 pelos arquivos 200–202. Downgrade, versões incompatíveis com o formato do executor e arquivos ausentes/duplicados exigem correção da distribuição. Não altere `installed_version` manualmente nem ative um módulo para limpar o contador.

A atualização exige administrador autenticado, POST e token de sessão adicional ao CSRF nativo. Um lock de conexão MySQL por módulo evita duas manutenções simultâneas. Após adquirir o lock, a ação relê a versão instalada, valida a sequência e chama o migrador nativo. O sucesso exige confirmação da versão persistida e preservação do estado ativo/inativo. A ação não chama hooks de ativação.

Faça backup antes de atualizar. Migrations podem conter DDL com commit implícito: o lock não representa rollback e uma falha pode deixar etapas parcialmente aplicadas. Uma versão pendente continua visível até a confirmação do marcador nativo. O fluxo não fornece garantia sobre efeitos internos de migrations de terceiros.

## Validação

- `php tests/module_maintenance.php`: inventário, controlador e migrador nativo com dados sintéticos.
- `php tests/ideal_module_migrations.php`: compatibilidade iDEAL e preservação de disponibilidade, estado e opções.
- `php tests/payment_gateway_catalog.php`: política comercial dos gateways.

Os testes sintéticos de lock não substituem execução concorrente em MySQL real. Não foram executadas migrations em instalação de cliente.

## Dependências legadas

Migrations de page_builder, accounting, hr_profile, timesheets, purchase e hr_payroll usam funções de helpers do módulo. Quando uma função necessária não está carregada, a manutenção mostra bloqueio antes de qualquer etapa e orienta obter bootstrap específico de manutenção. Não carrega init.php nem o arquivo principal do módulo para contornar o bloqueio. O mapa cobre dependências identificadas no código atual; não prova ausência de dependências em módulos de terceiros.

Não incluir indiscriminadamente os helpers: purchase_helper registra hooks ao carregar; page_builder_install altera rotas e chama install.php. O suporte ao upgrade desses inativos precisa ser implementado e testado por módulo, preservando o histórico de migrations.
