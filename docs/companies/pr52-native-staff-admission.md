# PR52 — admissão do Staff original

Incremento sobre `a16d724`; não representa conclusão do multiempresa.

## Código

- O menu original Colaboradores volta à empresa secundária; as URLs continuam
  `/admin/staff` e `/admin/staff/member/{id}` e reutilizam os templates oficiais.
- A admissão é finita: tabela AJAX POST, formulário GET/HEAD/POST, consulta de
  papel e ações de status/avatar/colunas já adaptadas. Não admite exclusão global
  da identidade, alteração global de idioma ou CRUD legado de apontamentos.
- O bootstrap carrega em conjunto os helpers de campos, DataTables, campos
  personalizados, texto, template e idioma exigidos pelas views originais.
- Valores de horas usam a moeda pública explicitamente carregada pelo leitor da
  empresa; a formatação não chama hooks globais nem consulta saldos/clientes.
- Notas usam os endpoints nativos revisados, POST/CSRF, relação autorizada e
  revisão; o retorno da criação abre o mesmo cadastro original. A ação de timer
  admitida usa o contrato próprio de política/revisão e não libera exclusão GET.
- A tabela liga avatar, nome e ação ao cadastro original; o modal legado de
  apagar identidade/transferir todos os dados não é emitido no escopo secundário.

As regras transacionais dos serviços existentes continuam responsáveis por
vínculo, identidade compartilhada, roles/departamentos, salário por empresa,
permissões concedíveis e revisão. Formulários antigos não recebem um contexto
novo automaticamente.

## Provas e limites

A stack passa a abrir tabela e formulário reais, criar identidade sintética sem
e-mail de boas-vindas, verificar vínculo exclusivo e ausência de admin global,
trocar a empresa e exigir409 no formulário anterior. Capturas desktop/mobile
incluem lista e cadastro. Esses novos passos precisam passar no CI do novo SHA.
O teste de renderização inclui uma linha de hora com valor conhecido e faz o
helper monetário global lançar exceção se for chamado.

No SHA anterior: PWA40400474487 e interface40400477840 passaram; stack40400474905
falhou porque o Staff não era admitido no menu. Focused PR40400478634/push40400465157
e inventário de Empresas40400474632 falharam pelo hash desatualizado de fronteiras.
O checkout limpo foi usado para regenerar e conferir os inventários.

Empresas também falhou em `pdf_typography_fiscal_identity.php`: a mutação de
CNPJ era malformada e nem chegava à comparação fiscal esperada. O cenário v2
agora usa mudanças válidas, mantém a comparação de todos os campos fiscais,
testa separadamente CNPJ inválido e chaves desconhecidas e confirma a exclusão
documentada de metadados de apresentação, como o cenário v1 já fazia. Nenhum
guard de produção foi relaxado.

PHP/Composer/Docker/banco não estão instalados neste executor. Os testes PHP,
renderização autenticada, bancos e suíte completa continuam dependentes dos
workflows existentes; verificações Node/Python locais não substituem essas provas.

## Pendências preservadas

O controller/view `Company_staff` ainda existe para os fluxos de vincular e
revogar acesso não migrados para o cadastro nativo; sua remoção requer portar
essas operações e suas provas de navegador, sem perder a proteção de identidade
compartilhada. A contagem de49 controllers paralelos não é reduzida neste passo.
Outros recursos nativos relacionados, incluindo detalhe completo de tarefas e
projetos, ainda precisam da admissão integral antes da retirada de seus pares.

CNAB400 operacional, preservação integral do retorno original, administração de
empresas sem procedimento legado e aceite completo das demais integrações
continuam pendentes. API/CNAB independentes e PWA corrigido são preservados.
Não houve operação financeira, SMTP ou chamada externa real. PR em rascunho.
