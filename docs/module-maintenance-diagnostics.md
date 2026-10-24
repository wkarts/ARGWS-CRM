# Diagnóstico de disponibilidade e manutenção de recursos

Base investigada: `3833fa4c63c74d9d2fd0bf1457f1aeb18b726aa3` (4.7.1).

## Causas distintas

- **Banco do Brasil 1.2.3:** distribuído, mas ausente de `application/config/argws_resources.php`. O controlador conserva a restrição de ativação. O link genérico de manutenção era exibido mesmo sem divergência de versão; isso não demonstra falta de migration ou homologação.
- **iDEAL 1.0.1:** a disponibilidade é condicionada a `CRM_ENABLE_IDEAL`; a restrição não é pendência de banco. Esta correção não altera a configuração nem autoriza checkout.
- **Efí / gerencianet_gateway:** header `5.1.4 sdk final` não corresponde ao contrato nativo X.Y.Z. O schema de instalação e a migration histórica 230 definem `installed_version VARCHAR(11)`. A versão observada na interface, `5.1.4 sdk f`, corresponde aos primeiros 11 caracteres do header. Isso é compatível com truncamento histórico; o banco de produção não foi inspecionado nem alterado nesta investigação.

## Correção incluída

O catálogo mostra link de manutenção apenas quando o inventário realmente contém pendência. Recursos restritos sem pendência recebem a mensagem correspondente, diferenciando política de ambiente e ausência do catálogo. Nenhuma autorização, disponibilidade, activation hook, migration ou estado operacional é alterado.

## Efí: bloqueio preservado

O módulo não tem migrations e seu instalador legado copia arquivos para o núcleo. Ampliar a coluna ou remover o sufixo não estabelece um histórico confiável. Antes de uma correção de compatibilidade, é necessário aprovar e testar um plano específico para os baselines reais, conferir os arquivos legados instalados, definir tracking verificável e preservar os estados ativo/desativado. Não se deve fabricar migrations históricas ou mapear versões ambíguas silenciosamente. Este patch **não desbloqueia Efí**.

## Evidências e validação

- Adicionados cinco checks ao teste `tests/module_maintenance.php`, incluindo renderização real da lista, ausência de formulários de ativação e manutenção apenas quando pendente.
- `git diff --check`: aprovado.
- PHP e lint: não executados neste ambiente, pois `php` não está instalado (`command not found`).
- Executar em runtime PHP com PDO SQLite: `php tests/module_maintenance.php`; complementar com `php tests/ideal_module_migrations.php` e gates da release integrada.
- Validação manual recomendada em fixture: BB/iDEAL sem divergência não devem apontar manutenção; restrições continuam; Efí inválido permanece bloqueado; nenhum dado/ativação deve mudar ao abrir as páginas.

O bump contíguo de release do núcleo será aplicado uma única vez na integração coordenada. Nenhuma publicação, merge, implantação, manutenção de produção ou chamada financeira foi realizada.

## Migration 472: preservação do identificador legado

Após autorização explícita, foi adicionada a migration 472 que amplia a coluna de versão para VARCHAR(64), sem alterar strings existentes, normalizar versões, reconstruir sufixos perdidos, ativar módulos ou executar instaladores. O schema de instalação nova acompanha a capacidade. A migration preserva collation, nulabilidade, default e comentário, não reduz colunas já maiores, recusa tipos/metadados não reconhecidos e é idempotente. A migration 230 histórica permanece intocada. Rollback não estreita a coluna para evitar perda de dados; rollback de código deve manter a capacidade ampliada.

Requer backup e avaliação de lock do ALTER TABLE antes de uma implantação futura autorizada. A ampliação não corrige o identificador já truncado nem torna Efí compatível com o executor. Teste adicional implementado: `php tests/module_version_column_migration.php`, com DDL simulado para replay, falha, preservação de metadados e ausência de reescrita. Não executado: PHP indisponível. Teste real MySQL/MariaDB ainda necessário para afirmar preservação efetiva dos valores e comportamento de locks.

## Rota após atualizar o banco

`App::upgrade_database()` redireciona ao seletor `admin/company_context` após sucesso (HTTP 303). O seletor consome o recibo temporário e exibe o aviso. Isso é uma hipótese concreta para o relato de tela adicional, não identificação da tela realmente vista pelo usuário. Não foi alterado: a seleção de contexto é fronteira de segurança; uma correção deverá distinguir contexto já válido de seleção realmente necessária e testar ambos sem liberar acesso entre empresas.

## Controles administrativos solicitados posteriormente

Banco do Brasil foi incluído no catálogo de ativação administrativa, oferecendo os controles nativos Ativar/Desativar. Isso não ativa o módulo automaticamente, não altera ACL por empresa e não remove as verificações de contrato, perfil ou homologação do módulo. A descrição do recurso explicita essa separação. A causa inicial acima descreve a base investigada, antes desta correção.

iDEAL conserva a política CRM_ENABLE_IDEAL para ativação: quando false, a lista mostra Ativar indisponível com motivo explícito; quando true, oferece o controle nativo. Uma instalação iDEAL já ativa pode ser desativada explicitamente mesmo com a flag false, evitando prender o administrador em um estado legado ativo. Isso não habilita novos checkouts nem modifica a flag. Testes de catálogo, desativação explícita preservando versão e bloqueio de ativação foram adicionados à suíte iDEAL, ainda pendentes de execução PHP.

### Validação SQL real adicionada

`tests/companies/module_version_column_mysql.php` usa o driver CI3 PDO com MySQL/MariaDB e a fixture dedicada `issuer_*module_audit`. Executa o `up()` real da migration472 sobre tabelas temporárias, verificando largura, collation, nulabilidade, default, comentário, bytes dos marcadores existentes, estados ativo/desativado, gravação de rótulo longo novo, replay sem ALTER e preservação de coluna prévia128 com valor90 bytes. Apenas o despachante CI_Migration é adaptado; não é teste do runner nativo de release. O teste ainda não foi executado neste ambiente sem PHP; execução deve ocorrer no job SQL da integração, com as variáveis ISSUERS_TEST_DSN/USER/PASSWORD já provisionadas.
