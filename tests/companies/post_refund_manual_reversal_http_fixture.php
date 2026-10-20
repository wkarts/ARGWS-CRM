<?php
/** Synthetic Asaas transport remains in the existing fixture;440 schema must already be installed. */
ob_start();require __DIR__.'/post_refund_manual_http_fixture.php';$fixture=ob_get_clean();
require_once APPPATH.'services/CompanyPostRefundManualReversalSchema.php';
CompanyPostRefundManualReversalSchema::ensure($db,'tbl',false);
echo $fixture;
