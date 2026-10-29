<?php
/** Canonical replacements for the retired projects/index and tasks/index write transports.
 * Each child runs the real original controller and view with its own framework
 * harness. Process separation avoids sharing synthetic global functions/classes.
 * Covered: create/edit, CSRF, stale revisions, tenant isolation, checklist,
 * comments, attachments and escaped original templates. No child is optional.
 */
$checks=['native_projects_controller.php','native_project_form_controller.php',
    'native_task_form_http.php','native_task_items_controller.php',
    'native_task_comment_files_controller.php'];
foreach($checks as $check){
    $pipes=[];$child=proc_open([PHP_BINARY,'-d','display_errors=stderr','-d','log_errors=0',__DIR__.'/'.$check],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['redirect',1]],$pipes);
    if(!is_resource($child))throw new RuntimeException('Controller test process unavailable');
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($child);
    echo $output;
    if($code!==0||preg_match('/^(?:PHP )?(?:Warning|Deprecated|Notice|Fatal error|Parse error):/m',$output))
        throw new RuntimeException('Canonical project/task controller check failed: '.$check);
}
echo "Canonical project/task controller replacement coverage passed.\n";
