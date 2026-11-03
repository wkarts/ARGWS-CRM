<?php
/** Independent real-controller harnesses must not share their framework doubles. */
function runPhpContractChecks(array $checks): void
{
    foreach($checks as $check){
        if(!preg_match('~^tests/[A-Za-z0-9_/]+\.php$~D',$check))throw new InvalidArgumentException('Invalid contract check');
        $pipes=[];$child=proc_open([PHP_BINARY,'-d','display_errors=stderr','-d','log_errors=0',dirname(__DIR__,2).'/'.$check],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['redirect',1]],$pipes);
        if(!is_resource($child))throw new RuntimeException('Controller test process unavailable');
        fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($child);
        echo $output;
        if($code!==0||preg_match('/^(?:PHP )?(?:Warning|Deprecated|Notice|Fatal error|Parse error):/m',$output))
            throw new RuntimeException('Canonical controller check failed: '.$check);
    }
}
