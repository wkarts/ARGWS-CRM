# PR 52 — validação do checkpoint recuperado

O commit `f11e52433021ce891139168ec729d03f49f5e166` recuperou o trabalho local. O job `node-contracts` do workflow `Native company experience focused` identificou dois testes registrados simultaneamente nas fases SQLite e MySQL, embora o contrato do runner proíba comandos idênticos entre fases. Houve também desatualização de números de linha no censo de ações após alterações dos controllers.

## Correção sem perda de cobertura

Os testes `contracts_value_branding.php` e `native_invoice_issued_form_update.php` permanecem em SQLite e são agora executados no MySQL por um wrapper explicitamente restrito ao banco descartável `issuer_native_module_audit`, que chama os mesmos testes reais sem substituir ou omitir asserções. Foram regenerados o censo de controllers e a matriz usando os geradores oficiais.

## Evidências locais

- `python3 tests/ci/test_native_company_focused.py`: 10 verificações aprovadas, inclusive unicidade do manifesto e barreiras de execução.
- `php scripts/native-experience-inventory.php` comparado byte a byte com o novo censo: idêntico.
- `php scripts/native-action-matrix.php --check`: 331 ações nos dez módulos, 272 admitidas no catálogo para secundárias, 59 não admitidas. **Admissão não equivale a paridade comprovada.**
- `php -l tests/companies/native_pr52_financial_pdf_mysql.php`: aprovado.
- 14 testes Node focados aprovados.

## Pendências para aceite

A correção acima ainda exige confirmação no CI do SHA efetivamente publicado. O ambiente local deste checkpoint não dispõe dos drivers PHP de SQLite/MySQL, nem do navegador instalado: não atribuir aprovação de banco ou UI ao código sem executar o CI. Os dois fixtures divergentes dos recibos anteriores também precisam de execução real. Continuam pendentes os testes amplos de tarefas/projetos, segurança principal/A/B/restrito, instalação limpa e upgrade e paridade funcional das ações não cobertas. CNAB400 permanece não homologado/desativado e não bloqueia esta publicação de software. Não fazer merge, deploy ou release.
