<?php
require dirname(__DIR__).'/application/services/NativeCompanyFormatting.php';
$n=0;$check=static function($ok)use(&$n){if(!$ok)throw new RuntimeException('Native formatting contract failed');++$n;};
foreach(CompanySettingsFormats::dates()as$format){foreach(['12','24']as$clock){$f=new NativeCompanyFormatting($format,$clock);$sql='2024-02-29';$shown=(new DateTimeImmutable($sql))->format($format);$check($f->date($sql)===$shown);$check($f->toSql($shown)===$sql);$check($f->toSql($f->datetime('2024-02-29 13:45:00'),true)==='2024-02-29 13:45:00');$check($f->date('0000-00-00')==='');}}
foreach(['2023-02-29','2024-13-01','tomorrow','2024-01-01<script>']as$bad){try{(new NativeCompanyFormatting('Y-m-d','24'))->toSql($bad);throw new RuntimeException('invalid date accepted');}catch(DomainException $e){$check(true);}}
echo "Native date formatting: {$n} checks passed.\n";
