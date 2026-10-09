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


    /**
     * Reconcilia os modelos dos módulos após a instalação, no upgrade e na
     * página administrativa. NÃO remove registros, alterna status nem altera
     * conteúdo personalizado. As edições são feitas campo a campo somente
     * quando ainda correspondem exatamente ao original conhecido.
     *
     * Para instaladores antigos que inseriram registros em inglês, preserva
     * a origem e cria uma cópia PT-BR, sem alterar o idioma do registro antigo.
     */
    public static function synchronizeModuleTemplates($db, ?string $module = null): int
    {
        $definitions = self::moduleTemplates();
        if ($module !== null) {
            $definitions = array_filter(
                $definitions,
                static function ($definition) use ($module) {
                    return ($definition['module'] ?? '') === $module;
                }
            );
        }
        if (!$definitions) {
            return 0;
        }

        $table = db_prefix() . 'emailtemplates';
        if (!$db->table_exists($table)) {
            return 0;
        }

        $rows = $db->where_in('slug', array_keys($definitions))
            ->get($table)->result_array();
        $bySlug = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $language = (string) ($row['language'] ?? '');
            if ($slug !== '' && in_array($language, ['portuguese_br', 'english'], true)) {
                $bySlug[$slug][$language] = $row;
            }
        }

        $count = 0;
        foreach ($definitions as $slug => $definition) {
            $localized = $bySlug[$slug]['portuguese_br'] ?? null;
            $english = $bySlug[$slug]['english'] ?? null;

            if (!$localized && $english) {
                $copy = $english;
                unset($copy['emailtemplateid']);
                $copy['language'] = 'portuguese_br';
                $copy = array_replace($copy, self::defaultOnlyChanges($copy, $definition));
                if ($db->insert($table, $copy)) {
                    ++$count;
                }
                continue;
            }

            if (!$localized) {
                // Modelos de módulos não instalados não são criados de modo antecipado.
                continue;
            }

            $updates = self::defaultOnlyChanges($localized, $definition);
            if ($updates) {
                $db->where('emailtemplateid', (int) $localized['emailtemplateid'])
                    ->update($table, $updates);
                ++$count;
            }
        }

        return $count;
    }

    private static function defaultOnlyChanges(array $row, array $definition): array
    {
        $updates = [];
        foreach (['name', 'subject', 'message'] as $field) {
            $source = (string) ($definition['source_' . $field] ?? '');
            $current = (string) ($row[$field] ?? '');
            if ($current !== $source) {
                // Inclusive conteúdos vazios ou personalizados são preservados.
                continue;
            }

            $translated = $field === 'message'
                ? self::translateMessage($source)
                : (string) ($definition[$field] ?? $source);
            if ($translated !== $current) {
                $updates[$field] = $translated;
            }
        }

        return $updates;
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
            foreach (['phrases_01.json', 'phrases_02.json', 'phrases_03.json', 'phrases_modules.json'] as $name) {
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
