<?php
require_once __DIR__.'/../application/services/TagPresentation.php';
$checks=0;
function tagCheck($ok){global$checks;if(!$ok)throw new RuntimeException('tag presentation mismatch');++$checks;}
tagCheck(TagPresentation::render([])==='');
$html=TagPresentation::render([['id'=>7,'name'=>'A <private>','order'=>0],['id'=>'111669149697','name'=>'Owned & literal','order'=>1]]);
tagCheck($html==='<div class="tags-labels"><span class="label label-tag tag-id-7"><span class="tag">A &lt;private&gt;</span><span class="hide">, </span></span><span class="label label-tag tag-id-111669149697"><span class="tag">Owned &amp; literal</span><span class="hide"></span></span></div>');
tagCheck(str_contains(TagPresentation::render([['id'=>0,'name'=>'Unknown legacy']]),'tag-id-0'));
foreach([[['id'=>'7" onclick="x','name'=>'Unsafe']],[['id'=>1,'name'=>['x']]]]as$tags){try{TagPresentation::render($tags);throw new RuntimeException('unsafe tag accepted');}catch(DomainException$expected){++$checks;}}
$source=file_get_contents(__DIR__.'/../application/helpers/tags_helper.php');tagCheck(str_contains($source,'TagPresentation::render($presentation)'));
echo "Shared native tags markup: {$checks} checks PASS.\n";
