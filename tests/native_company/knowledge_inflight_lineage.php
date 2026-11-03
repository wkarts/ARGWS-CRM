<?php
declare(strict_types=1);
require __DIR__.'/knowledge_read.php';
final class KnowledgeLineageRace {
 public bool $db_debug=true,$save_queries=true,$fired=false;
 public function __construct(private $base){}
 public function query($sql,$args=[]){$rows=$this->base->query($sql,$args);if(!$this->fired&&str_starts_with($sql,'SELECT * FROM tblknowledge_base ORDER')){$this->fired=true;$this->base->query('UPDATE tblissuers_installation_identity SET primary_issuer_id=3 WHERE id=1');}return$rows;}
 public function table_exists($t){return$this->base->table_exists($t);}public function field_exists($c,$t){return$this->base->field_exists($c,$t);}
}
$race=new KnowledgeLineageRace($db);$reader=new NativeCompanyKnowledge($race,'tbl');$deny(fn()=>$reader->articles(1,17));kc($race->fired,'actual historical query triggered lineage change');
echo "Knowledge historical stream fails closed if primary lineage changes during read\n";
