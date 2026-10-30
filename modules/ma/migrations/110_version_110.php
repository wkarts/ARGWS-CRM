<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** No database change; legacy marketing selectors exclude private customer ownership. */
class Migration_Version_110 extends App_module_migration
{
    public function up() { /* NOOP: privacy correction in audience selection. */ }
}
