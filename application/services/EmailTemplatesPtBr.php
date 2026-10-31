<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Localização exclusiva dos modelos de e-mail distribuídos com o CRM.
 *
 * Mantém a marcação HTML original (incluindo atributos, links e estilos),
 * não altera nomes de campos/variáveis e nunca faz chamadas a serviços externos.
 */
final class EmailTemplatesPtBr
{
    private static ?array $phrases = null;
    private static ?array $templates = null;
    private static ?array $moduleTemplates = null;
    private static ?array $sources = null;

    public static function templates(): array
    {
        if (self::$templates === null) {
            self::$templates = self::loadJson('templates.json');
        }

        return self::$templates;
    }

    public static function moduleTemplates(): array
    {
        if (self::$moduleTemplates === null) {
            self::$moduleTemplates = self::loadJson('templates_modules.json');
        }

        return self::$moduleTemplates;
    }

    public static function get(string $slug): ?array
    {
        $core = self::templates();
        if (isset($core[$slug])) {
            return $core[$slug];
        }

        return self::moduleTemplates()[$slug] ?? null;
    }


    /** All known shipped sources, including explicitly cataloged historical variants. */
    public static function sourceDefinitions(): array
    {
        if (self::$sources !== null) return self::$sources;
        $definitions = [];
        foreach (self::loadJson('defaults.json') as $row) {
            $labels = self::templates()[$row['slug']];
            $definitions[$row['slug']][] = $labels + [
                'source_name' => $row['name'], 'source_subject' => $row['subject'],
                'source_message' => $row['message'], 'type' => $row['type'], 'module' => null,
            ];
        }
        foreach (self::moduleTemplates() as $slug => $definition) $definitions[$slug][] = $definition;
        foreach (self::loadJson('legacy_sources.json') as $slug => $sources) {
            foreach ($sources as $source) $definitions[$slug][] = $source;
        }
        return self::$sources = $definitions;
    }

    /** Public compatibility hook for module installation and existing admin entry points. */
    public static function synchronizeModuleTemplates($db, ?string $module = null): int
    {
        require_once __DIR__.'/EmailTemplateReconciler.php';
        return EmailTemplateReconciler::synchronize($db, db_prefix(), $module);
    }

    public static function translateMessage(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('O conteúdo HTML do e-mail não é UTF-8 válido.');
        }

        $phrases = self::phrases();
        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }

            $matches = [];
            preg_match_all('/\{[^{}]+\}/u', $part, $matches);
            $variables = $matches[0];

            $key = preg_replace('/\{[^{}]+\}/u', '%s', $part);
            $key = preg_replace('/&nbsp;|&#160;/iu', ' ', (string) $key);
            $key = trim((string) preg_replace('/\s+/u', ' ', (string) $key));

            if ($key === '' || !preg_match('/\p{L}/u', str_replace('%s', '', $key))) {
                // Pontuação, separadores e marcadores isolados não são traduzíveis.
                continue;
            }
            if (!array_key_exists($key, $phrases)) {
                throw new RuntimeException('Trecho sem tradução validada: ' . $key);
            }

            $translation = $phrases[$key];
            if (!is_string($translation) || substr_count($translation, '%s') !== count($variables)) {
                throw new RuntimeException('Quantidade de campos inconsistente no trecho: ' . $key);
            }

            foreach ($variables as $variable) {
                $position = strpos($translation, '%s');
                if ($position === false) {
                    throw new RuntimeException('Campo de mesclagem ausente: ' . $key);
                }
                $translation = substr_replace($translation, $variable, $position, 2);
            }

            preg_match('/^\s*/u', $part, $prefix);
            preg_match('/\s*$/u', $part, $suffix);
            $parts[$index] = ($prefix[0] ?? '') . $translation . ($suffix[0] ?? '');
        }

        $translated = implode('', $parts);
        // As tags e todos os campos de mesclagem, inclusive em URLs, devem
        // permanecer literalmente iguais aos originais.
        preg_match_all('/<[^>]*>/u', $html, $oldTags);
        preg_match_all('/<[^>]*>/u', $translated, $newTags);
        preg_match_all('/\{[^{}]+\}/u', $html, $oldVariables);
        preg_match_all('/\{[^{}]+\}/u', $translated, $newVariables);
        if ($oldTags[0] !== $newTags[0] || $oldVariables[0] !== $newVariables[0]) {
            throw new RuntimeException('A tradução modificou HTML ou variáveis do modelo.');
        }

        return $translated;
    }

    private static function phrases(): array
    {
        if (self::$phrases === null) {
            $phrases = [];
            foreach (['phrases_01.json', 'phrases_02.json', 'phrases_03.json', 'phrases_modules.json', 'phrases_legacy.json'] as $name) {
                $next = self::loadJson($name);
                foreach ($next as $key => $value) {
                    if (array_key_exists($key, $phrases)) {
                        throw new RuntimeException('Frase duplicada no catálogo: ' . $key);
                    }
                    $phrases[$key] = $value;
                }
            }
            self::$phrases = $phrases;
        }

        return self::$phrases;
    }

    private static function loadJson(string $filename): array
    {
        $path = __DIR__ . '/email_templates_ptbr/' . $filename;
        if (!is_file($path)) {
            throw new RuntimeException('Catálogo de e-mail ausente: ' . $filename);
        }
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Catálogo de e-mail inválido: ' . $filename);
        }

        return $decoded;
    }
}
