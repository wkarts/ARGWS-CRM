<?php
/** Native disposable CLI fixture only, never reachable as a production HTTP service. */
ob_start();require __DIR__.'/project_milestones_http_fixture.php';$fixture=json_decode(ob_get_clean(),true,32,JSON_THROW_ON_ERROR);require_once APPPATH.'services/CompanyProjectTimesheetsSchema.php';CompanyProjectTimesheetsSchema::ensure($db,'tbl');CompanyProjectTimesheetsSchema::ensure($db,'tbl');echo json_encode($fixture,JSON_THROW_ON_ERROR).PHP_EOL;
