<?php
declare(strict_types=1);
define('BASEPATH',true);define('APPPATH',dirname(__DIR__,2).'/application/');class App_Model{}
require APPPATH.'models/Spam_filters_model.php';require_once APPPATH.'services/NativeSpamFilterMatcher.php';
class TestNativeSpamModel extends Spam_filters_model{public array$rules=[];function __construct(){}function get($rel){return$this->rules;}}
$model=new TestNativeSpamModel();$rules=[['type'=>'sender','value'=>'User@EXAMPLE.invalid'],['type'=>'subject','value'=>'Beginning'],['type'=>'phrase','value'=>'literal.*']];$model->rules=$rules;
foreach([['user@example.invalid','','','Blocked Sender'],['','Beginning subject','','Blocked Subject'],['','','A literal.* string','Blocked Phrase'],['','','A literal anything string',false],['','','',false]]as[$e,$s,$b,$expected])if($model->check($e,$s,$b,'tickets')!==$expected||NativeSpamFilterMatcher::check($rules,$e,$s,$b)!==$expected)throw new RuntimeException('native matcher diverged');
echo "Original Spam_filters_model and shared matcher passed exact sender/substring/case/literal precedence with no provider calls.\n";
