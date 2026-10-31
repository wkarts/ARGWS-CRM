# Definições → Clientes, formulário original

`NativeCompanyClientsSettings` fornece `fields()`, `page(issuer, actor)` e
`save(issuer, actor, revision, settingsPost)`. A página continua sendo
`admin/settings/includes/clients.php`; o controlador deve fornecer a DTO em
`native_settings_clients`. `render_yes_no_option` deve ser a implementação central
com o oitavo argumento explícito. Não chamar `get_contact_permissions()` global:
a mesma lista nativa finita é fornecida pelo adapter.

## Integração obrigatória antes de admitir o grupo

O adapter depende de `NativeCompanyEstimateRequestForms($db, $prefix)`:
`tables(): array` e `forSettings(int $issuer, int $actor): array` contendo IDs e
nomes reais. O reader é chamado dentro da transação owned; deve revalidar ACL e
proveniência. Não há substituição por lista vazia ou catálogo global. O integrador
é responsável por essa fonte, SettingsForms/controller e admissão final.

O save valida todos os 18 controles, países, IDs de formulários e tokens de
campos personalizados; a revisão cobre valores e catálogos. País inexistente,
formulário de outra empresa, token desconhecido, revisão antiga ou revogação de
ACL falham antes do commit. As opções da principal persistida usam seu guard
original; nenhuma opção named herda principal. Campos booleanos, arrays de
permissões, mapa serializado de abas e JSON de campos obrigatórios conservam seus
formatos nativos. A gramática de abas não executa `unserialize`.

## Consumidores e limites verificáveis

- Registro público: required fields, nome obrigatório, VAT, confirmação,
  honeypot e permissões usam os writers já existentes.
- Tema: apenas `perfex`, único tema distribuído e adaptado; valor desconhecido
  falha antes da renderização pública, sem carregar PHP de tema arbitrário.
- País padrão: cadastro administrativo e apresentação pública leem o issuer;
  não fica mais fixo em zero no formulário administrativo.
- Abas: preferência filtra o menu original já autorizado; não concede ações,
  não substitui ACL e nunca oculta os campos básicos/contatos obrigatórios.
- KB: publicação named continua explicitamente desligada (0/0) até configuração;
  não herda os defaults históricos 1/1 da principal.
- `customer_info_format`: validação e sanitização prontas, mas o integrador ainda
  precisa conectá-lo aos renderers financeiros com DTO autorizado e valores
  escapados. O adapter não anuncia que os documentos já o consomem.
- `only_own_files_contacts`, `allow_contact_to_delete_files` e
  `allow_primary_contact_to_manage_other_contacts`: paridade dos métodos públicos
  de arquivos/gestão de contatos continua pendente. O gate de Clients público
  permanece fechado; salvar configuração não prova consumer concluído.

## Provas locais

PHP 8.4 real: política/defaults/entradas negativas; catálogo de definições com
ACL settings sem concessão customers; perfil/país A/B com readers reais;
formulário original com helpers centrais reais e DTO de catálogo explicitamente
sintética. O teste de view não substitui o reader EstimateRequest real.
Após compor o reader real EstimateRequest f79264c1, o compositor inteiro passou
15 testes SQLite reais: A/B/principal, catálogos reais, CAS, opções consumidas,
país/campo/formulário de outra empresa, falha de auditoria e transferência de
formulário durante UPDATE com rollback integral. MySQL/concorrência de duas
conexões continuam pendentes. Nenhuma
validação de navegador ou matriz PHP 8.1/8.5 foi alegada.
