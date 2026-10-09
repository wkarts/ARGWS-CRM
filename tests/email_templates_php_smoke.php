<?php

declare(strict_types=1);

define('BASEPATH', '/app/system/');
require '/app/application/services/EmailTemplatesPtBr.php';

$core = EmailTemplatesPtBr::templates();
$modules = EmailTemplatesPtBr::moduleTemplates();

if (count($core) !== 82 || count($modules) !== 19) {
    fwrite(STDERR, 'Número inesperado de modelos traduzidos.' . PHP_EOL);
    exit(1);
}

foreach ($modules as $slug => $model) {
    $html = (string) $model['source_message'];
    try {
        $translated = EmailTemplatesPtBr::translateMessage($html);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Falha ao traduzir modelo ' . $slug . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }

    if ($html !== '' && ($html === $translated || $translated === '')) {
        fwrite(STDERR, 'O modelo não foi traduzido: ' . $slug . PHP_EOL);
        exit(1);
    }
    preg_match_all('/<[^>]*>/u', $html, $oldTags);
    preg_match_all('/<[^>]*>/u', $translated, $newTags);
    preg_match_all('/\{[^{}]+\}/u', $html, $oldVariables);
    preg_match_all('/\{[^{}]+\}/u', $translated, $newVariables);

    if ($oldTags !== $newTags || $oldVariables !== $newVariables) {
        fwrite(STDERR, 'HTML ou variáveis alterados indevidamente: ' . $slug . PHP_EOL);
        exit(1);
    }
}

echo 'Tradução em PHP: 82 modelos do núcleo e 19 de módulos catalogados.' . PHP_EOL;
