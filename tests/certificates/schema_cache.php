<?php
/** Exercise the actual CI table_exists/list_tables implementation with SQLite query I/O. */
require __DIR__.'/support.php';
require_once BASEPATH.'database/DB_driver.php';
final class A1CachedSchemaDb extends CI_DB_driver
{
    private IssuerTestDb $delegate;
    public function __construct(IssuerTestDb $db) { $this->delegate=$db; $this->conn_id=$db->pdo; }
    public function query($sql,$binds=false,$return_object=null) { return $this->delegate->query($sql,is_array($binds)?$binds:[]); }
    protected function _list_tables($prefix_limit=false) { return "SELECT name AS table_name FROM sqlite_master WHERE type='table'"; }
}
$raw=a1db();
$raw->query('DROP TABLE a1_issuer_certificate_audit');$raw->query('DROP TABLE a1_issuer_certificate_credentials');
$db=new A1CachedSchemaDb($raw);
a1check(!$db->table_exists('a1_issuer_certificate_credentials'),'native CI table cache primed before CREATE');
a1check(isset($db->data_cache['table_names']),'native CI list_tables cache populated');
IssuerCertificateSchema::ensure($db,'a1_');
a1check($db->table_exists('a1_issuer_certificate_credentials'),'native CI sees credentials after CREATE in same request');
a1check($db->table_exists('a1_issuer_certificate_audit'),'native CI sees audit after CREATE in same request');
IssuerCertificateSchema::ensure($db,'a1_');
a1check($db->table_exists('a1_issuer_certificate_credentials'),'schema replay leaves native table cache coherent');
echo "Certificate schema cache: actual CI readers, SQLite query adapter.\n";
