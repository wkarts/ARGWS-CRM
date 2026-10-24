<?php
/** Execute the real native library-selection loop, not application bootstrap or gateway code. */
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/application/config/autoload.php');
if(!preg_match('/foreach \(\$gateways as \$gateway\) \{[\s\S]*?\n\}/',$source,$match))throw new RuntimeException('Native gateway selection block not found');
$gateways=['Gerencianet_gateway.php','gerencianet_gateway.php','Stripe_gateway.php','App_gateway.php','.hidden.php','ignored.txt'];
$autoload=['libraries'=>['gateways/app_gateway']];
// This bounded block only maps the synthetic list above to library names; no includes/instances.
if(preg_match('/\b(?:include|require|eval|new)\b/',preg_replace('~//[^\n]*~','',$match[0])))throw new RuntimeException('Unexpected executable selection dependency');
eval($match[0]);
if($autoload['libraries']!==['gateways/app_gateway','gateways/stripe_gateway'])throw new RuntimeException('Copied Efí core gateway shadowed modular implementation or another gateway changed');
echo "Efí native gateway selection: PASS; copied class excluded, other gateway preserved\n";
