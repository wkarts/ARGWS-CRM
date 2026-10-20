<?php
/** Reconstructed prerequisite; historical prototype sources were incomplete, no byte-equivalence claim. */
register_shutdown_function(static function():void{if(isset($GLOBALS['db'],$GLOBALS['p']))foreach(['company_sales_copy_requests','company_sales_revisions']as$t)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$t);});
require __DIR__.'/sales.php';
CompanySalesMutationsSchema::ensure($db,$p);CompanySalesMutationsSchema::ensure($db,$p);
