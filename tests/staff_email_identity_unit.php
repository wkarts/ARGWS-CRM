<?php
declare(strict_types=1);
/** Pure canonicalization contract. This is not database/concurrency evidence. */
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/StaffEmailIdentity.php';
$checks=0;
function identityUnitCheck(bool $value,string $label):void { global $checks;++$checks;if(!$value){throw new RuntimeException('FAIL: '.$label);} }
foreach (['USER@EXAMPLE.INVALID'=>'user@example.invalid',' user@example.invalid '=>'user@example.invalid','MiXeD+Tag@Example.Invalid'=>'mixed+tag@example.invalid'] as $input=>$expected) {
    identityUnitCheck(StaffEmailIdentity::normalize($input)===$expected,'canonical valid input');
}
foreach (["\tuser@example.invalid", "user@example.invalid\n", " user\x00@example.invalid ", '', ' ', null, 123, [], 'café@example.invalid', 'not-an-email', str_repeat('a',90).'@example.invalid'] as $input) {
    try { StaffEmailIdentity::normalize($input); } catch(DomainException $error) { identityUnitCheck(!str_contains($error->getMessage(),'@'),'invalid input redacted rejection');continue; }
    throw new RuntimeException('FAIL: invalid input was accepted');
}
echo 'Staff email canonicalization: '.$checks.' checks passed; database tests remain separate.'.PHP_EOL;
