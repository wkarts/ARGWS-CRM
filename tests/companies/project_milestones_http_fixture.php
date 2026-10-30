<?php
/** Disposable native browser fixture. No runtime route and no real contacts. */
ob_start();require __DIR__.'/portal_task_write_http_fixture.php';$fixture=json_decode(ob_get_clean(),true,32,JSON_THROW_ON_ERROR);require_once APPPATH.'services/CompanyProjectMilestonesSchema.php';CompanyProjectMilestonesSchema::ensure($db,'tbl');CompanyProjectMilestonesSchema::ensure($db,'tbl');echo json_encode($fixture,JSON_THROW_ON_ERROR).PHP_EOL;
