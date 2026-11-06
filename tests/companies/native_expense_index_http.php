<?php
/** CI entrypoint requires the positive MySQL phase; shared navigation fixture also runs on SQLite. */
if(getenv('CI')&&!str_starts_with((string)getenv('ISSUERS_TEST_DSN'),'mysql:'))throw new RuntimeException('Expense index HTTP CI requires the positive MySQL phase');
require __DIR__.'/native_expense_index_http_fixture.php';
if(getenv('CI')&&$db->driver()!=='mysql')throw new RuntimeException('Expense index HTTP CI requires the positive MySQL phase');
