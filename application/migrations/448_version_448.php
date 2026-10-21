<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_448 extends CI_Migration
{
 public function up():void{require_once APPPATH.'services/CompanyTaskPrioritySchema.php';CompanyTaskPrioritySchema::ensure($this->db,db_prefix());}
 public function down():void{/* Close the new consumers; preserve task priority and canonical receipt history. */}
}
