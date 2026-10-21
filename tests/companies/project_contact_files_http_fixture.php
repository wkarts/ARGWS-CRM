<?php
/** Disposable native CLI fixture; no runtime route or migrated authority. */
ob_start();require __DIR__.'/project_gantt_http_fixture.php';$fixture=json_decode(ob_get_clean(),true,32,JSON_THROW_ON_ERROR);require_once APPPATH.'services/CompanyProjectContactFilesSchema.php';CompanyProjectContactFilesSchema::ensure($db,'tbl');CompanyProjectContactFilesSchema::ensure($db,'tbl');echo json_encode($fixture,JSON_THROW_ON_ERROR).PHP_EOL;
