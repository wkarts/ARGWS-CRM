<?php
/** Disposable native CLI bootstrap only. */
ob_start();require __DIR__.'/project_timesheets_http_fixture.php';$fixture=json_decode(ob_get_clean(),true,32,JSON_THROW_ON_ERROR);require_once APPPATH.'services/CompanyProjectGanttSchema.php';CompanyProjectGanttSchema::ensure($db,'tbl');CompanyProjectGanttSchema::ensure($db,'tbl');echo json_encode($fixture,JSON_THROW_ON_ERROR).PHP_EOL;
