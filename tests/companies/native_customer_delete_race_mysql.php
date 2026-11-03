<?php
/** Adversarial legacy-writer model; only runs against an explicitly disposable DB. */
declare(strict_types=1);
$dsn=getenv('ISSUERS_TEST_DSN')?:'';
if(getenv('ISSUERS_DELETE_RACE_ACK')!=='disposable'||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Disposable MySQL DSN and ISSUERS_DELETE_RACE_ACK=disposable required');
$user=getenv('ISSUERS_TEST_USER')?:'root';$password=getenv('ISSUERS_TEST_PASSWORD')?:'';
$db=static fn()=>new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(($argv[1]??'')==='worker'){
    $prefix=$argv[2]??'';if(!preg_match('/^codex_customer_race_[a-f0-9]{12}$/D',$prefix))throw new RuntimeException('invalid fixture');
    $c=$db();$c->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');$c->beginTransaction();
    if((int)$c->query('SELECT id FROM '.$prefix.'_clients WHERE id=1')->fetchColumn()!==1)throw new RuntimeException('customer not visible');
    echo "READY\n";flush();if(trim((string)fgets(STDIN))!=='GO')throw new RuntimeException('fixture protocol failed');
    $c->exec('INSERT INTO '.$prefix.'_invoices(id,clientid) VALUES(1,1)');$c->commit();echo "INSERTED\n";exit(0);
}
$prefix='codex_customer_race_'.bin2hex(random_bytes(6));$c=$db();$proc=null;$pipes=[];
try{
    $c->exec('CREATE TABLE '.$prefix.'_clients(id INT PRIMARY KEY) ENGINE=InnoDB');
    // install/database.sql indexes invoices.clientid, but does not define a client FK.
    $c->exec('CREATE TABLE '.$prefix.'_invoices(id INT PRIMARY KEY,clientid INT NOT NULL,KEY(clientid)) ENGINE=InnoDB');
    $c->exec('INSERT INTO '.$prefix.'_clients(id) VALUES(1)');
    $proc=proc_open([PHP_BINARY,__FILE__,'worker',$prefix],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    if(!is_resource($proc)||trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('worker did not pre-read customer');
    $c->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');$c->beginTransaction();
    $rows=$c->query('SELECT id FROM '.$prefix.'_invoices WHERE clientid=1 LIMIT 1 FOR UPDATE')->fetchAll();
    if($rows!==[])throw new RuntimeException('reference fixture unexpectedly populated');
    $c->exec('DELETE FROM '.$prefix.'_clients WHERE id=1');fwrite($pipes[0],"GO\n");fflush($pipes[0]);
    $read=[$pipes[1]];$write=null;$except=null;
    if(stream_select($read,$write,$except,0,300000)!==0)throw new RuntimeException('insert did not wait on the empty reference range');
    $c->commit();
    if(trim((string)fgets($pipes[1]))!=='INSERTED')throw new RuntimeException('worker insert failed');
    fclose($pipes[0]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$pipes=[];
    if(proc_close($proc)!==0)throw new RuntimeException('worker failed: '.$stderr);$proc=null;
    if((int)$c->query('SELECT COUNT(*) FROM '.$prefix.'_clients')->fetchColumn()!==0||(int)$c->query('SELECT COUNT(*) FROM '.$prefix.'_invoices WHERE clientid=1')->fetchColumn()!==1)throw new RuntimeException('race reproduction failed');
    echo "Race reproduced: legacy writer pre-read customer, waited for guard, then inserted an orphan after deletion committed. Native delete route must remain closed.\n";
}finally{
    if($c->inTransaction())$c->rollBack();foreach($pipes as$pipe)if(is_resource($pipe))fclose($pipe);if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}
    $c->exec('DROP TABLE IF EXISTS '.$prefix.'_invoices');$c->exec('DROP TABLE IF EXISTS '.$prefix.'_clients');
}
